<?php

declare(strict_types=1);

namespace App\Modules\Industry\Jobs;

use App\Models\JobPosting;
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

class ExtractJobSkillsJob implements ShouldQueue
{
    use Dispatchable, HasGeminiJobSettings, InteractsWithQueue, Queueable, SerializesModels;

    // Legacy upper bound: retryUntil() (see HasGeminiJobSettings) takes precedence while it is defined.
    public int $tries = 3;

    public function __construct(
        public int $jobPostingId,
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
        return [(new WithoutOverlapping((string) $this->jobPostingId))->dontRelease()->expireAfter($this->overlapLockSeconds())];
    }

    public function handle(
        GeminiClient $gemini,
        SkillNormalizer $skillNormalizer,
        EvidenceVerifier $evidenceVerifier,
        ?EmbeddingService $embeddingService = null,
    ): void {
        $embeddingService ??= app(EmbeddingService::class);
        $extraction = new JobSkillExtractionService($skillNormalizer, $evidenceVerifier);

        $posting = JobPosting::find($this->jobPostingId);
        if ($posting === null) {
            return;
        }

        if ($posting->extraction_status === ExtractionStatus::Completed) {
            return;
        }

        $posting->update(['extraction_status' => ExtractionStatus::Processing, 'error_code' => null, 'error_message' => null]);

        $combinedText = $extraction->combinedText($posting);
        if ($combinedText === '') {
            $posting->update(['extraction_status' => ExtractionStatus::Completed]);

            return;
        }

        $mode = strtolower(trim((string) config('skillbridge.gemini.mode', 'gemini')));
        $key = config('skillbridge.gemini.api_key');
        $model = config('skillbridge.gemini.flash_model');
        $timeout = config('skillbridge.gemini.timeout_seconds');

        if (! in_array($mode, ['offline', 'fallback', 'auto'], true)
            && (! is_string($key) || trim($key) === ''
                || ! is_string($model) || ! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,254}\z/', $model)
                || ! is_int($timeout) || $timeout < 1)) {
            $posting->update(['extraction_status' => ExtractionStatus::Failed]);

            return;
        }

        try {
            // Resume point: skills were saved earlier but the embedding step was interrupted
            // (for example by the embedding quota), so do not call Gemini for the text again.
            if (! $extraction->hasUnembeddedSkills($posting)) {
                $request = $extraction->buildRequest($posting, $combinedText);

                $response = $gemini->generateContent(
                    purpose: 'job_extraction',
                    prompt: $request['prompt'],
                    promptVersion: $request['prompt_version'],
                    subject: $posting,
                    responseSchema: $request['response_schema'],
                );

                $validatedSkills = $extraction->parse($response);
                if ($validatedSkills === null) {
                    throw new GeminiException('Format atau skema hasil ekstraksi lowongan tidak valid.');
                }

                $extraction->store($posting, $combinedText, $validatedSkills);
            }

            // Embed job skills via EmbeddingService (T4.1)
            $jobSkills = $posting->jobSkills()->with('skillEntry')->get();
            if ($jobSkills->isNotEmpty()) {
                $embeddingService->embedJobSkills($jobSkills, $posting);
            }

            $posting->update(['extraction_status' => ExtractionStatus::Completed]);
        } catch (GeminiRateLimitedException $e) {
            // Nothing failed: wait for the per-minute budget and try again (status stays "processing").
            $this->releaseForRateLimit($e);
        } catch (GeminiQuotaExhaustedException $e) {
            // The daily budget is gone: retrying before the reset is pointless.
            $posting->update([
                'extraction_status' => ExtractionStatus::Failed,
                'error_code' => GeminiQuotaExhaustedException::CODE,
                'error_message' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $posting->update(['extraction_status' => ExtractionStatus::Failed]);

            if (! app()->runningUnitTests()) {
                throw $e;
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $posting = JobPosting::find($this->jobPostingId);
        if ($posting !== null && $posting->extraction_status !== ExtractionStatus::Completed) {
            $posting->update(['extraction_status' => ExtractionStatus::Failed]);
        }
    }
}
