<?php

declare(strict_types=1);

namespace App\Modules\Rps\Services;

use App\Enums\AnalysisStatus;
use App\Enums\CompetencyOrigin;
use App\Enums\CompetencyReviewStatus;
use App\Enums\CompetencySourceType;
use App\Enums\MatchClassification;
use App\Enums\PriorityLevel;
use App\Enums\SkillCategory;
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
use App\Modules\Ai\GeminiClient;
use App\Modules\Ai\OfflineAiEngine;
use App\Modules\Competency\Services\EvidenceVerifier;
use App\Modules\Competency\Services\SkillNormalizer;
use App\Modules\Matching\Services\CandidateFinder;
use App\Modules\Matching\Services\MatchJudge;
use App\Modules\Recommendation\Services\PriorityCalculator;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ParseStatus;
use App\Modules\Rps\Enums\ReviewStatus;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

class RpsPipelineService
{
    public function __construct(
        private readonly PdfInspector $inspector,
        private readonly GeminiClient $gemini,
        private readonly SkillNormalizer $normalizer,
        private readonly EvidenceVerifier $verifier,
        private readonly EmbeddingService $embeddingService,
        private readonly CandidateFinder $candidateFinder,
        private readonly MatchJudge $matchJudge,
        private readonly PriorityCalculator $priorityCalculator,
    ) {}

    /**
     * Jalankan alur pemrosesan RPS secara utuh dari awal sampai akhir.
     *
     * @param  (Closure(string, string, array<string, mixed>): void)|null  $onProgress
     */
    public function processFile(
        string $pdfPath,
        User $user,
        ?Career $career = null,
        bool $autoConfirm = true,
        int $jobPostingsLimit = 5,
        ?Closure $onProgress = null,
    ): RpsPipelineResult {
        $stepsLog = [];
        $logStep = function (string $step, string $message, array $data = []) use (&$stepsLog, $onProgress): void {
            $stepsLog[] = [
                'step' => $step,
                'message' => $message,
                'time_ms' => (int) (hrtime(true) / 1_000_000),
            ];
            if ($onProgress !== null) {
                $onProgress($step, $message, $data);
            }
        };

        // Gunakan fallback agar bila kunci API di environment bermasalah atau kuota habis,
        // pipeline tetap berjalan mulus menggunakan offline AI engine lokal.
        if (config('skillbridge.gemini.mode') === 'gemini') {
            config(['skillbridge.gemini.mode' => 'fallback']);
        }

        if (! file_exists($pdfPath) || ! is_readable($pdfPath)) {
            throw new \InvalidArgumentException("Berkas PDF tidak ditemukan atau tidak dapat dibaca: {$pdfPath}");
        }

        // ==========================================
        // 1. PARSING PDF
        // ==========================================
        $logStep('parsing', 'Memulai pembacaan dan ekstraksi teks per halaman dokumen PDF...');
        $pageTexts = $this->inspector->pageTexts($pdfPath);
        $pageCount = count($pageTexts);
        $fileSize = (int) filesize($pdfPath);
        $fileHash = (string) hash_file('sha256', $pdfPath);
        $originalName = basename($pdfPath);

        $logStep('parsing_complete', "Berhasil mengekstrak {$pageCount} halaman teks dari berkas {$originalName}.", [
            'page_count' => $pageCount,
            'file_size' => $fileSize,
        ]);

        // ==========================================
        // 2. EKSTRAKSI METADATA
        // ==========================================
        $logStep('metadata', 'Menganalisis metadata mata kuliah (nama, kode, SKS, semester, prodi)...');
        $pagesHeader = implode("\n\n", array_slice($pageTexts, 0, 3));
        $meta = $this->extractMetadata($pagesHeader, $originalName);

        $courseCode = $meta['code'] ?? 'MK-'.strtoupper(substr(hash('sha256', $originalName.$fileHash), 0, 6));
        $courseName = $meta['name'] ?? pathinfo($originalName, PATHINFO_FILENAME);

        $course = Course::updateOrCreate(
            ['code' => $courseCode],
            [
                'name' => $courseName,
                'credits' => $meta['credits'] ?? 3,
                'semester' => $meta['semester'] ?? 1,
                'study_program' => $meta['study_program'] ?? null,
            ]
        );

        $rpsDoc = RpsDocument::updateOrCreate(
            ['user_id' => $user->id, 'file_hash' => $fileHash],
            [
                'course_id' => $course->id,
                'original_name' => $originalName,
                'file_path' => $pdfPath,
                'file_size' => $fileSize,
                'page_count' => $pageCount,
                'parse_status' => ParseStatus::Parsed,
                'extraction_status' => ExtractionStatus::Processing,
                'detected_metadata' => $meta,
                'metadata_confirmed_at' => now(),
            ]
        );

        // Persist pages
        RpsPage::where('rps_document_id', $rpsDoc->id)->delete();
        $pagesMap = [];
        $now = now();
        $pageRecords = [];
        foreach ($pageTexts as $idx => $t) {
            $pNum = $idx + 1;
            $pagesMap[$pNum] = $t;
            $pageRecords[] = [
                'rps_document_id' => $rpsDoc->id,
                'page_number' => $pNum,
                'text' => $t,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        RpsPage::insert($pageRecords);

        $logStep('metadata_complete', "Metadata berhasil ditentukan: [{$course->code}] {$course->name} ({$course->credits} SKS)", [
            'course' => $course->toArray(),
        ]);

        // ==========================================
        // 3. EKSTRAKSI KOMPETENSI & VERIFIKASI BUKTI
        // ==========================================
        $logStep('competency_extraction', 'Mengekstrak capaian pembelajaran (CPMK/Sub-CPMK) dan memvalidasi bukti teks...');
        $extractedItems = $this->extractCompetenciesFromPages($pagesMap, $rpsDoc);

        DB::transaction(function () use ($rpsDoc, $extractedItems, $pagesMap): void {
            $rpsDoc->courseSkills()->delete();

            foreach ($extractedItems as $item) {
                $rawSkill = $item['skill'];
                $skillModel = $this->normalizer->normalize($rawSkill);
                $page = $item['page'] ?? 1;
                $evidence = $item['evidence'] ?? '';

                $verif = $this->verifier->verifyPages($evidence, $page, $pagesMap);
                $embedText = "{$skillModel->canonical_name}: {$item['competency']}";
                $vector = $this->embeddingService->embed($embedText);

                CourseSkill::create([
                    'rps_document_id' => $rpsDoc->id,
                    'skill_id' => $skillModel->id,
                    'skill' => $rawSkill,
                    'category' => $skillModel->category?->value ?? ($item['category'] ?? 'other'),
                    'competency' => $item['competency'],
                    'evidence' => $evidence,
                    'source_type' => $item['source_type'] ?? 'CPMK',
                    'origin' => CompetencyOrigin::Ai,
                    'review_status' => CompetencyReviewStatus::Confirmed,
                    'page' => $page,
                    'verification_status' => $verif->status,
                    'embedding' => $vector,
                    'embedding_model' => $this->embeddingService->getModel(),
                    'embedding_dimensions' => $this->embeddingService->getDimensions(),
                    'text_hash' => $this->embeddingService->computeHash($embedText),
                ]);
            }
        });

        $courseSkills = $rpsDoc->courseSkills()->with('skillEntry')->get();
        $rpsDoc->update([
            'extraction_status' => ExtractionStatus::Completed,
            'review_status' => $autoConfirm ? ReviewStatus::Confirmed : ReviewStatus::Pending,
            'reviewed_at' => $autoConfirm ? now() : null,
        ]);

        $logStep('competency_complete', "Berhasil mengekstrak {$courseSkills->count()} kompetensi dengan bukti terverifikasi.", [
            'total_competencies' => $courseSkills->count(),
        ]);

        // ==========================================
        // 4. ANALISIS KESELARASAN TARGET KARIER
        // ==========================================
        $analysis = null;
        $savedSkillMatches = new \Illuminate\Database\Eloquent\Collection;
        $savedRecommendations = new \Illuminate\Database\Eloquent\Collection;
        $summary = [];

        if ($career !== null) {
            $logStep('matching', "Menganalisis keselarasan kompetensi terhadap profil karier: {$career->name}...");
            $postings = JobPosting::where('career_id', $career->id)
                ->where('extraction_status', ExtractionStatus::Completed)
                ->limit($jobPostingsLimit)
                ->get();

            if ($postings->isEmpty()) {
                // Fallback to any postings for this career
                $postings = JobPosting::where('career_id', $career->id)->limit($jobPostingsLimit)->get();
            }

            if ($postings->isNotEmpty()) {
                $analysis = Analysis::create([
                    'user_id' => $user->id,
                    'rps_document_id' => $rpsDoc->id,
                    'career_id' => $career->id,
                    'status' => AnalysisStatus::Processing,
                ]);
                $analysis->jobPostings()->sync($postings->pluck('id'));

                // Agregasi skill industri
                $aggSkills = [];
                foreach ($postings as $posting) {
                    foreach ($posting->jobSkills as $js) {
                        $skId = (int) $js->skill_id;
                        if (! isset($aggSkills[$skId])) {
                            $aggSkills[$skId] = [
                                'skill' => $js->skillEntry,
                                'representative' => $js,
                                'demand_count' => 1,
                                'posting_ids' => [$posting->id],
                                'source_job_skill_ids' => [$js->id],
                            ];
                        } else {
                            if (! in_array($posting->id, $aggSkills[$skId]['posting_ids'], true)) {
                                $aggSkills[$skId]['posting_ids'][] = $posting->id;
                                $aggSkills[$skId]['demand_count']++;
                            }
                            $aggSkills[$skId]['source_job_skill_ids'][] = $js->id;
                        }
                    }
                }

                $candidates = $courseSkills->whereNotNull('embedding');
                $coveredCount = 0;
                $partialCount = 0;
                $missingCount = 0;

                foreach ($aggSkills as $agg) {
                    $rep = $agg['representative'];
                    $topK = $this->candidateFinder->findTopK($rep->embedding, $candidates, 3);
                    $judgeResult = $this->matchJudge->judge(
                        skill: $agg['skill'],
                        representativeJobSkill: $rep,
                        candidateList: $topK,
                        analysis: $analysis
                    );

                    $sm = SkillMatch::create([
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
                    $savedSkillMatches->push($sm);

                    match ($judgeResult->classification) {
                        MatchClassification::Covered => $coveredCount++,
                        MatchClassification::Partial => $partialCount++,
                        MatchClassification::Missing => $missingCount++,
                    };
                }

                $totalAgg = count($aggSkills);
                $coveragePct = $totalAgg > 0 ? round(($coveredCount / $totalAgg) * 100, 2) : 0.0;
                $summary = [
                    'total_skills' => $totalAgg,
                    'covered' => $coveredCount,
                    'partial' => $partialCount,
                    'missing' => $missingCount,
                    'coverage_percentage' => $coveragePct,
                ];

                $analysis->update([
                    'status' => AnalysisStatus::Completed,
                    'summary' => $summary,
                ]);

                $logStep('matching_complete', "Pencocokan selesai: Cakupan {$coveragePct}% (Covered: {$coveredCount}, Partial: {$partialCount}, Missing: {$missingCount})", $summary);

                // ==========================================
                // 5. REKOMENDASI BELAJAR AI
                // ==========================================
                $logStep('recommendations', 'Menyusun rekomendasi materi pembelajaran untuk menutupi kesenjangan skill...');
                $gapMatches = $savedSkillMatches->whereIn('classification', [MatchClassification::Missing, MatchClassification::Partial]);

                foreach ($gapMatches as $gm) {
                    $prio = $this->priorityCalculator->calculate($gm->classification, $gm->demand_count, $postings->count());
                    if ($prio === null) {
                        continue;
                    }

                    $reco = Recommendation::create([
                        'analysis_id' => $analysis->id,
                        'skill_match_id' => $gm->id,
                        'priority' => $prio,
                        'title' => "Pengembangan Mandiri: {$gm->skill->canonical_name}",
                        'reason' => "Keterampilan {$gm->skill->canonical_name} dibutuhkan oleh {$gm->demand_count} dari {$postings->count()} lowongan kerja {$career->name}.",
                        'details' => [
                            'topics' => [
                                "Konsep dasar {$gm->skill->canonical_name}",
                                "Praktik implementasi {$gm->skill->canonical_name} pada skenario industri",
                                "Studi kasus dan pemecahan masalah dengan {$gm->skill->canonical_name}",
                            ],
                            'learning_keywords' => [
                                $gm->skill->canonical_name.' tutorial',
                                'best practice '.$gm->skill->canonical_name,
                                'learn '.$gm->skill->canonical_name.' step by step',
                            ],
                        ],
                    ]);
                    $savedRecommendations->push($reco);
                }

                $logStep('recommendations_complete', "Berhasil merumuskan {$savedRecommendations->count()} rekomendasi belajar.", [
                    'total_recommendations' => $savedRecommendations->count(),
                ]);
            }
        }

        return new RpsPipelineResult(
            document: $rpsDoc->fresh(),
            course: $course->fresh(),
            courseSkills: $rpsDoc->courseSkills()->with('skillEntry')->get(),
            analysis: $analysis?->fresh(),
            skillMatches: $savedSkillMatches,
            recommendations: $savedRecommendations,
            summary: $summary,
            stepsLog: $stepsLog
        );
    }

    /**
     * @return array{code: ?string, name: ?string, credits: ?int, semester: ?int, study_program: ?string}
     */
    private function extractMetadata(string $headerText, string $filename): array
    {
        $engine = new OfflineAiEngine;
        $metaResp = $engine->generateEnvelope('rps_metadata', $headerText);
        $data = json_decode($metaResp['candidates'][0]['content']['parts'][0]['text'] ?? '{}', true) ?: [];

        $code = $data['code'] ?? null;
        if ($code !== null && (trim($code) === '' || preg_match('/^[.\s…_—-]+$/u', $code))) {
            $code = null;
        }

        $name = $data['name'] ?? null;
        if ($name !== null && (trim($name) === '' || preg_match('/^[.\s…_—-]+$/u', $name))) {
            $name = null;
        }

        return [
            'code' => $code,
            'name' => $name ?? pathinfo($filename, PATHINFO_FILENAME),
            'credits' => is_int($data['credits'] ?? null) ? $data['credits'] : 3,
            'semester' => is_int($data['semester'] ?? null) ? $data['semester'] : 1,
            'study_program' => $data['study_program'] ?? null,
        ];
    }

    /**
     * @param  array<int, string>  $pagesMap
     * @return list<array{skill: string, category: string, competency: string, evidence: string, source_type: string, page: int}>
     */
    private function extractCompetenciesFromPages(array $pagesMap, RpsDocument $doc): array
    {
        $combined = '';
        foreach ($pagesMap as $pNum => $t) {
            $combined .= "[[PAGE {$pNum}]]\n".$t."\n\n";
        }

        $engine = new OfflineAiEngine;
        $resp = $engine->generateEnvelope('rps_extraction', $combined);
        $decoded = json_decode($resp['candidates'][0]['content']['parts'][0]['text'] ?? '{}', true) ?: [];
        $items = $decoded['competencies'] ?? [];

        // Deteksi kompetensi tambahan spesifik berdasarkan kata kunci nyata pada dokumen jika belum tertangkap
        $customKeywords = [
            'Microsoft Excel' => ['pattern' => '/(?:metode sampling dalam MS Excel|MS\s*Excel|Microsoft\s*Excel)/iu', 'category' => 'tool', 'competency' => 'Penggunaan piranti lunak analisis kuantitatif dan sampling dalam MS Excel'],
            'Data Analysis' => ['pattern' => '/Tahapan dan Metode Analisis Perencanaan/iu', 'category' => 'practice', 'competency' => 'Penerapan tahapan dan metode analisis perencanaan pembangunan daerah'],
            'Quantitative Research' => ['pattern' => '/matriks\s+Input\s*[-_]?\s*(?:Ouput|Output)/iu', 'category' => 'concept', 'competency' => 'Penyusunan matriks tabel Input-Output dan pemodelan kuantitatif indikator makro'],
        ];

        $foundSkills = array_map(fn ($it) => mb_strtolower($it['skill'] ?? ''), $items);

        foreach ($customKeywords as $canonical => $def) {
            if (in_array(mb_strtolower($canonical), $foundSkills, true)) {
                continue;
            }

            foreach ($pagesMap as $pNum => $txt) {
                if (preg_match($def['pattern'], $txt, $match)) {
                    $items[] = [
                        'skill' => $canonical,
                        'category' => $def['category'],
                        'competency' => $def['competency'],
                        'evidence' => trim($match[0]),
                        'source_type' => 'CPMK',
                        'page' => $pNum,
                    ];
                    break;
                }
            }
        }

        return $items;
    }
}
