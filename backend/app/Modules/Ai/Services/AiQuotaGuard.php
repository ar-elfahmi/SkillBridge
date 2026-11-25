<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

use App\Modules\Ai\Exceptions\GeminiQuotaExhaustedException;
use Illuminate\Http\JsonResponse;

/**
 * Early answer for endpoints that start AI work: when the daily Gemini budget is already gone,
 * say so up front (429 with the reset time) instead of accepting a job that is certain to fail.
 *
 * Only applies when requests really go to Gemini ("gemini" mode). The "offline" and
 * "fallback"/"auto" modes can still produce a result locally, so they are never blocked here.
 */
final class AiQuotaGuard
{
    public function __construct(private readonly GeminiQuota $quota) {}

    public function denial(): ?JsonResponse
    {
        if (! $this->enforced() || $this->quota->remainingDaily(GeminiQuota::BUCKET_FLASH) > 0) {
            return null;
        }

        $resetsAt = $this->quota->resetsAt();

        return response()->json([
            'message' => GeminiQuotaExhaustedException::messageFor($resetsAt),
            'code' => GeminiQuotaExhaustedException::CODE,
            'resets_at' => $resetsAt->toIso8601String(),
        ], 429);
    }

    /**
     * Public view of the daily budget (no per-minute counters, no provider limits).
     *
     * @return array{available: bool, daily_remaining: int, resets_at: string}
     */
    public function status(): array
    {
        $snapshot = $this->quota->snapshot(GeminiQuota::BUCKET_FLASH);

        return [
            'available' => $snapshot['rpd_remaining'] > 0 || ! $this->enforced(),
            'daily_remaining' => $snapshot['rpd_remaining'],
            'resets_at' => $snapshot['resets_at'],
        ];
    }

    private function enforced(): bool
    {
        return $this->quota->isEnabled()
            && strtolower(trim((string) config('skillbridge.gemini.mode', 'gemini'))) === 'gemini';
    }
}
