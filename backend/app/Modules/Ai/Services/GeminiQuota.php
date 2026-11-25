<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

use App\Modules\Ai\Exceptions\GeminiQuotaExhaustedException;
use App\Modules\Ai\Exceptions\GeminiRateLimitedException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;

/**
 * Shared request counters for Gemini, kept in Redis so every worker sees the same state.
 *
 * Two budgets per bucket ("flash" or "embedding"):
 * - RPM: sliding window of the last 60 seconds (sorted set of request timestamps).
 * - RPD: counter per calendar day in the provider reset timezone (Pacific time by default).
 *
 * A reservation is atomic (single Lua script), so concurrent workers can never exceed a budget.
 * Limits come from config('skillbridge.gemini.limits.*'); this class never hardcodes them.
 */
final class GeminiQuota
{
    public const BUCKET_FLASH = 'flash';

    public const BUCKET_EMBEDDING = 'embedding';

    private const WINDOW_MS = 60_000;

    private const RESERVE_SCRIPT = <<<'LUA'
local now = tonumber(ARGV[1])
local window = tonumber(ARGV[2])
local rpm = tonumber(ARGV[3])
local rpd = tonumber(ARGV[4])
local count = tonumber(ARGV[5])
local ttl = tonumber(ARGV[6])
local uid = ARGV[7]

redis.call('ZREMRANGEBYSCORE', KEYS[1], '-inf', now - window)
local usedDay = tonumber(redis.call('GET', KEYS[2]) or '0')
if usedDay + count > rpd then
  return {2, 0, usedDay}
end

local usedMinute = redis.call('ZCARD', KEYS[1])
if usedMinute + count > rpm then
  local need = usedMinute + count - rpm
  local entry = redis.call('ZRANGE', KEYS[1], need - 1, need - 1, 'WITHSCORES')
  local waitMs = tonumber(entry[2]) + window - now
  if waitMs < 1 then waitMs = 1 end
  return {1, waitMs, usedDay}
end

for i = 1, count do
  redis.call('ZADD', KEYS[1], now, uid .. ':' .. i)
end
redis.call('PEXPIRE', KEYS[1], window)
redis.call('INCRBY', KEYS[2], count)
redis.call('EXPIRE', KEYS[2], ttl)
return {0, 0, usedDay + count}
LUA;

    private const SNAPSHOT_SCRIPT = <<<'LUA'
local now = tonumber(ARGV[1])
local window = tonumber(ARGV[2])
redis.call('ZREMRANGEBYSCORE', KEYS[1], '-inf', now - window)
return {redis.call('ZCARD', KEYS[1]), tonumber(redis.call('GET', KEYS[2]) or '0')}
LUA;

    public function isEnabled(): bool
    {
        return (bool) config('skillbridge.gemini.limits.enabled', true);
    }

    /**
     * Try to reserve $count requests. Nothing is counted unless the whole reservation is allowed.
     */
    public function reserve(string $bucket, int $count = 1, ?CarbonInterface $now = null): QuotaDecision
    {
        [$rpm, $rpd] = $this->limitsFor($bucket);

        if ($count < 1 || $count > $rpm) {
            throw new InvalidArgumentException('Jumlah reservasi kuota AI tidak valid.');
        }

        $moment = $this->moment($now);

        if (! $this->isEnabled()) {
            return QuotaDecision::allowed(0);
        }

        [$rpmKey, $rpdKey, $resetsAt] = $this->keys($bucket, $moment);
        $ttl = max(60, $resetsAt->getTimestamp() - $moment->getTimestamp() + 3600);

        /** @var array{0: int|string, 1: int|string, 2: int|string} $result */
        $result = Redis::connection()->eval(
            self::RESERVE_SCRIPT,
            2,
            $rpmKey,
            $rpdKey,
            $this->nowMs($moment),
            self::WINDOW_MS,
            $rpm,
            $rpd,
            $count,
            $ttl,
            bin2hex(random_bytes(6)),
        );

        $status = (int) $result[0];
        $usedToday = (int) $result[2];

        return match ($status) {
            0 => QuotaDecision::allowed($usedToday),
            1 => QuotaDecision::wait(((int) $result[1]) / 1000, $usedToday),
            default => QuotaDecision::dailyExhausted($resetsAt, $usedToday),
        };
    }

    /**
     * Reserve or throw the typed exception the callers (jobs) know how to handle.
     *
     * @throws GeminiRateLimitedException
     * @throws GeminiQuotaExhaustedException
     */
    public function reserveOrFail(string $bucket, int $count = 1, ?CarbonInterface $now = null): void
    {
        $decision = $this->reserve($bucket, $count, $now);

        if ($decision->isAllowed()) {
            return;
        }

        if ($decision->status === QuotaStatus::DailyExhausted && $decision->resetsAt !== null) {
            throw new GeminiQuotaExhaustedException($decision->resetsAt, $bucket);
        }

        throw new GeminiRateLimitedException(max(1, (int) ceil($decision->waitSeconds)));
    }

    /**
     * Read-only view of the counters.
     *
     * @return array{bucket: string, enabled: bool, rpm_limit: int, rpm_used: int, rpd_limit: int, rpd_used: int, rpd_remaining: int, resets_at: string}
     */
    public function snapshot(string $bucket, ?CarbonInterface $now = null): array
    {
        [$rpm, $rpd] = $this->limitsFor($bucket);
        $moment = $this->moment($now);
        [$rpmKey, $rpdKey, $resetsAt] = $this->keys($bucket, $moment);

        $rpmUsed = 0;
        $rpdUsed = 0;

        if ($this->isEnabled()) {
            /** @var array{0: int|string, 1: int|string} $result */
            $result = Redis::connection()->eval(self::SNAPSHOT_SCRIPT, 2, $rpmKey, $rpdKey, $this->nowMs($moment), self::WINDOW_MS);
            $rpmUsed = (int) $result[0];
            $rpdUsed = (int) $result[1];
        }

        return [
            'bucket' => $bucket,
            'enabled' => $this->isEnabled(),
            'rpm_limit' => $rpm,
            'rpm_used' => $rpmUsed,
            'rpd_limit' => $rpd,
            'rpd_used' => $rpdUsed,
            'rpd_remaining' => max(0, $rpd - $rpdUsed),
            'resets_at' => $resetsAt->toIso8601String(),
        ];
    }

    public function remainingDaily(string $bucket, ?CarbonInterface $now = null): int
    {
        return $this->snapshot($bucket, $now)['rpd_remaining'];
    }

    /**
     * When the daily budget of the bucket resets (UTC instant).
     */
    public function resetsAt(?CarbonInterface $now = null): CarbonImmutable
    {
        return $this->keys(self::BUCKET_FLASH, $this->moment($now))[2];
    }

    /**
     * Forget all counters of a bucket (tests and operations).
     */
    public function reset(string $bucket, ?CarbonInterface $now = null): void
    {
        $this->limitsFor($bucket);
        [$rpmKey, $rpdKey] = $this->keys($bucket, $this->moment($now));

        Redis::connection()->del($rpmKey, $rpdKey);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function limitsFor(string $bucket): array
    {
        if (! in_array($bucket, [self::BUCKET_FLASH, self::BUCKET_EMBEDDING], true)) {
            throw new InvalidArgumentException('Bucket kuota AI tidak dikenal.');
        }

        $rpm = (int) config("skillbridge.gemini.limits.{$bucket}.rpm", 0);
        $rpd = (int) config("skillbridge.gemini.limits.{$bucket}.rpd", 0);

        if ($rpm < 1 || $rpd < 1) {
            throw new InvalidArgumentException('Konfigurasi batas kuota AI tidak valid.');
        }

        return [$rpm, $rpd];
    }

    /**
     * @return array{0: string, 1: string, 2: CarbonImmutable}
     */
    private function keys(string $bucket, CarbonImmutable $moment): array
    {
        $timezone = (string) config('skillbridge.gemini.limits.reset_timezone', 'America/Los_Angeles');
        $local = $moment->setTimezone($timezone);
        $prefix = (string) config('skillbridge.gemini.limits.redis_prefix', 'skillbridge:ai-quota');

        return [
            "{$prefix}:{$bucket}:rpm",
            "{$prefix}:{$bucket}:rpd:{$local->format('Y-m-d')}",
            $local->addDay()->startOfDay()->setTimezone('UTC'),
        ];
    }

    private function moment(?CarbonInterface $now): CarbonImmutable
    {
        return CarbonImmutable::instance($now ?? now());
    }

    private function nowMs(CarbonImmutable $moment): int
    {
        return (int) $moment->getPreciseTimestamp(3);
    }
}
