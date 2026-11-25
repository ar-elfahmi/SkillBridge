<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

use App\Modules\Ai\Exceptions\GeminiException;
use App\Modules\Ai\Exceptions\GeminiQuotaExhaustedException;
use App\Modules\Ai\Exceptions\GeminiRateLimitedException;
use Illuminate\Http\Client\Response;

/**
 * Turns a provider 429 into the right typed exception: daily budget gone, or "wait N seconds".
 * Only quota identifiers and delays are read from the body; the body itself is never kept.
 */
final class RateLimitExceptionFactory
{
    /** Wait suggested to the caller when the provider answers 429 without a usable delay. */
    private const DEFAULT_RETRY_AFTER_SECONDS = 60;

    public static function fromResponse(Response $response, GeminiQuota $quota, string $bucket): GeminiException
    {
        $retryAfter = null;
        $daily = false;

        $header = trim($response->header('Retry-After'));
        if ($header !== '') {
            if (is_numeric($header)) {
                $retryAfter = (int) ceil((float) $header);
            } elseif (($timestamp = strtotime($header)) !== false) {
                $retryAfter = max(0, $timestamp - time());
            }
        }

        $body = $response->json();
        $details = is_array($body) ? ($body['error']['details'] ?? []) : [];

        foreach (is_array($details) ? $details : [] as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            if ($retryAfter === null && is_string($detail['retryDelay'] ?? null)
                && preg_match('/\A(\d+(?:\.\d+)?)s\z/', $detail['retryDelay'], $matches)) {
                $retryAfter = (int) ceil((float) $matches[1]);
            }

            foreach (is_array($detail['violations'] ?? null) ? $detail['violations'] : [] as $violation) {
                if (is_array($violation) && is_string($violation['quotaId'] ?? null) && stripos($violation['quotaId'], 'PerDay') !== false) {
                    $daily = true;
                }
            }
        }

        if ($daily) {
            return new GeminiQuotaExhaustedException($quota->resetsAt(), $bucket);
        }

        return new GeminiRateLimitedException(max(1, $retryAfter ?? self::DEFAULT_RETRY_AFTER_SECONDS));
    }
}
