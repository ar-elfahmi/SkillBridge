<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AnalysisStatus;
use App\Enums\CompetencyOrigin;
use App\Enums\CompetencyReviewStatus;
use App\Enums\CompetencySourceType;
use App\Enums\MatchClassification;
use App\Enums\RecommendationPriority;
use App\Enums\SkillCategory;
use App\Enums\VerificationStatus;
use App\Models\Analysis;
use App\Models\Career;
use App\Models\Course;
use App\Models\CourseSkill;
use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Models\Recommendation;
use App\Models\RpsDocument;
use App\Models\RpsPage;
use App\Models\Skill;
use App\Models\SkillMatch;
use App\Models\User;
use App\Modules\Ai\EmbeddingService;
use App\Modules\Competency\Services\EvidenceVerifier;
use App\Modules\Competency\Services\SkillNormalizer;
use App\Modules\Matching\Services\CandidateFinder;
use App\Modules\Matching\Services\MatchJudge;
use App\Modules\Recommendation\Services\PriorityCalculator;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ParseStatus;
use App\Modules\Rps\Enums\ReviewStatus;
use App\Support\Cosine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulateWorkflowCommand extends Command
{
    protected $signature = 'skillbridge:simulate {--clean : Hapus data simulasi sebelumnya sebelum menjalankan}';

    protected $description = 'Simulasikan alur kerja end-to-end SkillBridge di sisi backend (Upload RPS -> Ekstraksi -> Matching -> Rekomendasi)';

    public function handle(
        EvidenceVerifier $evidenceVerifier,
        SkillNormalizer $skillNormalizer,
        EmbeddingService $embeddingService,
        CandidateFinder $candidateFinder,
        MatchJudge $matchJudge,
        PriorityCalculator $priorityCalculator,
    ): int {
        // Gunakan mode AI offline agar simulasi berjalan 100% lokal tanpa memerlukan koneksi atau API Key eksternal
        config(['skillbridge.gemini.mode' => 'offline']);
        $this->info('================================================================');
        $this->info('  🚀 SIMULASI ALUR KERJA END-TO-END SKILLBRIDGE (BACKEND ONLY)');
        $this->info('================================================================');
        $this->line('');

        // -------------------------------------------------------------
        // 1. User Mahasiswa & Profesi Target
        // -------------------------------------------------------------
        $this->info('📌 TAHAP 1: KONTEKS MAHASISWA & TARGET KARIR');
        $this->line('----------------------------------------------------------------');

        $user = User::firstOrCreate(
            ['email' => 'budi.santoso@mahasiswa.ac.id'],
            [
                'name' => 'Budi Santoso',
                'password' => bcrypt('password123'),
                'role' => 'student',
            ]
        );
        $this->line("👤 Pengguna : <comment>{$user->name}</comment> ({$user->email})");

        $career = Career::where('slug', 'backend-developer')->first();
        if ($career === null) {
            $career = Career::create([
                'name' => 'Backend Developer',
                'slug' => 'backend-developer',
                'description' => 'Membangun dan memelihara logika sisi server, API, dan basis data aplikasi.',
            ]);
        }
        $this->line("🎯 Target Karir : <comment>{$career->name}</comment> ({$career->slug})");
        $this->line('');

        // -------------------------------------------------------------
        // 2. Upload Dokumen RPS & Ekstraksi Metadata
        // -------------------------------------------------------------
        $this->info('📌 TAHAP 2: UPLOAD DOKUMEN RPS & METADATA MATA KULIAH');
        $this->line('----------------------------------------------------------------');

        $courseCode = 'IF-3104';
        $course = Course::firstOrCreate(
            ['code' => $courseCode],
            [
                'name' => 'Pemrograman Berbasis Web & Framework',
                'credits' => 3,
                'semester' => 5,
                'study_program' => 'S1 Teknik Informatika',
            ]
        );

        // Bersihkan jika ada RPS sebelumnya dari user ini untuk course ini jika requested
        if ($this->option('clean')) {
            $oldDocs = RpsDocument::where('user_id', $user->id)->get();
            foreach ($oldDocs as $oldDoc) {
                $oldDoc->analyses()->delete();
                $oldDoc->delete();
            }
        }

        $rps = RpsDocument::firstOrCreate(
            [
                'user_id' => $user->id,
                'original_name' => 'RPS_Pemrograman_Web_Framework_2026.pdf',
            ],
            [
                'course_id' => $course->id,
                'file_path' => 'rps/mock-rps-web.pdf',
                'file_hash' => hash('sha256', 'mock-content-rps-web'),
                'file_size' => 1024 * 350,
                'page_count' => 2,
                'parse_status' => ParseStatus::Parsed,
                'extraction_status' => ExtractionStatus::Completed,
                'review_status' => ReviewStatus::Confirmed,
                'reviewed_at' => now(),
                'detected_metadata' => [
                    'code' => $course->code,
                    'name' => $course->name,
                    'credits' => $course->credits,
                    'semester' => $course->semester,
                    'study_program' => $course->study_program,
                ],
                'metadata_confirmed_at' => now(),
            ]
        );

        // Halaman Teks RPS (Digunakan oleh EvidenceVerifier untuk anti-halusinasi)
        $page1Text = "UNIVERSITAS AIRLANGGA - FAKULTAS TEKNOLOGI MAJU DAN MULTIDISIPLIN\n"
            ."Mata Kuliah: Pemrograman Berbasis Web & Framework (IF-3104), 3 SKS, Semester 5\n"
            ."CPL-03: Mampu merancang perangkat lunak aplikasi menggunakan framework industri modern.\n"
            ."CPMK-1: Mahasiswa mampu merancang dan mengimplementasikan RESTful API menggunakan PHP Laravel Framework dengan arsitektur MVC.\n"
            ."CPMK-2: Mahasiswa mampu menggunakan Git version control untuk manajemen kode dan kolaborasi tim repository Git.\n";

        $page2Text = "Rencana Pembelajaran Mingguan:\n"
            ."Minggu 3-4: Relational Database Management System dengan PostgreSQL, migrasi database, dan Eloquent ORM.\n"
            ."Minggu 8: Implementasi autentikasi API berbasis token Sanctum dan pengujian unit test PHPUnit.\n"
            ."Minggu 12: Pengantar Docker containerization untuk standarisasi environment web service backend.\n";

        RpsPage::updateOrCreate(
            ['rps_document_id' => $rps->id, 'page_number' => 1],
            ['text' => $page1Text]
        );
        RpsPage::updateOrCreate(
            ['rps_document_id' => $rps->id, 'page_number' => 2],
            ['text' => $page2Text]
        );

        $pagesMap = [
            1 => $page1Text,
            2 => $page2Text,
        ];

        $this->line("📄 Dokumen : <comment>{$rps->original_name}</comment> (ID: {$rps->id})");
        $this->line("📚 Mata Kuliah : <info>{$course->code} - {$course->name}</info> ({$course->credits} SKS, Semester {$course->semester})");
        $this->line("✅ Status Dokumen : Parse: <info>{$rps->parse_status->value}</info> | Ekstraksi: <info>{$rps->extraction_status->value}</info> | Review: <info>{$rps->review_status->value}</info>");
        $this->line('');

        // -------------------------------------------------------------
        // 3. Ekstraksi Kompetensi & Verifikasi Bukti
        // -------------------------------------------------------------
        $this->info('📌 TAHAP 3: EKSTRAKSI KOMPETENSI RPS & VERIFIKASI BUKTI TEKS');
        $this->line('----------------------------------------------------------------');

        $rawCompetencies = [
            [
                'raw_skill' => 'Laravel',
                'competency' => 'Merancang dan mengimplementasikan RESTful API dengan framework PHP Laravel',
                'evidence' => 'Mahasiswa mampu merancang dan mengimplementasikan RESTful API menggunakan PHP Laravel Framework dengan arsitektur MVC.',
                'page' => 1,
                'source_type' => CompetencySourceType::Cpmk,
            ],
            [
                'raw_skill' => 'Git Version Control',
                'competency' => 'Menggunakan Git version control untuk manajemen kode dan kolaborasi repository',
                'evidence' => 'Mahasiswa mampu menggunakan Git version control untuk manajemen kode dan kolaborasi tim repository Git.',
                'page' => 1,
                'source_type' => CompetencySourceType::Cpmk,
            ],
            [
                'raw_skill' => 'PostgreSQL',
                'competency' => 'Pengelolaan basis data relasional PostgreSQL, migrasi database dan Eloquent ORM',
                'evidence' => 'Relational Database Management System dengan PostgreSQL, migrasi database, dan Eloquent ORM.',
                'page' => 2,
                'source_type' => CompetencySourceType::WeeklyPlan,
            ],
            [
                'raw_skill' => 'REST API',
                'competency' => 'Perancangan dan implementasi RESTful API untuk komunikasi client-server',
                'evidence' => 'Mahasiswa mampu merancang dan mengimplementasikan RESTful API menggunakan PHP Laravel Framework dengan arsitektur MVC.',
                'page' => 1,
                'source_type' => CompetencySourceType::Cpmk,
            ],
            [
                'raw_skill' => 'Docker',
                'competency' => 'Containerization layanan backend menggunakan Docker',
                'evidence' => 'Pengantar Docker containerization untuk standarisasi environment web service backend.',
                'page' => 2,
                'source_type' => CompetencySourceType::WeeklyPlan,
            ],
        ];

        $extractedCourseSkills = [];
        $competencyRows = [];

        foreach ($rawCompetencies as $item) {
            // Normalisasi nama skill ke kamus kanonik
            $skillModel = $skillNormalizer->normalize($item['raw_skill']);

            // Verifikasi bukti terhadap halaman teks RPS
            $verifResult = $evidenceVerifier->verifyPages(
                $item['evidence'],
                $item['page'],
                $pagesMap
            );

            // Hitung embedding semantik L2
            $embedText = "{$skillModel->canonical_name}: {$item['competency']}";
            $embedding = $embeddingService->embed($embedText);

            $courseSkill = CourseSkill::updateOrCreate(
                [
                    'rps_document_id' => $rps->id,
                    'skill_id' => $skillModel->id,
                ],
                [
                    'skill' => $item['raw_skill'],
                    'category' => $skillModel->category?->value ?? 'framework',
                    'competency' => $item['competency'],
                    'evidence' => $item['evidence'],
                    'source_type' => $item['source_type'],
                    'origin' => CompetencyOrigin::Ai,
                    'review_status' => CompetencyReviewStatus::Confirmed,
                    'page' => $item['page'],
                    'verification_status' => $verifResult->status,
                    'embedding' => $embedding,
                    'embedding_model' => $embeddingService->getModel(),
                    'embedding_dimensions' => $embeddingService->getDimensions(),
                    'text_hash' => $embeddingService->computeHash($embedText),
                ]
            );

            $extractedCourseSkills[] = $courseSkill;
            $competencyRows[] = [
                $courseSkill->id,
                $skillModel->canonical_name,
                "Hal. {$item['page']}",
                $verifResult->status === VerificationStatus::Verified ? '✅ verified' : '⚠️ unverified',
                mb_strimwidth($item['evidence'], 0, 50, '...'),
            ];
        }

        $this->table(
            ['ID', 'Skill Kanonik', 'Halaman', 'Verifikasi Bukti', 'Kutipan Bukti'],
            $competencyRows
        );
        $this->line('');

        // -------------------------------------------------------------
        // 4. Lowongan Industri & Kebutuhan Keterampilan
        // -------------------------------------------------------------
        $this->info('📌 TAHAP 4: LOWONGAN INDUSTRI & KEBUTUHAN KETERAMPILAN');
        $this->line('----------------------------------------------------------------');

        // Ambil lowongan backend dari dataset
        $postings = JobPosting::where('career_id', $career->id)->limit(5)->get();
        if ($postings->isEmpty()) {
            $this->warn('Tidak ada lowongan di DB, membuat 3 lowongan simulasi.');
            $postings = collect([
                JobPosting::create([
                    'career_id' => $career->id,
                    'company' => 'Tech Nusantara',
                    'title' => 'Senior Backend Engineer',
                    'description' => 'Membangun microservices dengan Laravel, PostgreSQL, Redis, dan Docker.',
                    'source_name' => 'Jobicy',
                    'source_url' => 'https://example.com/job/1',
                    'extraction_status' => ExtractionStatus::Completed,
                ]),
                JobPosting::create([
                    'career_id' => $career->id,
                    'company' => 'Fintech Asia',
                    'title' => 'Backend Developer (API & Database)',
                    'description' => 'Dibutuhkan pengembang backend mahir REST API, PostgreSQL, Redis cache, dan Git.',
                    'source_name' => 'Arbeitnow',
                    'source_url' => 'https://example.com/job/2',
                    'extraction_status' => ExtractionStatus::Completed,
                ]),
                JobPosting::create([
                    'career_id' => $career->id,
                    'company' => 'Cloud Solusindo',
                    'title' => 'Cloud Backend Engineer',
                    'description' => 'Membangun arsitektur scalable dengan Docker, Kubernetes, PostgreSQL, dan Laravel.',
                    'source_name' => 'Himalayas',
                    'source_url' => 'https://example.com/job/3',
                    'extraction_status' => ExtractionStatus::Completed,
                ]),
            ]);
        }

        // Pastikan lowongan terpilih memiliki job_skills dengan embedding
        $industrySkillsSetup = [
            'Laravel Framework' => [
                'req' => 'Strong experience with PHP Laravel framework and MVC backend architecture',
                'evidence' => 'Membangun microservices dengan Laravel dan MVC pattern',
            ],
            'PostgreSQL' => [
                'req' => 'Proficiency in relational database design, query optimization, and PostgreSQL',
                'evidence' => 'Relational database PostgreSQL dan query optimization',
            ],
            'Git' => [
                'req' => 'Hands-on experience using Git version control, branching workflows and code reviews',
                'evidence' => 'Kolaborasi tim menggunakan Git dan pull requests',
            ],
            'Docker' => [
                'req' => 'Containerization experience deploying services with Docker and Docker Compose',
                'evidence' => 'Membangun arsitektur scalable dengan Docker containerization',
            ],
            'Redis' => [
                'req' => 'In-memory caching, session store, and queuing system with Redis',
                'evidence' => 'In-memory caching dan queue worker dengan Redis',
            ],
            'Kubernetes' => [
                'req' => 'Orchestrating microservices deployment in production using Kubernetes cluster',
                'evidence' => 'Deploy and manage containerized apps on Kubernetes cluster',
            ],
        ];

        foreach ($postings as $postingIndex => $posting) {
            $posting->update(['extraction_status' => ExtractionStatus::Completed]);

            // Berikan variasi skill per lowongan untuk menghasilkan demand_count realistis
            $assignedSkills = match ($postingIndex % 3) {
                0 => ['Laravel Framework', 'PostgreSQL', 'Git', 'Docker', 'Redis'],
                1 => ['Laravel Framework', 'PostgreSQL', 'Redis', 'Docker'],
                default => ['Laravel Framework', 'PostgreSQL', 'Git', 'Kubernetes', 'Redis'],
            };

            foreach ($assignedSkills as $skillName) {
                $skillModel = $skillNormalizer->normalize($skillName);
                $info = $industrySkillsSetup[$skillName];
                $embedText = "{$skillModel->canonical_name}: {$info['req']}";
                $embedding = $embeddingService->embed($embedText);

                JobSkill::updateOrCreate(
                    [
                        'job_posting_id' => $posting->id,
                        'skill_id' => $skillModel->id,
                    ],
                    [
                        'skill' => $skillName,
                        'requirement_text' => $info['req'],
                        'evidence' => $info['evidence'],
                        'embedding' => $embedding,
                        'embedding_model' => $embeddingService->getModel(),
                        'embedding_dimensions' => $embeddingService->getDimensions(),
                        'text_hash' => $embeddingService->computeHash($embedText),
                    ]
                );
            }
        }

        $this->line("🏢 Sampel Lowongan Terpilih : <info>{$postings->count()} lowongan</info> untuk karir {$career->name}:");
        foreach ($postings->take(3) as $p) {
            $this->line("   - [{$p->company}] {$p->title} ({$p->source_name})");
        }
        $this->line('');

        // -------------------------------------------------------------
        // 5. Eksekusi Analisis Keselarasan (Matching & Gap Analysis)
        // -------------------------------------------------------------
        $this->info('📌 TAHAP 5: ANALISIS KESELARASAN (MATCHING & GAP CLASSIFICATION)');
        $this->line('----------------------------------------------------------------');

        $analysis = Analysis::create([
            'user_id' => $user->id,
            'rps_document_id' => $rps->id,
            'career_id' => $career->id,
            'status' => AnalysisStatus::Processing,
        ]);
        $analysis->jobPostings()->sync($postings->pluck('id'));

        // Jalankan pipeline analisis
        $aggregatedSkills = [];
        foreach ($postings as $posting) {
            foreach ($posting->jobSkills as $js) {
                $skillId = (int) $js->skill_id;
                if (! isset($aggregatedSkills[$skillId])) {
                    $aggregatedSkills[$skillId] = [
                        'skill' => $js->skillEntry,
                        'representative' => $js,
                        'demand_count' => 1,
                        'posting_ids' => [$posting->id],
                        'source_job_skill_ids' => [$js->id],
                    ];
                } else {
                    if (! in_array($posting->id, $aggregatedSkills[$skillId]['posting_ids'], true)) {
                        $aggregatedSkills[$skillId]['posting_ids'][] = $posting->id;
                        $aggregatedSkills[$skillId]['demand_count']++;
                    }
                    $aggregatedSkills[$skillId]['source_job_skill_ids'][] = $js->id;
                }
            }
        }

        $candidates = $rps->courseSkills()
            ->where('review_status', CompetencyReviewStatus::Confirmed)
            ->whereNotNull('embedding')
            ->get();

        $coveredCount = 0;
        $partialCount = 0;
        $missingCount = 0;
        $matchRows = [];

        foreach ($aggregatedSkills as $agg) {
            $rep = $agg['representative'];
            $topK = $candidateFinder->findTopK($rep->embedding, $candidates, 3);

            $judgeResult = $matchJudge->judge(
                skill: $agg['skill'],
                representativeJobSkill: $rep,
                candidateList: $topK,
                analysis: $analysis,
            );

            SkillMatch::create([
                'analysis_id' => $analysis->id,
                'skill_id' => $agg['skill']->id,
                'demand_count' => $agg['demand_count'],
                'source_job_skill_ids' => $agg['source_job_skill_ids'],
                'course_skill_id' => $judgeResult->matchedCourseSkillId,
                'similarity_score' => $judgeResult->similarityScore !== null ? round($judgeResult->similarityScore, 4) : null,
                'classification' => $judgeResult->classification,
                'reason' => $judgeResult->reason,
                'evidence' => $judgeResult->evidence,
                'candidates' => $judgeResult->candidates,
                'judged_by' => $judgeResult->judgedBy,
            ]);

            match ($judgeResult->classification) {
                MatchClassification::Covered => $coveredCount++,
                MatchClassification::Partial => $partialCount++,
                MatchClassification::Missing => $missingCount++,
            };

            $statusBadge = match ($judgeResult->classification) {
                MatchClassification::Covered => '<info>🟢 COVERED</info>',
                MatchClassification::Partial => '<comment>🟡 PARTIAL</comment>',
                MatchClassification::Missing => '<error>🔴 MISSING</error>',
            };

            $simFormatted = $judgeResult->similarityScore !== null ? number_format($judgeResult->similarityScore, 3) : '-';
            $matchRows[] = [
                $agg['skill']->canonical_name,
                "{$agg['demand_count']}/{$postings->count()} lowongan",
                $simFormatted,
                $statusBadge,
                $judgeResult->reason,
            ];
        }

        $totalSkills = count($aggregatedSkills);
        $coveragePercentage = $totalSkills > 0 ? round(($coveredCount / $totalSkills) * 100, 1) : 0.0;

        $analysis->update([
            'status' => AnalysisStatus::Completed,
            'summary' => [
                'total_skills' => $totalSkills,
                'covered' => $coveredCount,
                'partial' => $partialCount,
                'missing' => $missingCount,
                'coverage_percentage' => $coveragePercentage,
            ],
            'model_versions' => [
                'embedding_model' => $embeddingService->getModel(),
                'embedding_dimensions' => $embeddingService->getDimensions(),
                'prompt_version' => 'match-judge-v1',
            ],
        ]);

        $this->table(
            ['Skill Industri', 'Demand Industri', 'Cosine Sim', 'Klasifikasi', 'Alasan Penilaian'],
            $matchRows
        );

        $this->line('');
        $this->info("📊 RINGKASAN KESELARASAN KURIKULUM (COVERAGE):");
        $this->line("   - Total Kebutuhan Skill Industri : <info>{$totalSkills}</info>");
        $this->line("   - Tercakup Penuh (Covered)       : <info>{$coveredCount}</info> skill");
        $this->line("   - Tercakup Sebagian (Partial)    : <comment>{$partialCount}</comment> skill");
        $this->line("   - Belum Ditemukan di RPS (Missing: <error>{$missingCount}</error> skill");
        $this->line("   - Persentase Keselarasan         : <info>{$coveragePercentage}%</info>");
        $this->line('');

        // -------------------------------------------------------------
        // 6. Pembuatan Rekomendasi Belajar
        // -------------------------------------------------------------
        $this->info('📌 TAHAP 6: REKOMENDASI BELAJAR MAHASISWA (ACTIONABLE PLAN)');
        $this->line('----------------------------------------------------------------');

        $recoRows = [];
        $allMatches = $analysis->skillMatches()->with('skill')->get();

        foreach ($allMatches as $match) {
            $priority = $priorityCalculator->forMatch($match, $postings->count());
            if ($priority === null) {
                // Skill covered tidak memerlukan rekomendasi
                continue;
            }

            // Topik & kata kunci belajar
            $details = match ($match->skill->canonical_name) {
                'Redis' => [
                    'topics' => ['Redis Data Structures (String, Hash, List)', 'Redis Caching Pattern & Cache Invalidation', 'Redis Pub/Sub & Task Queues'],
                    'learning_keywords' => ['redis in-memory cache', 'laravel redis queue', 'cache aside pattern'],
                ],
                'Kubernetes' => [
                    'topics' => ['Kubernetes Architecture (Pods, Services, Deployments)', 'Container Orchestration & Scaling', 'ConfigMap & Secret Management'],
                    'learning_keywords' => ['kubernetes pod deployment', 'kubectl basic commands', 'k8s service networking'],
                ],
                'Docker' => [
                    'topics' => ['Multi-stage Dockerfile Optimization', 'Docker Compose Networking for Local Microservices'],
                    'learning_keywords' => ['docker multi-stage build', 'docker-compose volumes'],
                ],
                default => [
                    'topics' => ["Konsep Fundamental {$match->skill->canonical_name}", "Implementasi Praktis {$match->skill->canonical_name}"],
                    'learning_keywords' => [mb_strtolower($match->skill->canonical_name)." tutorial", mb_strtolower($match->skill->canonical_name)." best practices"],
                ],
            };

            $title = "Panduan Belajar {$match->skill->canonical_name}";
            $reason = "Dibutuhkan oleh {$match->demand_count} lowongan industri terpilih namun belum tercakup di RPS.";

            $reco = Recommendation::updateOrCreate(
                [
                    'analysis_id' => $analysis->id,
                    'skill_match_id' => $match->id,
                ],
                [
                    'title' => $title,
                    'reason' => $reason,
                    'priority' => $priority,
                    'details' => $details,
                ]
            );

            $prioBadge = match ($priority) {
                RecommendationPriority::High => '<error>🔴 HIGH</error>',
                RecommendationPriority::Medium => '<comment>🟡 MEDIUM</comment>',
                RecommendationPriority::Low => '<info>🟢 LOW</info>',
            };

            $recoRows[] = [
                $match->skill->canonical_name,
                $match->classification->value,
                $prioBadge,
                implode(', ', $details['topics']),
                implode(', ', $details['learning_keywords']),
            ];
        }

        $this->table(
            ['Skill Rekomendasi', 'Status Gap', 'Prioritas', 'Topik Pembelajaran Mandiri', 'Kata Kunci Pencarian'],
            $recoRows
        );

        $this->line('');
        $this->info('================================================================');
        $this->info('  🎉 SIMULASI SELESAI: ALUR KERJA SKILLBRIDGE BEKERJA SEMPURNA!');
        $this->info('================================================================');
        $this->line("1. Dokumen RPS di-parse dan diverifikasi buktinya tanpa halusinasi.");
        $this->line("2. Kompetensi RPS dan lowongan industri di-embed ke vektor semantik L2.");
        $this->line("3. Keselarasan dihitung dengan Cosine Similarity & LLM MatchJudge.");
        $this->line("4. Gap skill dipetakan secara objektif (Covered/Partial/Missing).");
        $this->line("5. Mahasiswa mendapatkan rekomendasi topik & kata kunci belajar terarah.");
        $this->line('');

        return self::SUCCESS;
    }
}
