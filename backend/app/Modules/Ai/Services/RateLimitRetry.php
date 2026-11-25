<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

use App\Modules\Ai\Exceptions\GeminiRateLimitedException;
use Illuminate\Support\Sleep;

/**
 * For jobs that call Gemini many times inside one unit of work (judge loop, recommendations):
 * releasing the whole job would redo everything, so wait for the per-minute budget and retry
 * the single call instead. Gives up (rethrows) after a bounded number of waits.
 */
final class RateLimitRetry
{
    /**
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     *
     * @throws GeminiRateLimitedException when the budget did not free up within the allowed waits
     */
    public static function run(callable $call): mixed
    {
        $waits = max(0, (int) config('skillbridge.gemini.jobs.max_rate_limit_waits', 3));

        for ($attempt = 0; ; $attempt++) {
            try {
                return $call();
            } catch (GeminiRateLimitedException $exception) {
                if ($attempt >= $waits) {
                    throw $exception;
                }

                Sleep::for($exception->retryAfterSeconds)->seconds();
            }
        }
    }
}
