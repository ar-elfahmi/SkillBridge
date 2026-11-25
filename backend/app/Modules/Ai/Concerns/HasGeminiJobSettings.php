<?php

declare(strict_types=1);

namespace App\Modules\Ai\Concerns;

use App\Modules\Ai\Exceptions\GeminiQuotaExhaustedException;
use App\Modules\Ai\Exceptions\GeminiRateLimitedException;
use DateTimeInterface;

/**
 * Shared queue settings for jobs that call Gemini.
 *
 * - Explicit timeout (must stay below REDIS_QUEUE_RETRY_AFTER so a running job is never picked up twice).
 * - retryUntil(): waiting for the per-minute budget releases the job; a time window bounds the retries,
 *   and maxExceptions only counts real failures. The legacy $tries value is ignored by the worker
 *   while retryUntil() is defined.
 * - failure messages for quota situations, reused by every job so the wording stays identical.
 *
 * Call configureGeminiJob() from the job constructor.
 */
trait HasGeminiJobSettings
{
    public int $timeout = 120;

    public int $maxExceptions = 3;

    protected function configureGeminiJob(bool $long = false): void
    {
        $key = $long ? 'long_timeout_seconds' : 'timeout_seconds';

        $this->timeout = max(30, (int) config("skillbridge.gemini.jobs.{$key}", $long ? 300 : 120));
        $this->maxExceptions = max(1, (int) config('skillbridge.gemini.jobs.max_exceptions', 3));
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(max(1, (int) config('skillbridge.gemini.jobs.retry_until_minutes', 15)));
    }

    /**
     * Lock lifetime for WithoutOverlapping: slightly longer than the job timeout so a killed worker
     * cannot leave the lock behind forever (the middleware default is "never expires").
     */
    protected function overlapLockSeconds(): int
    {
        return $this->timeout + 30;
    }

    /**
     * Put the job back on the queue after the per-minute budget frees up.
     */
    protected function releaseForRateLimit(GeminiRateLimitedException $exception): void
    {
        $this->release($exception->retryAfterSeconds);
    }

    /**
     * Safe message for the user when the daily budget is gone (Indonesian).
     */
    protected function quotaMessage(GeminiQuotaExhaustedException $exception): string
    {
        return $exception->getMessage();
    }
}
