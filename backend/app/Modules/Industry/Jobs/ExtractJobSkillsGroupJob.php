<?php

declare(strict_types=1);

namespace App\Modules\Industry\Jobs;

use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Modules\Ai\Concerns\HasGeminiJobSettings;
use App\Modules\Ai\EmbeddingService;
use App\Modules\Ai\Exceptions\GeminiException;
use App\Modules\Ai\Exceptions\GeminiQuotaExhaustedException;
use App\Modules\Ai\Exceptions\GeminiRateLimitedException;
use App\Modules\Ai\GeminiClient;
use App\Modules\Competency\Services\EvidenceVerifier;
use App\Modules\Competency\Services\SkillNormalizer;
use App\Modules\Industry\Services\JobSkillExtractionService;
use App\Modules\Rps\Enums\ExtractionStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Extracts a few job postings at the same time: one Gemini request per posting (never mixed in one
 * prompt), sent together through Http::pool, and one embedding batch for all of their skills.
 *
 * Any posting that cannot be finished here is handed to ExtractJobSkillsJob, which owns the
 * per-posting retries and the final "failed" state.
 */
class ExtractJobSkillsGroupJob implements ShouldQueue
{
    use Dispatchable, HasGeminiJobSettings, InteractsWithQueue, Queueable, SerializesModels;

    // Legacy upper bound: retryUntil() (see HasGeminiJobSettings) takes precedence while it is defined.
    public int $tries = 3;

    /**
     * @param  list<int>  $jobPostingIds
     */
    public function __construct(
        public array $jobPostingIds,
    ) {
        $this->configureGeminiJob();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping(implode('-', $this->jobPostingIds)))->dontRelease()->expireAfter($this->overlapLockSeconds())];
    }

    public function handle(
        GeminiClient $gemini,
        SkillNormalizer $skillNormalizer,
        EvidenceVerifier $evidenceVerifier,
        ?EmbeddingService $embeddingService = null,
    ): void {
        $embeddingService ??= app(EmbeddingService::class);
        $extraction = new JobSkillExtractionService($skillNormalizer, $evidenceVerifier);

        $postings = JobPosting::query()->whereIn('id', $this->jobPostingIds)->get()->keyBy('id');

        /** @var array<string, array{prompt: string, prompt_version: string, subject: JobPosting, response_schema: array<string, mixed>}> $requests */
        $requests = [];
        /** @var array<int, string> $texts */
        $texts = [];

        foreach ($this->jobPostingIds as $id) {
            $posting = $postings->get($id);

            if ($posting === null || $posting->extraction_status === ExtractionStatus::Completed) {
                continue;
            }

            // Saved skills that only miss their embedding are finished by the single job (no Gemini call).
            if ($extraction->hasUnembeddedSkills($posting)) {
                ExtractJobSkillsJob::dispatch($id);

                continue;
            }

            $posting->update(['extraction_status' => ExtractionStatus::Processing, 'error_code' => null, 'error_message' => null]);

            $text = $extraction->combinedText($posting);
            if ($text === '') {
                $posting->update(['extraction_status' => ExtractionStatus::Completed]);

                continue;
            }

            $requests['posting-'.$id] = $extraction->buildRequest($posting, $text);
            $texts[$id] = $text;
        }

        if ($requests === []) {
            return;
        }

        try {
            $responses = $gemini->generateContentConcurrent(
                'job_extraction',
                $requests,
                max(1, (int) config('skillbridge.gemini.jobs.concurrent_postings', 2)),
            );
        } catch (Throwable) {
            // Invalid provider configuration: nothing was sent, nothing can be retried.
            foreach (array_keys($texts) as $id) {
                $postings->get($id)?->update(['extraction_status' => ExtractionStatus::Failed]);
            }

            return;
        }

        /** @var array<int, JobPosting> $stored */
        $stored = [];

        foreach (array_keys($texts) as $id) {
            $posting = $postings->get($id);
            $response = $responses['posting-'.$id] ?? new GeminiException;

            if ($posting === null) {
                continue;
            }

            if ($response instanceof GeminiQuotaExhaustedException) {
                $this->markQuotaFailed($posting, $response);

                continue;
            }

            if ($response instanceof GeminiRateLimitedException) {
                ExtractJobSkillsJob::dispatch($id)->delay(now()->addSeconds($response->retryAfterSeconds));

                continue;
            }

            $skills = $response instanceof GeminiException ? null : $extraction->parse($response);
            if ($skills === null) {
                ExtractJobSkillsJob::dispatch($id);

                continue;
            }

            $extraction->store($posting, $texts[$id], $skills);
            $stored[$id] = $posting;
        }

        $this->embedAndComplete($embeddingService, $stored);
    }

    private function markQuotaFailed(JobPosting $posting, GeminiQuotaExhaustedException $exception): void
    {
        $posting->update([
            'extraction_status' => ExtractionStatus::Failed,
            'error_code' => GeminiQuotaExhaustedException::CODE,
            'error_message' => $exception->getMessage(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        JobPosting::query()
            ->whereIn('id', $this->jobPostingIds)
            ->where('extraction_status', '!=', ExtractionStatus::Completed->value)
            ->update(['extraction_status' => ExtractionStatus::Failed->value]);
    }

    /**
     * One embedding batch for every saved skill of the group.
     *
     * @param  array<int, JobPosting>  $stored
     */
    private function embedAndComplete(EmbeddingService $embeddingService, array $stored): void
    {
        if ($stored === []) {
            return;
        }

        try {
            $jobSkills = JobSkill::query()
                ->whereIn('job_posting_id', array_keys($stored))
                ->with('skillEntry')
                ->get();

            if ($jobSkills->isNotEmpty()) {
                $embeddingService->embedJobSkills($jobSkills);
            }

            foreach ($stored as $posting) {
                $posting->update(['extraction_status' => ExtractionStatus::Completed]);
            }
        } catch (GeminiRateLimitedException $e) {
            foreach (array_keys($stored) as $id) {
                ExtractJobSkillsJob::dispatch($id)->delay(now()->addSeconds($e->retryAfterSeconds));
            }
        } catch (GeminiQuotaExhaustedException $e) {
            foreach ($stored as $posting) {
                $this->markQuotaFailed($posting, $e);
            }
        } catch (Throwable) {
            foreach (array_keys($stored) as $id) {
                ExtractJobSkillsJob::dispatch($id);
            }
        }
    }
}
