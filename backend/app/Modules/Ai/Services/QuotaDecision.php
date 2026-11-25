<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

use Carbon\CarbonImmutable;

final readonly class QuotaDecision
{
    private function __construct(
        public QuotaStatus $status,
        public float $waitSeconds,
        public ?CarbonImmutable $resetsAt,
        public int $usedToday,
    ) {}

    public static function allowed(int $usedToday): self
    {
        return new self(QuotaStatus::Allowed, 0.0, null, $usedToday);
    }

    public static function wait(float $seconds, int $usedToday): self
    {
        return new self(QuotaStatus::Wait, $seconds, null, $usedToday);
    }

    public static function dailyExhausted(CarbonImmutable $resetsAt, int $usedToday): self
    {
        return new self(QuotaStatus::DailyExhausted, 0.0, $resetsAt, $usedToday);
    }

    public function isAllowed(): bool
    {
        return $this->status === QuotaStatus::Allowed;
    }
}
