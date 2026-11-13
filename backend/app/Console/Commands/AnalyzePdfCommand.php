<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MatchClassification;
use App\Models\Career;
use App\Models\User;
use App\Modules\Rps\Services\RpsPipelineService;
use Illuminate\Console\Command;

class AnalyzePdfCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'skillbridge:analyze-pdf
                            {file : Path ke berkas RPS dalam format PDF}
                            {--career=data-analyst : Slug target karier (misal: data-analyst, backend-developer)}
                            {--user= : Email pengguna (opsional, default ke user pertama atau demo)}
                            {--limit=5 : Jumlah lowongan industri sampel yang dicocokkan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menjalankan alur evaluasi RPS secara menyeluruh (PDF -> Metadata -> CPMK -> Matching -> Rekomendasi)';

    /**
     * Execute the console command.
     */
    public function handle(RpsPipelineService $pipeline): int
    {
        if (empty(config('skillbridge.gemini.api_key'))) {
            config(['skillbridge.gemini.mode' => 'offline']);
        }

        $fileArg = (string) $this->argument('file');
        $resolvedPath = file_exists($fileArg)
            ? realpath($fileArg)
            : base_path($fileArg);

        if (! $resolvedPath || ! file_exists($resolvedPath)) {
            $this->error("Berkas PDF tidak ditemukan di path: {$fileArg}");

            return Command::FAILURE;
        }

        $careerSlug = (string) $this->option('career');
        $career = Career::where('slug', $careerSlug)->first();
        if (! $career) {
            $this->warn("Karier dengan slug '{$careerSlug}' tidak ditemukan. Menggunakan karier pertama.");
            $career = Career::first();
        }

        $userEmail = (string) $this->option('user');
        $user = $userEmail !== ''
            ? User::where('email', $userEmail)->first()
            : User::first();

        if (! $user) {
            $user = User::create([
                'name' => 'Demo Student',
                'email' => 'student.demo@skillbridge.local',
                'password' => bcrypt('password123'),
                'role' => 'student',
            ]);
        }

        $limit = max(1, (int) $this->option('limit'));

        $this->newLine();
        $this->info('======================================================================');
        $this->info('           SKILLBRIDGE: PIPELINE EVALUASI KURIKULUM RPS              ');
        $this->info('======================================================================');
        $this->line("Berkas PDF      : <comment>{$resolvedPath}</comment>");
        $this->line("Target Karier   : <info>{$career?->name}</info> ({$career?->slug})");
        $this->line("Pengguna        : <comment>{$user->name}</comment> ({$user->email})");
        $this->line("Sampel Lowongan : <comment>{$limit} lowongan</comment>");
        $this->newLine();

        $progressBar = null;

        try {
            $result = $pipeline->processFile(
                pdfPath: $resolvedPath,
                user: $user,
                career: $career,
                autoConfirm: true,
                jobPostingsLimit: $limit,
                onProgress: function (string $step, string $message, array $data = []): void {
                    $badge = match ($step) {
                        'parsing' => '1/5 PARSER',
                        'parsing_complete' => '1/5 SELESAI',
                        'metadata' => '2/5 METADATA',
                        'metadata_complete' => '2/5 SELESAI',
                        'competency_extraction' => '3/5 EKSTRAKSI',
                        'competency_complete' => '3/5 SELESAI',
                        'matching' => '4/5 MATCHING',
                        'matching_complete' => '4/5 SELESAI',
                        'recommendations' => '5/5 REKOMENDASI',
                        'recommendations_complete' => '5/5 SELESAI',
                        default => 'INFO',
                    };
                    $this->line(sprintf(' <fg=cyan>[%s]</> %s', str_pad($badge, 12), $message));
                }
            );

            $this->newLine();
            $this->info('----------------------------------------------------------------------');
            $this->info('1. METADATA MATA KULIAH:');
            $this->table(
                ['Parameter', 'Nilai'],
                [
                    ['Kode Mata Kuliah', $result->course->code],
                    ['Nama Mata Kuliah', $result->course->name],
                    ['Bobot SKS', "{$result->course->credits} SKS"],
                    ['Semester', (string) $result->course->semester],
                    ['Program Studi', $result->course->study_program ?? '-'],
                    ['Jumlah Halaman', "{$result->document->page_count} halaman"],
                ]
            );

            $this->info('2. KOMPETENSI RPS DENGAN VERIFIKASI BUKTI TEKS:');
            $compRows = [];
            foreach ($result->courseSkills as $idx => $cs) {
                $categoryStr = $cs->category instanceof \BackedEnum
                    ? (string) $cs->category->value
                    : (string) $cs->category;

                $compRows[] = [
                    $idx + 1,
                    $cs->skillEntry?->canonical_name ?? $cs->skill,
                    $categoryStr,
                    "Hal. {$cs->page}",
                    $cs->verification_status?->value === 'verified' ? '✅ Terverifikasi' : '⚠️ Belum',
                    mb_strimwidth($cs->evidence ?? '-', 0, 35, '..'),
                ];
            }
            $this->table(['No', 'Skill Kanonik', 'Kategori', 'Halaman', 'Verifikasi', 'Kutipan Bukti'], $compRows);

            if ($result->analysis !== null && $result->skillMatches->isNotEmpty()) {
                $this->info("3. HASIL PENILAIAN KESELARASAN TARGET: {$career?->name}");
                $matchRows = [];
                foreach ($result->skillMatches as $sm) {
                    $statusBadge = match ($sm->classification) {
                        MatchClassification::Covered => '<fg=green;options=bold>🟢 COVERED</>',
                        MatchClassification::Partial => '<fg=yellow;options=bold>🟡 PARTIAL</>',
                        MatchClassification::Missing => '<fg=red;options=bold>🔴 MISSING</>',
                    };
                    $simScore = $sm->similarity_score !== null ? number_format((float) $sm->similarity_score, 3) : '-';
                    $matchRows[] = [
                        $sm->skill?->canonical_name ?? '-',
                        "{$sm->demand_count}/{$limit}",
                        $simScore,
                        $statusBadge,
                        mb_strimwidth($sm->reason ?? '-', 0, 42, '..'),
                    ];
                }
                $this->table(['Skill Industri', 'Kebutuhan', 'Cosine Sim', 'Status', 'Alasan Sistem'], $matchRows);

                $cov = $result->summary['coverage_percentage'] ?? 0;
                $covColor = $cov >= 75 ? 'green' : ($cov >= 40 ? 'yellow' : 'red');
                $this->line(sprintf(
                    ' <options=bold>Ringkasan:</> Total: %d | Covered: %d | Partial: %d | Missing: %d | <fg=%s;options=bold>Cakupan Kurikulum: %.1f%%</>',
                    $result->summary['total_skills'] ?? 0,
                    $result->summary['covered'] ?? 0,
                    $result->summary['partial'] ?? 0,
                    $result->summary['missing'] ?? 0,
                    $covColor,
                    $cov
                ));
            }

            if ($result->recommendations->isNotEmpty()) {
                $this->newLine();
                $this->info('4. REKOMENDASI PEMBELAJARAN PENGEMBANGAN DIRI:');
                $recoRows = [];
                foreach ($result->recommendations as $idx => $r) {
                    $details = is_array($r->details) ? $r->details : [];
                    $topics = $details['topics'] ?? [];
                    $topicsStr = implode(', ', array_slice($topics, 0, 2));
                    $skillName = $r->skillMatch?->skill?->canonical_name ?? str_replace('Pengembangan Mandiri: ', '', $r->title);
                    $prioBadge = match ($r->priority->value) {
                        'high' => '<fg=red;options=bold>🔴 TINGGI</>',
                        'medium' => '<fg=yellow;options=bold>🟡 SEDANG</>',
                        'low' => '<fg=cyan>🔵 RENDAH</>',
                        default => strtoupper($r->priority->value),
                    };
                    $recoRows[] = [
                        $idx + 1,
                        $prioBadge,
                        $skillName,
                        mb_strimwidth($r->reason ?? '-', 0, 38, '..'),
                        mb_strimwidth($topicsStr, 0, 38, '..'),
                    ];
                }
                $this->table(['No', 'Prioritas', 'Skill Gap', 'Alasan', 'Topik Fokus'], $recoRows);
            }

            $this->newLine();
            $this->info('✅ Pipeline selesai dijalankan dengan sukses tanpa hambatan!');
            $this->line("Dokumen ID: <comment>{$result->document->id}</comment> | Analisis ID: <comment>{$result->analysis?->id}</comment>");
            $this->newLine();

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("Terjadi kesalahan pada eksekusi pipeline: {$e->getMessage()}");
            $this->line($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
