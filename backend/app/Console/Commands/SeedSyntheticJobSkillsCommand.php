<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Modules\Ai\OfflineAiEngine;
use App\Modules\Competency\Services\SkillNormalizer;
use App\Modules\Rps\Enums\ExtractionStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedSyntheticJobSkillsCommand extends Command
{
    protected $signature = 'skillbridge:seed-synthetic-skills
        {--force : Jalankan ulang dan timpa job_skills yang sudah ada untuk lowongan sintetis}';

    protected $description = 'Mengekstrak bullet list qualifications dari 651 lowongan sintetis menjadi relasi job_skills beserta embedding lokal';

    public function handle(SkillNormalizer $normalizer, OfflineAiEngine $aiEngine): int
    {
        $force = (bool) $this->option('force');

        $query = JobPosting::query()
            ->where('dataset_version', 'synthetic-2026-11-11')
            ->whereNotNull('qualifications')
            ->where('qualifications', '!=', '');

        if (! $force) {
            $query->where('extraction_status', ExtractionStatus::Pending);
        }

        $postings = $query->get();

        if ($postings->isEmpty()) {
            $this->info('Tidak ada lowongan sintetis yang perlu diekstrak skill-nya.');

            return self::SUCCESS;
        }

        $this->info("Memproses ekstraksi bullet list qualifications untuk {$postings->count()} lowongan sintetis...");

        $model = (string) config('skillbridge.gemini.embedding_model', 'gemini-embedding-001');
        $dimensions = (int) config('skillbridge.gemini.embedding_dimensions', 768);

        $totalSkillsAdded = 0;
        $bar = $this->output->createProgressBar($postings->count());
        $bar->start();

        foreach ($postings as $posting) {
            $lines = preg_split('/\r\n|\r|\n/', (string) $posting->qualifications);
            $skillsToAdd = [];

            foreach ($lines as $line) {
                $cleaned = trim((string) preg_replace('/^[-*•\d.)\s]+/', '', $line));
                if ($cleaned === '' || mb_strlen($cleaned) > 255) {
                    continue;
                }

                $skillModel = $normalizer->normalize($cleaned);
                if ($skillModel === null) {
                    continue;
                }

                $embedText = "{$skillModel->canonical_name}: {$cleaned}";
                $textHash = hash('sha256', $embedText);

                $skillsToAdd[] = [
                    'job_posting_id' => $posting->id,
                    'skill_id' => $skillModel->id,
                    'skill' => $cleaned,
                    'requirement_text' => "Kualifikasi yang dibutuhkan: {$cleaned}",
                    'evidence' => $cleaned,
                    'embedding' => json_encode($aiEngine->embedText($embedText, $dimensions)),
                    'embedding_model' => $model,
                    'embedding_dimensions' => $dimensions,
                    'text_hash' => $textHash,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::transaction(function () use ($posting, $skillsToAdd, &$totalSkillsAdded): void {
                if ($skillsToAdd !== []) {
                    foreach ($skillsToAdd as $item) {
                        JobSkill::query()->updateOrCreate(
                            [
                                'job_posting_id' => $item['job_posting_id'],
                                'skill_id' => $item['skill_id'],
                            ],
                            $item
                        );
                        $totalSkillsAdded++;
                    }
                }

                $posting->update([
                    'extraction_status' => ExtractionStatus::Completed,
                ]);
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Selesai! Total {$totalSkillsAdded} job_skills berhasil diekstrak dan di-embedding.");

        return self::SUCCESS;
    }
}
