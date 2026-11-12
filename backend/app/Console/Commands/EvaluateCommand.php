<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\JudgedBy;
use App\Enums\MatchClassification;
use App\Enums\SkillCategory;
use App\Enums\VerificationStatus;
use App\Models\CourseSkill;
use App\Models\Skill;
use App\Modules\Competency\Services\EvidenceVerifier;
use App\Modules\Matching\Services\ClassificationGuardrails;
use App\Modules\Matching\Services\MatchJudgeResult;
use App\Modules\Recommendation\Services\PriorityCalculator;
use App\Support\Cosine;
use Database\Seeders\SkillSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class EvaluateCommand extends Command
{
    protected $signature = 'skillbridge:evaluate
        {--set=golden : Nama subset fixture di tests/Fixtures/<set>}';

    protected $description = 'Evaluasi kualitas AI (Extraction, Matching/Gap Classification, Kalibrasi SIM_FLOOR, dan Recommendation Relevance) menggunakan golden dataset';

    public function handle(
        EvidenceVerifier $evidenceVerifier,
        ClassificationGuardrails $guardrails,
        PriorityCalculator $priorityCalculator,
    ): int {
        $set = trim((string) $this->option('set'));
        if ($set === '' || ! preg_match('/^[a-zA-Z0-9_-]+$/', $set)) {
            $this->error('Nama dataset tidak valid.');

            return self::FAILURE;
        }

        $fixtureDir = base_path("tests/Fixtures/{$set}");
        if (! is_dir($fixtureDir)) {
            $this->error("Direktori dataset tidak ditemukan: {$fixtureDir}");

            return self::FAILURE;
        }

        $rpsPath = "{$fixtureDir}/rps_extraction.json";
        $matchingPath = "{$fixtureDir}/matching_pairs.json";
        $recoPath = "{$fixtureDir}/recommendations.json";

        if (! is_file($rpsPath) || ! is_file($matchingPath) || ! is_file($recoPath)) {
            $this->error('Berkas golden dataset (rps_extraction.json, matching_pairs.json, recommendations.json) belum lengkap.');

            return self::FAILURE;
        }

        $rpsData = json_decode((string) file_get_contents($rpsPath), true, 512, JSON_THROW_ON_ERROR);
        $matchingData = json_decode((string) file_get_contents($matchingPath), true, 512, JSON_THROW_ON_ERROR);
        $recoData = json_decode((string) file_get_contents($recoPath), true, 512, JSON_THROW_ON_ERROR);

        $extractionMetrics = $this->evaluateExtraction($rpsData, $evidenceVerifier);
        $thresholdSweep = $this->evaluateThresholdSweep($matchingData);
        $simFloor = (float) config('skillbridge.matching.sim_floor', 0.50);
        $matchingMetrics = $this->evaluateMatching($matchingData, $guardrails, $simFloor);
        $recommendationMetrics = $this->evaluateRecommendations($recoData, $priorityCalculator);

        $report = [
            'dataset' => $set,
            'evaluated_at' => now()->toIso8601String(),
            'config' => [
                'sim_floor' => $simFloor,
                'sim_high' => (float) config('skillbridge.matching.sim_high', 0.80),
                'top_k' => (int) config('skillbridge.matching.top_k', 3),
                'high_demand_ratio' => (float) config('skillbridge.recommendation.priority.high_demand_ratio', 0.50),
            ],
            'extraction' => $extractionMetrics,
            'matching' => $matchingMetrics,
            'threshold_calibration' => $thresholdSweep,
            'recommendations' => $recommendationMetrics,
        ];

        $evalDir = storage_path('app/evals');
        if (! is_dir($evalDir)) {
            File::makeDirectory($evalDir, 0755, true);
        }

        $reportPath = "{$evalDir}/eval-{$set}.json";
        file_put_contents(
            $reportPath,
            (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->renderConsoleSummary($report, $reportPath);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $rpsData
     * @return array<string, mixed>
     */
    private function evaluateExtraction(array $rpsData, EvidenceVerifier $evidenceVerifier): array
    {
        $dictionary = SkillSeeder::dictionary();
        $aliasMap = [];
        foreach ($dictionary as $canonical => [$cat, $aliases]) {
            $aliasMap[mb_strtolower($canonical)] = $canonical;
            foreach ($aliases as $alias) {
                $aliasMap[mb_strtolower($alias)] = $canonical;
            }
        }

        $tp = 0;
        $fp = 0;
        $fn = 0;
        $totalPredictions = 0;
        $verifiedCount = 0;
        $courseCount = 0;

        foreach ((array) ($rpsData['courses'] ?? []) as $course) {
            $courseCount++;
            $pages = [];
            foreach ((array) ($course['pages'] ?? []) as $page) {
                $pages[(int) $page['page_number']] = (string) $page['text'];
            }

            $groundTruth = array_values(array_unique((array) ($course['ground_truth_skills'] ?? [])));
            $predictedCanonicals = [];

            foreach ((array) ($course['extracted_predictions'] ?? []) as $pred) {
                $totalPredictions++;
                $rawSkill = trim((string) ($pred['raw_skill'] ?? ''));
                $canonical = $aliasMap[mb_strtolower($rawSkill)] ?? $rawSkill;
                $predictedCanonicals[] = $canonical;

                $verification = $evidenceVerifier->verifyPages(
                    (string) ($pred['evidence'] ?? ''),
                    isset($pred['page']) ? (int) $pred['page'] : null,
                    $pages
                );

                if ($verification->isVerified()) {
                    $verifiedCount++;
                }
            }

            $predictedCanonicals = array_values(array_unique($predictedCanonicals));

            foreach ($predictedCanonicals as $predSkill) {
                if (in_array($predSkill, $groundTruth, true)) {
                    $tp++;
                } else {
                    $fp++;
                }
            }

            foreach ($groundTruth as $gtSkill) {
                if (! in_array($gtSkill, $predictedCanonicals, true)) {
                    $fn++;
                }
            }
        }

        $precision = ($tp + $fp) > 0 ? round($tp / ($tp + $fp), 4) : 0.0;
        $recall = ($tp + $fn) > 0 ? round($tp / ($tp + $fn), 4) : 0.0;
        $f1 = ($precision + $recall) > 0 ? round((2 * $precision * $recall) / ($precision + $recall), 4) : 0.0;
        $verificationRate = $totalPredictions > 0 ? round($verifiedCount / $totalPredictions, 4) : 0.0;

        return [
            'courses_count' => $courseCount,
            'total_predictions' => $totalPredictions,
            'true_positives' => $tp,
            'false_positives' => $fp,
            'false_negatives' => $fn,
            'precision' => $precision,
            'recall' => $recall,
            'f1' => $f1,
            'evidence_verified_rate' => $verificationRate,
        ];
    }

    /**
     * @param  array<string, mixed>  $matchingData
     * @return list<array<string, mixed>>
     */
    private function evaluateThresholdSweep(array $matchingData): array
    {
        $pairs = (array) ($matchingData['pairs'] ?? []);
        $thresholds = [0.35, 0.40, 0.45, 0.50, 0.55, 0.60, 0.65];
        $results = [];

        $positiveTotal = 0;
        $negativeTotal = 0;
        foreach ($pairs as $pair) {
            $gt = (string) ($pair['ground_truth'] ?? 'missing');
            if ($gt === 'covered' || $gt === 'partial') {
                $positiveTotal++;
            } else {
                $negativeTotal++;
            }
        }

        foreach ($thresholds as $floor) {
            $positiveRetained = 0;
            $negativeFilteredOut = 0;
            $llmCallsTriggered = 0;

            foreach ($pairs as $pair) {
                $jobVec = Cosine::l2Normalize((array) ($pair['job_vector'] ?? []));
                $candVec = Cosine::l2Normalize((array) ($pair['candidate']['vector'] ?? []));
                $score = Cosine::similarity($jobVec, $candVec);
                $gt = (string) ($pair['ground_truth'] ?? 'missing');

                if ($score >= $floor) {
                    $llmCallsTriggered++;
                    if ($gt === 'covered' || $gt === 'partial') {
                        $positiveRetained++;
                    }
                } elseif ($gt === 'missing') {
                    $negativeFilteredOut++;
                }
            }

            $results[] = [
                'sim_floor' => $floor,
                'candidate_recall_covered_partial' => $positiveTotal > 0 ? round($positiveRetained / $positiveTotal, 4) : 0.0,
                'missing_filtered_by_rule_rate' => $negativeTotal > 0 ? round($negativeFilteredOut / $negativeTotal, 4) : 0.0,
                'llm_calls_triggered' => $llmCallsTriggered,
                'total_pairs' => count($pairs),
            ];
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $matchingData
     * @return array<string, mixed>
     */
    private function evaluateMatching(
        array $matchingData,
        ClassificationGuardrails $guardrails,
        float $simFloor,
    ): array {
        $classes = ['covered', 'partial', 'missing'];
        $confusionMatrix = [];
        foreach ($classes as $actual) {
            $confusionMatrix[$actual] = array_fill_keys($classes, 0);
        }

        $pairs = (array) ($matchingData['pairs'] ?? []);

        foreach ($pairs as $pair) {
            $jobVec = Cosine::l2Normalize((array) ($pair['job_vector'] ?? []));
            $candVec = Cosine::l2Normalize((array) ($pair['candidate']['vector'] ?? []));
            $score = Cosine::similarity($jobVec, $candVec);

            $candData = (array) ($pair['candidate'] ?? []);
            $candId = (int) ($candData['id'] ?? 1);

            $candidateModel = new CourseSkill([
                'competency' => (string) ($candData['competency'] ?? ''),
                'evidence' => $candData['evidence'] ?? null,
                'verification_status' => VerificationStatus::from((string) ($candData['verification_status'] ?? 'verified')),
            ]);
            $candidateModel->id = $candId;

            $skillModel = new Skill([
                'canonical_name' => (string) ($pair['skill'] ?? ''),
                'category' => SkillCategory::tryFrom((string) ($pair['category'] ?? 'other')),
                'aliases' => (array) ($pair['aliases'] ?? []),
            ]);

            if ($score < $simFloor) {
                $predicted = MatchClassification::Missing->value;
            } else {
                $llmRaw = (array) ($pair['llm_raw'] ?? []);
                $rawResult = new MatchJudgeResult(
                    classification: MatchClassification::from((string) ($llmRaw['classification'] ?? 'missing')),
                    matchedCourseSkillId: isset($llmRaw['matched_course_skill_id']) ? (int) $llmRaw['matched_course_skill_id'] : null,
                    similarityScore: $score,
                    reason: (string) ($llmRaw['reason'] ?? ''),
                    evidence: isset($llmRaw['evidence']) ? (string) $llmRaw['evidence'] : null,
                    judgedBy: JudgedBy::Llm,
                    candidates: [['course_skill_id' => $candId, 'score' => round($score, 5)]],
                );

                $guarded = $guardrails->apply(
                    raw: $rawResult,
                    skill: $skillModel,
                    candidateMap: [$candId => $candidateModel],
                    bestScore: $score,
                    simFloor: $simFloor,
                );

                $predicted = $guarded->classification->value;
            }

            $actual = (string) ($pair['ground_truth'] ?? 'missing');
            $confusionMatrix[$actual][$predicted]++;
        }

        $perClass = [];
        $f1Sum = 0.0;
        $correctTotal = 0;

        foreach ($classes as $cls) {
            $tp = $confusionMatrix[$cls][$cls];
            $correctTotal += $tp;

            $fp = 0;
            $fn = 0;
            foreach ($classes as $other) {
                if ($other !== $cls) {
                    $fp += $confusionMatrix[$other][$cls];
                    $fn += $confusionMatrix[$cls][$other];
                }
            }

            $precision = ($tp + $fp) > 0 ? round($tp / ($tp + $fp), 4) : 0.0;
            $recall = ($tp + $fn) > 0 ? round($tp / ($tp + $fn), 4) : 0.0;
            $f1 = ($precision + $recall) > 0 ? round((2 * $precision * $recall) / ($precision + $recall), 4) : 0.0;

            $perClass[$cls] = [
                'precision' => $precision,
                'recall' => $recall,
                'f1' => $f1,
                'support' => array_sum($confusionMatrix[$cls]),
            ];
            $f1Sum += $f1;
        }

        $totalPairs = count($pairs);
        $accuracy = $totalPairs > 0 ? round($correctTotal / $totalPairs, 4) : 0.0;
        $macroF1 = round($f1Sum / count($classes), 4);

        return [
            'total_pairs' => $totalPairs,
            'accuracy' => $accuracy,
            'macro_f1' => $macroF1,
            'per_class' => $perClass,
            'confusion_matrix' => $confusionMatrix,
        ];
    }

    /**
     * @param  array<string, mixed>  $recoData
     * @return array<string, mixed>
     */
    private function evaluateRecommendations(array $recoData, PriorityCalculator $priorityCalculator): array
    {
        $samples = (array) ($recoData['samples'] ?? []);
        $total = count($samples);
        $scoreSum = 0;
        $highRelevanceCount = 0;
        $priorityMatchCount = 0;
        $urlFreeCount = 0;

        foreach ($samples as $sample) {
            $score = (int) ($sample['manual_relevance_score'] ?? 0);
            $scoreSum += $score;
            if ($score >= 4) {
                $highRelevanceCount++;
            }

            $classification = MatchClassification::from((string) ($sample['classification'] ?? 'missing'));
            $computedPriority = $priorityCalculator->calculate(
                $classification,
                (int) ($sample['demand_count'] ?? 1),
                (int) ($sample['selected_postings'] ?? 1)
            );

            if ($computedPriority?->value === ($sample['expected_priority'] ?? null)) {
                $priorityMatchCount++;
            }

            $combinedText = implode(' ', [
                (string) ($sample['title'] ?? ''),
                (string) ($sample['reason'] ?? ''),
                implode(' ', (array) ($sample['topics'] ?? [])),
                implode(' ', (array) ($sample['learning_keywords'] ?? [])),
            ]);

            if (! preg_match('/https?:\/\/[^\s]+|www\.[^\s]+/i', $combinedText)) {
                $urlFreeCount++;
            }
        }

        return [
            'samples_count' => $total,
            'mean_relevance_score' => $total > 0 ? round($scoreSum / $total, 2) : 0.0,
            'high_relevance_rate' => $total > 0 ? round($highRelevanceCount / $total, 4) : 0.0,
            'priority_alignment_rate' => $total > 0 ? round($priorityMatchCount / $total, 4) : 0.0,
            'url_free_compliance_rate' => $total > 0 ? round($urlFreeCount / $total, 4) : 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderConsoleSummary(array $report, string $reportPath): void
    {
        $ext = $report['extraction'];
        $match = $report['matching'];
        $reco = $report['recommendations'];

        $this->info("=== Evaluasi Kualitas AI SkillBridge (set: {$report['dataset']}) ===");
        $this->line(sprintf(
            '1. Extraction Accuracy (%d RPS): Precision=%.2f%% | Recall=%.2f%% | F1=%.2f%% | Evidence Verified=%.2f%%',
            $ext['courses_count'],
            $ext['precision'] * 100,
            $ext['recall'] * 100,
            $ext['f1'] * 100,
            $ext['evidence_verified_rate'] * 100,
        ));

        $this->line(sprintf(
            '2. Matching & Gap Classification (%d pairs, SIM_FLOOR=%.2f): Accuracy=%.2f%% | Macro-F1=%.2f%%',
            $match['total_pairs'],
            $report['config']['sim_floor'],
            $match['accuracy'] * 100,
            $match['macro_f1'] * 100,
        ));

        $this->table(
            ['Kelas', 'Precision', 'Recall', 'F1', 'Support'],
            array_map(
                fn (string $cls, array $m) => [
                    $cls,
                    number_format($m['precision'], 4),
                    number_format($m['recall'], 4),
                    number_format($m['f1'], 4),
                    $m['support'],
                ],
                array_keys($match['per_class']),
                array_values($match['per_class']),
            )
        );

        $this->line(sprintf(
            '3. Recommendation Relevance (%d sampel): Mean Score=%.2f/5.00 | Priority Match=%.2f%% | URL-Free=%.2f%%',
            $reco['samples_count'],
            $reco['mean_relevance_score'],
            $reco['priority_alignment_rate'] * 100,
            $reco['url_free_compliance_rate'] * 100,
        ));

        $this->info("Laporan evaluasi disimpan ke: {$reportPath}");
    }
}
