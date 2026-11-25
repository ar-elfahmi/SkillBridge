<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Career;
use App\Models\JobPosting;
use App\Modules\Industry\Services\JobExtractionDispatcher;
use App\Modules\Rps\Enums\ExtractionStatus;
use Illuminate\Console\Command;

class ExtractPendingJobsCommand extends Command
{
    protected $signature = 'skillbridge:extract-pending-jobs
        {--limit=25 : Maksimal jumlah lowongan yang dijadwalkan dalam satu eksekusi}
        {--career= : Filter berdasarkan slug profesi (opsional)}
        {--retry-failed : Sertakan juga lowongan berstatus failed}';

    protected $description = 'Menjadwalkan ExtractJobSkillsJob secara bertahap untuk lowongan berstatus pending';

    public function handle(JobExtractionDispatcher $dispatcher): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $careerSlug = $this->option('career');
        $retryFailed = (bool) $this->option('retry-failed');

        $statuses = [ExtractionStatus::Pending->value];
        if ($retryFailed) {
            $statuses[] = ExtractionStatus::Failed->value;
        }

        $query = JobPosting::query()
            ->whereIn('extraction_status', $statuses)
            ->orderBy('id');

        if (is_string($careerSlug) && trim($careerSlug) !== '') {
            $career = Career::query()->where('slug', trim($careerSlug))->first();
            if ($career === null) {
                $this->error("Profesi dengan slug '{$careerSlug}' tidak ditemukan.");

                return self::FAILURE;
            }
            $query->where('career_id', $career->id);
        }

        $postings = $query->limit($limit)->get(['id', 'title', 'company']);

        if ($postings->isEmpty()) {
            $this->info('Tidak ada lowongan pending yang perlu diekstrak.');

            return self::SUCCESS;
        }

        $result = $dispatcher->dispatch($postings->pluck('id')->map(fn ($id): int => (int) $id)->all());

        $this->info("Dijadwalkan ekstraksi skill untuk {$result['scheduled']} lowongan.");

        if ($result['postponed'] > 0) {
            $this->warn("{$result['postponed']} lowongan ditunda karena anggaran kuota AI harian (sisa anggaran: {$result['budget']} permintaan, termasuk cadangan untuk pengguna). Jalankan lagi setelah reset pada ".$result['resets_at']->setTimezone('Asia/Jakarta')->format('Y-m-d H:i').' WIB.');
        }

        return self::SUCCESS;
    }
}
