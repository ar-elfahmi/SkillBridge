<?php

declare(strict_types=1);

namespace App\Modules\Industry\Services;

use App\Modules\Ai\Services\GeminiQuota;
use App\Modules\Industry\Jobs\ExtractJobSkillsGroupJob;
use App\Modules\Industry\Jobs\ExtractJobSkillsJob;
use Carbon\CarbonImmutable;

/**
 * Schedules bulk extraction of job postings without spending the whole daily Gemini budget.
 *
 * Each posting costs one flash request; each group of postings costs one embedding request.
 * Part of the flash budget stays reserved for requests triggered by users.
 */
class JobExtractionDispatcher
{
    public function __construct(private readonly GeminiQuota $quota) {}

    /**
     * @param  list<int>  $postingIds  ordered by priority; the tail is left out when the budget is short
     * @return array{requested: int, scheduled: int, postponed: int, budget: int|null, resets_at: CarbonImmutable}
     */
    public function dispatch(array $postingIds): array
    {
        $groupSize = max(1, (int) config('skillbridge.gemini.jobs.concurrent_postings', 2));
        $resetsAt = $this->quota->resetsAt();
        $budget = null;
        $allowed = count($postingIds);

        if ($this->quota->isEnabled()) {
            $reserved = max(0, (int) config('skillbridge.gemini.jobs.reserved_daily_requests', 100));
            $flash = max(0, $this->quota->remainingDaily(GeminiQuota::BUCKET_FLASH) - $reserved);
            $embedding = $this->quota->remainingDaily(GeminiQuota::BUCKET_EMBEDDING) * $groupSize;

            $budget = min($flash, $embedding);
            $allowed = min($allowed, $budget);
        }

        $scheduled = array_slice($postingIds, 0, $allowed);

        foreach (array_chunk($scheduled, $groupSize) as $group) {
            if (count($group) === 1) {
                ExtractJobSkillsJob::dispatch($group[0]);

                continue;
            }

            ExtractJobSkillsGroupJob::dispatch($group);
        }

        return [
            'requested' => count($postingIds),
            'scheduled' => count($scheduled),
            'postponed' => count($postingIds) - count($scheduled),
            'budget' => $budget,
            'resets_at' => $resetsAt,
        ];
    }
}
