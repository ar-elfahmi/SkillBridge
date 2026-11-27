<?php

declare(strict_types=1);

namespace App\Modules\Matching\Jobs;

use App\Enums\AnalysisStatus;
use App\Enums\CompetencyReviewStatus;
use App\Enums\MatchClassification;
use App\Models\Analysis;
use App\Models\CourseSkill;
use App\Models\JobSkill;
use App\Models\Skill;
use App\Models\SkillMatch;
use App\Modules\Ai\Concerns\HasGeminiJobSettings;
use App\Modules\Ai\Exceptions\GeminiQuotaExhaustedException;
use App\Modules\Ai\Exceptions\GeminiRateLimitedException;
use App\Modules\Matching\Services\CandidateFinder;
use App\Modules\Matching\Services\MatchJudge;
use App\Modules\Recommendation\Jobs\GenerateRecommendationsJob;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ReviewStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunAnalysisJob implements ShouldQueue
{
    use Dispatchable, HasGeminiJobSettings, InteractsWithQueue, Queueable, SerializesModels;

    // Legacy upper bound: retryUntil() (see HasGeminiJobSettings) takes precedence while it is defined.
    public int $tries = 3;

    public function __construct(
        public int $analysisId,
    ) {
        // The judge loop makes one Gemini call per skill, so this job gets the long timeout.
        $this->configureGeminiJob(long: true);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->analysisId))->dontRelease()->expireAfter($this->overlapLockSeconds())];
    }

    public function handle(
        CandidateFinder $candidateFinder,
        MatchJudge $matchJudge,
    ): void {
        $analysis = Analysis::with(['jobPostings.jobSkills.skillEntry', 'rpsDocument.courseSkills.skillEntry'])
            ->find($this->analysisId);

        if ($analysis === null || $analysis->status === AnalysisStatus::Completed) {
            return;
        }

        $analysis->update([
            'status' => AnalysisStatus::Processing,
            'error_code' => null,
            'error_message' => null,
        ]);

        $rpsDocument = $analysis->rpsDocument;
        $jobPostings = $analysis->jobPostings;

        // Verify technical prerequisites
        if ($rpsDocument === null
            || $rpsDocument->extraction_status !== ExtractionStatus::Completed
            || $rpsDocument->review_status !== ReviewStatus::Confirmed
            || $jobPostings->isEmpty()
        ) {
            $analysis->update([
                'status' => AnalysisStatus::Failed,
                'error_message' => 'Prasyarat analisis tidak terpenuhi.',
            ]);

            return;
        }

        foreach ($jobPostings as $posting) {
            if ($posting->extraction_status !== ExtractionStatus::Completed) {
                $analysis->update([
                    'status' => AnalysisStatus::Failed,
                    'error_message' => 'Satu atau lebih lowongan belum selesai diekstrak.',
                ]);

                return;
            }
        }

        try {
            // 1. Industry Skills Aggregation (group by skill_id)
            /** @var array<int, array{skill: Skill, representative: JobSkill, demand_count: int, source_job_skill_ids: list<int>}> $aggregatedSkills */
            $aggregatedSkills = [];

            foreach ($jobPostings as $posting) {
                foreach ($posting->jobSkills as $jobSkill) {
                    $skillId = (int) $jobSkill->skill_id;
                    $skillEntry = $jobSkill->skillEntry;

                    if ($skillEntry === null) {
                        continue;
                    }

                    if (! isset($aggregatedSkills[$skillId])) {
                        $aggregatedSkills[$skillId] = [
                            'skill' => $skillEntry,
                            'representative' => $jobSkill,
                            'demand_count' => 1,
                            'posting_ids' => [$posting->id],
                            'source_job_skill_ids' => [$jobSkill->id],
                        ];
                    } else {
                        if (! in_array($posting->id, $aggregatedSkills[$skillId]['posting_ids'], true)) {
                            $aggregatedSkills[$skillId]['posting_ids'][] = $posting->id;
                            $aggregatedSkills[$skillId]['demand_count']++;
                        }

                        $aggregatedSkills[$skillId]['source_job_skill_ids'][] = $jobSkill->id;

                        // Keep the job_skill with longest requirement_text as the representative embedding
                        if (mb_strlen($jobSkill->requirement_text) > mb_strlen($aggregatedSkills[$skillId]['representative']->requirement_text)) {
                            $aggregatedSkills[$skillId]['representative'] = $jobSkill;
                        }
                    }
                }
            }

            // Keep only the skills most requested across the career's postings (ties: lower skill id first).
            uasort($aggregatedSkills, fn (array $a, array $b): int => [$b['demand_count'], $a['skill']->id] <=> [$a['demand_count'], $b['skill']->id]);
            $maxSkills = max(1, (int) config('skillbridge.matching.max_skills', 20));
            $aggregatedSkills = array_slice($aggregatedSkills, 0, $maxSkills, true);

            // 2. Candidate RPS Competencies (with embeddings, excluding rejected)
            $candidates = $rpsDocument->courseSkills
                ->filter(function (CourseSkill $cs): bool {
                    return $cs->review_status !== CompetencyReviewStatus::Rejected
                        && is_array($cs->embedding)
                        && ! empty($cs->embedding);
                })
                ->values();

            // 3a. Judge every industry skill first. Gemini calls run in parallel and outside the database
            // transaction, so no connection is held open while waiting for the provider.
            $judgeItems = [];
            foreach ($aggregatedSkills as $skillId => $agg) {
                $targetEmbedding = is_array($agg['representative']->embedding) ? $agg['representative']->embedding : [];

                $judgeItems[$skillId] = [
                    'skill' => $agg['skill'],
                    'representative' => $agg['representative'],
                    'candidates' => ! empty($targetEmbedding)
                        ? $candidateFinder->findTopK($targetEmbedding, $candidates)
                        : [],
                ];
            }

            $judged = $matchJudge->judgeMany($judgeItems, $analysis);

            // 3b. Save to skill_matches (idempotent)
            $coveredCount = 0;
            $partialCount = 0;
            $missingCount = 0;

            DB::transaction(function () use (
                $analysis,
                $aggregatedSkills,
                $judged,
                &$coveredCount,
                &$partialCount,
                &$missingCount,
            ): void {
                $analysis->skillMatches()->delete();

                foreach ($aggregatedSkills as $skillId => $agg) {
                    $matchResult = $judged[$skillId];

                    SkillMatch::create([
                        'analysis_id' => $analysis->id,
                        'skill_id' => $agg['skill']->id,
                        'demand_count' => $agg['demand_count'],
                        'source_job_skill_ids' => $agg['source_job_skill_ids'],
                        'course_skill_id' => $matchResult->matchedCourseSkillId,
                        'similarity_score' => $matchResult->similarityScore !== null ? round($matchResult->similarityScore, 5) : null,
                        'classification' => $matchResult->classification,
                        'reason' => $matchResult->reason,
                        'evidence' => $matchResult->evidence,
                        'candidates' => $matchResult->candidates,
                        'judged_by' => $matchResult->judgedBy,
                    ]);

                    match ($matchResult->classification) {
                        MatchClassification::Covered => $coveredCount++,
                        MatchClassification::Partial => $partialCount++,
                        MatchClassification::Missing => $missingCount++,
                    };
                }
            });

            $totalSkills = count($aggregatedSkills);
            $coveragePercentage = $totalSkills > 0
                ? round(($coveredCount / $totalSkills) * 100, 2)
                : 0.0;

            $summary = [
                'total_skills' => $totalSkills,
                'covered' => $coveredCount,
                'partial' => $partialCount,
                'missing' => $missingCount,
                'coverage_percentage' => $coveragePercentage,
            ];

            $modelVersions = [
                'judge_model' => config('skillbridge.gemini.flash_model'),
                'embedding_model' => config('skillbridge.gemini.embedding_model'),
                'embedding_dimensions' => config('skillbridge.gemini.embedding_dimensions'),
                'prompt_version' => $matchJudge->promptVersion(),
                'sim_floor' => (float) config('skillbridge.matching.sim_floor', 0.50),
                'sim_high' => (float) config('skillbridge.matching.sim_high', 0.80),
                'top_k' => (int) config('skillbridge.matching.top_k', 3),
                'max_skills' => $maxSkills,
            ];

            $analysis->update([
                'status' => AnalysisStatus::Completed,
                'summary' => $summary,
                'model_versions' => $modelVersions,
                'error_code' => null,
                'error_message' => null,
            ]);

            GenerateRecommendationsJob::dispatch($analysis->id);
        } catch (GeminiRateLimitedException $e) {
            // The per-minute budget did not free up in time. Nothing was saved (transaction rolled back):
            // put the whole job back on the queue instead of storing fake "missing" results.
            $this->releaseForRateLimit($e);
        } catch (GeminiQuotaExhaustedException $e) {
            $analysis->update([
                'status' => AnalysisStatus::Failed,
                'error_code' => GeminiQuotaExhaustedException::CODE,
                'error_message' => $this->quotaMessage($e),
            ]);
        } catch (Throwable $e) {
            $analysis->update([
                'status' => AnalysisStatus::Failed,
                'error_message' => 'Gagal menjalankan analisis kesesuaian skill.',
            ]);

            if (! app()->runningUnitTests()) {
                throw $e;
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $analysis = Analysis::find($this->analysisId);
        if ($analysis !== null && $analysis->status !== AnalysisStatus::Completed) {
            $analysis->update([
                'status' => AnalysisStatus::Failed,
                'error_message' => 'Gagal menjalankan analisis kesesuaian skill.',
            ]);
        }
    }
}
