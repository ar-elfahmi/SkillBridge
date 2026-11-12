<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Career;
use App\Models\JobPosting;
use App\Modules\Industry\Jobs\ExtractJobSkillsJob;
use App\Modules\Rps\Enums\ExtractionStatus;
use Illuminate\Console\Command;

class ExtractPendingJobsCommand extends Command
{
    protected $signature = 'skillbridge:extract-pending-jobs
        {--limit=25 : Maksimal jumlah lowongan yang dijadwalkan dalam satu eksekusi}
        {--career= : Filter berdasarkan slug profesi (opsional)}
        {--retry-failed : Sertakan juga lowongan berstatus failed}';

    protected $description = 'Menjadwalkan ExtractJobSkillsJob secara bertahap untuk lowongan berstatus pending';

    public function handle(): int
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

        foreach ($postings as $posting) {
            ExtractJobSkillsJob::dispatch((int) $posting->id);
        }

        $this->info("Dijadwalkan ekstraksi skill untuk {$postings->count()} lowongan.");

        return self::SUCCESS;
    }
}
