<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Ai\Services\GeminiQuota;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class AiQuotaCommand extends Command
{
    protected $signature = 'skillbridge:ai-quota {--json : Print the snapshot as JSON}';

    protected $description = 'Show current Gemini request counters (per minute and per day) for the flash and embedding buckets.';

    public function handle(GeminiQuota $quota): int
    {
        $snapshots = [
            $quota->snapshot(GeminiQuota::BUCKET_FLASH),
            $quota->snapshot(GeminiQuota::BUCKET_EMBEDDING),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($snapshots, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if (! $quota->isEnabled()) {
            $this->warn('Penghitung kuota dinonaktifkan (SKILLBRIDGE_QUOTA_ENABLED=false). Angka di bawah selalu 0.');
        }

        $this->table(
            ['Bucket', 'Menit ini', 'Batas RPM', 'Hari ini', 'Batas RPD', 'Sisa RPD', 'Reset (WIB)'],
            array_map(static fn (array $row): array => [
                $row['bucket'],
                $row['rpm_used'],
                $row['rpm_limit'],
                $row['rpd_used'],
                $row['rpd_limit'],
                $row['rpd_remaining'],
                CarbonImmutable::parse($row['resets_at'])->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'),
            ], $snapshots),
        );

        return self::SUCCESS;
    }
}
