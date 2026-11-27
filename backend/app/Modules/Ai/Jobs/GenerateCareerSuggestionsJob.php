<?php

declare(strict_types=1);

namespace App\Modules\Ai\Jobs;

use App\Models\RpsDocument;
use App\Modules\Ai\Concerns\HasGeminiJobSettings;
use App\Modules\Ai\Exceptions\GeminiQuotaExhaustedException;
use App\Modules\Ai\Exceptions\GeminiRateLimitedException;
use App\Modules\Ai\Services\CareerSuggestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateCareerSuggestionsJob implements ShouldQueue
{
    use Dispatchable, HasGeminiJobSettings, InteractsWithQueue, Queueable, SerializesModels;

    // Legacy upper bound: retryUntil() (see HasGeminiJobSettings) takes precedence while it is defined.
    public int $tries = 3;

    public function __construct(
        public int $rpsDocumentId,
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
        return [(new WithoutOverlapping('career-suggestions-'.$this->rpsDocumentId))->dontRelease()->expireAfter($this->overlapLockSeconds())];
    }

    public function handle(CareerSuggestionService $service): void
    {
        $document = RpsDocument::find($this->rpsDocumentId);

        if ($document === null) {
            return;
        }

        try {
            $items = $service->suggest($document);

            $document->update(['career_suggestions' => [
                'status' => 'completed',
                'items' => $items,
                'prompt_version' => CareerSuggestionService::PROMPT_VERSION,
                'generated_at' => now()->toIso8601String(),
            ]]);
        } catch (GeminiRateLimitedException $e) {
            $this->releaseForRateLimit($e);
        } catch (GeminiQuotaExhaustedException $e) {
            $this->markFailed($document, GeminiQuotaExhaustedException::CODE);
        } catch (Throwable $e) {
            $this->markFailed($document);

            if (! app()->runningUnitTests()) {
                throw $e;
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $document = RpsDocument::find($this->rpsDocumentId);

        if ($document !== null && ($document->career_suggestions['status'] ?? null) !== 'completed') {
            $this->markFailed($document);
        }
    }

    private function markFailed(RpsDocument $document, ?string $errorCode = null): void
    {
        $document->update(['career_suggestions' => [
            'status' => 'failed',
            'items' => [],
            'error_code' => $errorCode,
        ]]);
    }
}
