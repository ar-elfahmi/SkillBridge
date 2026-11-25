<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Modules\Ai\Exceptions\GeminiQuotaExhaustedException;
use App\Modules\Ai\Exceptions\GeminiRateLimitedException;
use App\Modules\Ai\Services\GeminiQuota;
use App\Modules\Ai\Services\QuotaStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class GeminiQuotaTest extends TestCase
{
    private GeminiQuota $quota;

    private CarbonImmutable $base;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'skillbridge.gemini.limits.enabled' => true,
            'skillbridge.gemini.limits.redis_prefix' => 'test-quota:'.bin2hex(random_bytes(6)),
            'skillbridge.gemini.limits.reset_timezone' => 'America/Los_Angeles',
            'skillbridge.gemini.limits.flash.rpm' => 3,
            'skillbridge.gemini.limits.flash.rpd' => 5,
            'skillbridge.gemini.limits.embedding.rpm' => 2,
            'skillbridge.gemini.limits.embedding.rpd' => 4,
        ]);

        // 2026-11-26 10:00:00 UTC = 02:00 on 2026-11-26 in Pacific time.
        $this->base = CarbonImmutable::parse('2026-11-26 10:00:00', 'UTC');
        $this->quota = new GeminiQuota;
    }

    protected function tearDown(): void
    {
        foreach ([GeminiQuota::BUCKET_FLASH, GeminiQuota::BUCKET_EMBEDDING] as $bucket) {
            foreach ([$this->base, $this->base->addDay()] as $moment) {
                $this->quota->reset($bucket, $moment);
            }
        }

        parent::tearDown();
    }

    public function test_allows_requests_below_the_limits_and_counts_them(): void
    {
        $first = $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, $this->base);
        $second = $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, $this->base->addSecond());

        $this->assertTrue($first->isAllowed());
        $this->assertTrue($second->isAllowed());
        $this->assertSame(2, $second->usedToday);

        $snapshot = $this->quota->snapshot(GeminiQuota::BUCKET_FLASH, $this->base->addSeconds(2));
        $this->assertSame(2, $snapshot['rpm_used']);
        $this->assertSame(3, $snapshot['rpm_limit']);
        $this->assertSame(2, $snapshot['rpd_used']);
        $this->assertSame(3, $snapshot['rpd_remaining']);
    }

    public function test_waits_when_the_minute_window_is_full_and_reports_when_a_slot_frees(): void
    {
        foreach ([0, 10, 20] as $offset) {
            $this->assertTrue($this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, $this->base->addSeconds($offset))->isAllowed());
        }

        $decision = $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, $this->base->addSeconds(30));

        $this->assertSame(QuotaStatus::Wait, $decision->status);
        // The oldest request (t = 0) leaves the 60 s window at t = 60, so 30 s from now.
        $this->assertEqualsWithDelta(30.0, $decision->waitSeconds, 0.01);
        $this->assertSame(3, $this->quota->snapshot(GeminiQuota::BUCKET_FLASH, $this->base->addSeconds(30))['rpd_used']);
    }

    public function test_sliding_window_frees_slots_gradually(): void
    {
        foreach ([0, 10, 20] as $offset) {
            $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, $this->base->addSeconds($offset));
        }

        $this->assertSame(QuotaStatus::Wait, $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, $this->base->addSeconds(59))->status);
        $this->assertTrue($this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, $this->base->addSeconds(61))->isAllowed());
        // Requests at t = 10 and t = 20 are still inside the window; the one at t = 61 is the third.
        $this->assertSame(QuotaStatus::Wait, $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, $this->base->addSeconds(62))->status);
    }

    public function test_multi_request_reservation_is_all_or_nothing(): void
    {
        $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 2, $this->base);

        $denied = $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 2, $this->base->addSecond());

        $this->assertSame(QuotaStatus::Wait, $denied->status);
        $this->assertSame(2, $this->quota->snapshot(GeminiQuota::BUCKET_FLASH, $this->base->addSecond())['rpd_used']);
    }

    public function test_daily_budget_is_exhausted_and_reports_the_reset_time(): void
    {
        foreach ([0, 61, 122, 183, 244] as $offset) {
            $this->assertTrue($this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, $this->base->addSeconds($offset))->isAllowed());
        }

        $decision = $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, $this->base->addSeconds(305));

        $this->assertSame(QuotaStatus::DailyExhausted, $decision->status);
        // Midnight Pacific on 2026-11-27 (UTC-8 in November) = 08:00 UTC.
        $this->assertSame('2026-11-27T08:00:00+00:00', $decision->resetsAt?->toIso8601String());
        $this->assertSame(5, $decision->usedToday);
    }

    public function test_daily_counter_resets_at_pacific_midnight_not_utc_midnight(): void
    {
        foreach ([0, 61, 122, 183, 244] as $offset) {
            $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, $this->base->addSeconds($offset));
        }

        // 2026-11-27 00:30 UTC is still 2026-11-26 16:30 in Pacific time: same day, still exhausted.
        $stillSameDay = $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, CarbonImmutable::parse('2026-11-27 00:30:00', 'UTC'));
        $this->assertSame(QuotaStatus::DailyExhausted, $stillSameDay->status);

        // 2026-11-27 08:01 UTC is just after Pacific midnight: a fresh day.
        $nextDay = $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, CarbonImmutable::parse('2026-11-27 08:01:00', 'UTC'));
        $this->assertTrue($nextDay->isAllowed());
        $this->assertSame(1, $nextDay->usedToday);
    }

    public function test_buckets_are_counted_independently(): void
    {
        $this->quota->reserve(GeminiQuota::BUCKET_EMBEDDING, 2, $this->base);

        $this->assertSame(QuotaStatus::Wait, $this->quota->reserve(GeminiQuota::BUCKET_EMBEDDING, 1, $this->base->addSecond())->status);
        $this->assertTrue($this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, $this->base->addSecond())->isAllowed());
    }

    public function test_reserve_or_fail_throws_typed_exceptions(): void
    {
        foreach ([0, 1, 2] as $offset) {
            $this->quota->reserveOrFail(GeminiQuota::BUCKET_FLASH, 1, $this->base->addSeconds($offset));
        }

        try {
            $this->quota->reserveOrFail(GeminiQuota::BUCKET_FLASH, 1, $this->base->addSeconds(3));
            $this->fail('Expected a rate limit exception.');
        } catch (GeminiRateLimitedException $exception) {
            $this->assertSame(57, $exception->retryAfterSeconds);
        }

        foreach ([61, 62] as $offset) {
            $this->quota->reserveOrFail(GeminiQuota::BUCKET_FLASH, 1, $this->base->addSeconds($offset));
        }

        try {
            $this->quota->reserveOrFail(GeminiQuota::BUCKET_FLASH, 1, $this->base->addSeconds(125));
            $this->fail('Expected a quota exception.');
        } catch (GeminiQuotaExhaustedException $exception) {
            $this->assertSame('flash', $exception->bucket);
            $this->assertStringContainsString('Kuota AI harian habis', $exception->getMessage());
            // 08:00 UTC = 15.00 WIB.
            $this->assertStringContainsString('27 November 2026 pukul 15.00 WIB', $exception->getMessage());
        }
    }

    public function test_concurrent_looking_reservations_never_exceed_the_minute_budget(): void
    {
        $allowed = 0;
        for ($i = 0; $i < 10; $i++) {
            if ($this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, $this->base->addMilliseconds($i))->isAllowed()) {
                $allowed++;
            }
        }

        $this->assertSame(3, $allowed);
    }

    public function test_disabled_quota_always_allows_and_touches_nothing(): void
    {
        config(['skillbridge.gemini.limits.enabled' => false]);

        for ($i = 0; $i < 20; $i++) {
            $this->assertTrue($this->quota->reserve(GeminiQuota::BUCKET_FLASH, 1, $this->base)->isAllowed());
        }

        $this->assertSame(0, $this->quota->snapshot(GeminiQuota::BUCKET_FLASH, $this->base)['rpd_used']);
    }

    public function test_rejects_unknown_buckets_and_invalid_counts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->quota->reserve('unknown', 1, $this->base);
    }

    public function test_rejects_a_reservation_larger_than_the_minute_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 4, $this->base);
    }

    public function test_quota_command_prints_counters(): void
    {
        $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 2);

        $this->artisan('skillbridge:ai-quota')
            ->expectsTable(
                ['Bucket', 'Menit ini', 'Batas RPM', 'Hari ini', 'Batas RPD', 'Sisa RPD', 'Reset (WIB)'],
                [
                    ['flash', 2, 3, 2, 5, 3, $this->quota->resetsAt()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i')],
                    ['embedding', 0, 2, 0, 4, 4, $this->quota->resetsAt()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i')],
                ],
            )
            ->assertSuccessful();

        $this->quota->reset(GeminiQuota::BUCKET_FLASH);
    }
}
