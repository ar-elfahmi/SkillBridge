<?php

declare(strict_types=1);

namespace Tests\Feature\Matching;

use App\Enums\JudgedBy;
use App\Enums\MatchClassification;
use App\Enums\SkillCategory;
use App\Enums\VerificationStatus;
use App\Models\CourseSkill;
use App\Models\JobSkill;
use App\Models\Skill;
use App\Modules\Ai\Exceptions\GeminiQuotaExhaustedException;
use App\Modules\Ai\Exceptions\GeminiRateLimitedException;
use App\Modules\Matching\Services\MatchJudge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class MatchJudgeTest extends TestCase
{
    use RefreshDatabase;

    private const string GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    private MatchJudge $judge;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('skillbridge.gemini.api_key', 'test-key');
        Config::set('skillbridge.gemini.flash_model', 'gemini-2.5-flash');
        Config::set('skillbridge.gemini.timeout_seconds', 30);
        Config::set('skillbridge.matching.sim_floor', 0.50);

        $this->judge = app(MatchJudge::class);
    }

    public function test_scores_below_sim_floor_return_missing_rule_without_llm_call(): void
    {
        Http::fake();

        $skill = Skill::factory()->create([
            'canonical_name' => 'Docker',
            'category' => SkillCategory::Tool,
        ]);

        $jobSkill = new JobSkill([
            'skill' => 'Docker',
            'requirement_text' => 'Menguasai Docker dan containerization',
            'evidence' => 'Menguasai Docker',
        ]);

        $candidate = new CourseSkill([
            'competency' => 'Dasar sistem operasi',
            'evidence' => 'Pengenalan proses dan thread',
            'verification_status' => VerificationStatus::Verified,
        ]);
        $candidate->id = 10;

        $candidateList = [
            [
                'course_skill' => $candidate,
                'course_skill_id' => 10,
                'score' => 0.42, // Below sim_floor 0.50
            ],
        ];

        $result = $this->judge->judge($skill, $jobSkill, $candidateList);

        $this->assertSame(MatchClassification::Missing, $result->classification);
        $this->assertSame(JudgedBy::Rule, $result->judgedBy);
        $this->assertNull($result->matchedCourseSkillId);
        $this->assertNull($result->evidence);
        $this->assertSame(0.42, $result->similarityScore);

        Http::assertNothingSent();
    }

    public function test_valid_llm_covered_result_passes_guardrails(): void
    {
        Http::fake([
            self::GEMINI_URL => Http::response([
                'candidates' => [
                    [
                        'finishReason' => 'STOP',
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'classification' => 'covered',
                                        'matched_course_skill_id' => 25,
                                        'reason' => 'Mahasiswa mempelajari framework Laravel secara mendalam.',
                                        'evidence' => 'Membangun API menggunakan Laravel',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $skill = Skill::factory()->create([
            'canonical_name' => 'Laravel',
            'category' => SkillCategory::Framework,
            'aliases' => ['Laravel Framework'],
        ]);

        $jobSkill = new JobSkill([
            'skill' => 'Laravel',
            'requirement_text' => 'Pengalaman minimal 2 tahun dengan framework Laravel',
            'evidence' => 'framework Laravel',
        ]);

        $candidate = new CourseSkill([
            'competency' => 'Mampu membuat REST API dengan Laravel',
            'evidence' => 'Membangun API menggunakan Laravel',
            'verification_status' => VerificationStatus::Verified,
        ]);
        $candidate->id = 25;

        $candidateList = [
            [
                'course_skill' => $candidate,
                'course_skill_id' => 25,
                'score' => 0.88,
            ],
        ];

        $result = $this->judge->judge($skill, $jobSkill, $candidateList);

        $this->assertSame(MatchClassification::Covered, $result->classification);
        $this->assertSame(JudgedBy::Llm, $result->judgedBy);
        $this->assertSame(25, $result->matchedCourseSkillId);
        $this->assertSame('Membangun API menggunakan Laravel', $result->evidence);
        $this->assertSame(0.88, $result->similarityScore);
    }

    public function test_guardrail_caps_to_missing_when_candidate_id_invalid(): void
    {
        Http::fake([
            self::GEMINI_URL => Http::response([
                'candidates' => [
                    [
                        'finishReason' => 'STOP',
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'classification' => 'covered',
                                        'matched_course_skill_id' => 9999, // Hallucinated ID not in candidateList
                                        'reason' => 'Cocok dengan kompetensi.',
                                        'evidence' => 'bebas',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $skill = Skill::factory()->create(['canonical_name' => 'Go']);
        $jobSkill = new JobSkill(['requirement_text' => 'Golang dev']);

        $candidate = new CourseSkill([
            'competency' => 'Pemrograman Go',
            'evidence' => 'Pengenalan Go',
            'verification_status' => VerificationStatus::Verified,
        ]);
        $candidate->id = 30;

        $result = $this->judge->judge($skill, $jobSkill, [
            ['course_skill' => $candidate, 'course_skill_id' => 30, 'score' => 0.85],
        ]);

        $this->assertSame(MatchClassification::Missing, $result->classification);
        $this->assertNull($result->matchedCourseSkillId);
        $this->assertNull($result->evidence);
    }

    public function test_guardrail_caps_covered_to_partial_and_nulls_evidence_when_evidence_not_substring(): void
    {
        Http::fake([
            self::GEMINI_URL => Http::response([
                'candidates' => [
                    [
                        'finishReason' => 'STOP',
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'classification' => 'covered',
                                        'matched_course_skill_id' => 40,
                                        'reason' => 'Cocok',
                                        'evidence' => 'Kutipan palsu yang tidak ada di dokumen',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $skill = Skill::factory()->create([
            'canonical_name' => 'PostgreSQL',
            'category' => SkillCategory::Tool,
        ]);
        $jobSkill = new JobSkill(['requirement_text' => 'PostgreSQL']);

        $candidate = new CourseSkill([
            'competency' => 'Basis data relasional menggunakan PostgreSQL',
            'evidence' => 'Praktikum PostgreSQL minggu ke-4',
            'verification_status' => VerificationStatus::Verified,
        ]);
        $candidate->id = 40;

        $result = $this->judge->judge($skill, $jobSkill, [
            ['course_skill' => $candidate, 'course_skill_id' => 40, 'score' => 0.78],
        ]);

        $this->assertSame(MatchClassification::Partial, $result->classification);
        $this->assertNull($result->evidence);
        $this->assertSame(40, $result->matchedCourseSkillId);
    }

    public function test_guardrail_caps_to_partial_when_technology_specific_name_absent(): void
    {
        Http::fake([
            self::GEMINI_URL => Http::response([
                'candidates' => [
                    [
                        'finishReason' => 'STOP',
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'classification' => 'covered',
                                        'matched_course_skill_id' => 50,
                                        'reason' => 'Konsep sesuai',
                                        'evidence' => 'Praktikum membuat REST API',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        // Tech-specific category: Framework
        $skill = Skill::factory()->create([
            'canonical_name' => 'Express.js',
            'category' => SkillCategory::Framework,
            'aliases' => ['ExpressJS', 'Express'],
        ]);
        $jobSkill = new JobSkill(['requirement_text' => 'Express.js']);

        // Candidate mentions REST API, but never Express / ExpressJS
        $candidate = new CourseSkill([
            'competency' => 'Mampu membuat web service berbasis REST',
            'evidence' => 'Praktikum membuat REST API',
            'verification_status' => VerificationStatus::Verified,
        ]);
        $candidate->id = 50;

        $result = $this->judge->judge($skill, $jobSkill, [
            ['course_skill' => $candidate, 'course_skill_id' => 50, 'score' => 0.75],
        ]);

        // Guardrail caps covered to partial because technology name is absent
        $this->assertSame(MatchClassification::Partial, $result->classification);
    }

    public function test_guardrail_caps_to_partial_when_candidate_is_unverified(): void
    {
        Http::fake([
            self::GEMINI_URL => Http::response([
                'candidates' => [
                    [
                        'finishReason' => 'STOP',
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'classification' => 'covered',
                                        'matched_course_skill_id' => 60,
                                        'reason' => 'Cocok',
                                        'evidence' => 'Pengenalan Git',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $skill = Skill::factory()->create([
            'canonical_name' => 'Git',
            'category' => SkillCategory::Tool,
        ]);
        $jobSkill = new JobSkill(['requirement_text' => 'Git version control']);

        $candidate = new CourseSkill([
            'competency' => 'Pengenalan Git version control',
            'evidence' => 'Pengenalan Git',
            'verification_status' => VerificationStatus::Unverified, // UNVERIFIED!
        ]);
        $candidate->id = 60;

        $result = $this->judge->judge($skill, $jobSkill, [
            ['course_skill' => $candidate, 'course_skill_id' => 60, 'score' => 0.82],
        ]);

        // Unverified competency cannot produce covered -> capped to partial
        $this->assertSame(MatchClassification::Partial, $result->classification);
    }

    /**
     * @return array{skill: Skill, representative: JobSkill, candidates: list<array{course_skill: CourseSkill, course_skill_id: int, score: float}>}
     */
    private function judgeItem(string $name, int $candidateId, float $score): array
    {
        $skill = Skill::factory()->create(['canonical_name' => $name, 'category' => SkillCategory::Framework]);
        $candidate = new CourseSkill([
            'competency' => "Mampu memakai {$name}",
            'evidence' => "Praktikum {$name}",
            'verification_status' => VerificationStatus::Verified,
        ]);
        $candidate->id = $candidateId;

        return [
            'skill' => $skill,
            'representative' => new JobSkill(['skill' => $name, 'requirement_text' => "Menguasai {$name}", 'evidence' => $name]),
            'candidates' => [['course_skill' => $candidate, 'course_skill_id' => $candidateId, 'score' => $score]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function judgeAnswer(string $classification, ?int $candidateId, ?string $evidence): array
    {
        return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode(array_filter([
            'classification' => $classification,
            'matched_course_skill_id' => $candidateId,
            'reason' => 'Alasan penilaian.',
            'evidence' => $evidence,
        ], fn ($v) => $v !== null))]]]]]];
    }

    public function test_judge_many_sends_one_request_per_skill_and_keeps_the_keys(): void
    {
        Config::set('skillbridge.gemini.judge_batch_size', 1);

        Http::fake([self::GEMINI_URL => Http::response($this->judgeAnswer('partial', 31, 'Praktikum Alpha'))]);

        $results = $this->judge->judgeMany([
            101 => $this->judgeItem('Alpha', 31, 0.90),
            102 => $this->judgeItem('Beta', 32, 0.20),
            103 => $this->judgeItem('Gamma', 33, 0.90),
        ]);

        // Beta is below the similarity floor: decided by rule, no request.
        Http::assertSentCount(2);
        $this->assertSame([101, 102, 103], array_keys($results));
        $this->assertSame(JudgedBy::Rule, $results[102]->judgedBy);
        $this->assertSame(MatchClassification::Missing, $results[102]->classification);
        $this->assertSame(JudgedBy::Llm, $results[101]->judgedBy);
    }

    public function test_judge_many_retries_only_failed_skills_then_falls_back_to_missing(): void
    {
        Config::set('skillbridge.gemini.judge_batch_size', 1);

        Config::set('skillbridge.gemini.chunk_retries', 1);

        Http::fake(function ($request) {
            $prompt = (string) ($request->data()['contents'][0]['parts'][0]['text'] ?? '');

            return str_contains($prompt, 'Alpha')
                ? Http::response($this->judgeAnswer('partial', 31, 'Praktikum Alpha'))
                : Http::response(['error' => 'Server Error'], 500);
        });

        $results = $this->judge->judgeMany([
            1 => $this->judgeItem('Alpha', 31, 0.90),
            2 => $this->judgeItem('Beta', 32, 0.90),
        ]);

        // Alpha: 1 request. Beta: 1 request + 1 retry round, then the missing fallback.
        $this->assertSame(MatchClassification::Partial, $results[1]->classification);
        $this->assertSame(MatchClassification::Missing, $results[2]->classification);
        $this->assertSame(JudgedBy::Rule, $results[2]->judgedBy);
        $this->assertStringContainsString('Gagal melakukan penilaian', $results[2]->reason);
    }

    public function test_judge_many_throws_the_daily_quota_instead_of_saving_missing(): void
    {
        Http::fake([self::GEMINI_URL => Http::response(['error' => ['details' => [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel']]]]]], 429)]);

        $this->expectException(GeminiQuotaExhaustedException::class);

        $this->judge->judgeMany([1 => $this->judgeItem('Alpha', 31, 0.90)]);
    }

    public function test_judge_many_keeps_waiting_while_the_minute_budget_lets_more_skills_through(): void
    {
        Config::set('skillbridge.gemini.judge_batch_size', 1);

        Sleep::fake();
        Config::set('skillbridge.gemini.jobs.max_rate_limit_waits', 1);

        $calls = ['Alpha' => 0, 'Beta' => 0, 'Gamma' => 0];
        $blockedFirst = ['Alpha' => 0, 'Beta' => 1, 'Gamma' => 2];

        Http::fake(function ($request) use (&$calls, $blockedFirst) {
            $prompt = (string) ($request->data()['contents'][0]['parts'][0]['text'] ?? '');
            foreach ($calls as $name => $count) {
                if (str_contains($prompt, "Menguasai {$name}")) {
                    $calls[$name]++;

                    return $count < $blockedFirst[$name]
                        ? Http::response('Too many', 429, ['Retry-After' => '4'])
                        : Http::response($this->judgeAnswer('partial', 31, "Praktikum {$name}"));
                }
            }

            return Http::response('unexpected', 500);
        });

        $results = $this->judge->judgeMany([
            1 => $this->judgeItem('Alpha', 31, 0.90),
            2 => $this->judgeItem('Beta', 31, 0.90),
            3 => $this->judgeItem('Gamma', 31, 0.90),
        ]);

        // Every round let at least one more skill through, so the single allowed wait is never exhausted.
        $this->assertSame([JudgedBy::Llm, JudgedBy::Llm, JudgedBy::Llm], array_map(fn ($r) => $r->judgedBy, array_values($results)));
        $this->assertSame(['Alpha' => 1, 'Beta' => 2, 'Gamma' => 3], $calls);
        Sleep::assertSequence([Sleep::for(4)->seconds(), Sleep::for(4)->seconds()]);
    }

    public function test_judge_many_gives_up_when_the_minute_budget_never_frees_up(): void
    {
        Sleep::fake();
        Config::set('skillbridge.gemini.jobs.max_rate_limit_waits', 1);
        Http::fake([self::GEMINI_URL => Http::response('Too many', 429, ['Retry-After' => '4'])]);

        $this->expectException(GeminiRateLimitedException::class);

        $this->judge->judgeMany([1 => $this->judgeItem('Alpha', 31, 0.90)]);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    private function judgeBatchAnswer(array $entries): array
    {
        return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($entries)]]]]]];
    }

    public function test_judge_many_judges_several_skills_in_one_prompt_with_per_skill_guardrails(): void
    {
        Config::set('skillbridge.gemini.judge_batch_size', 3);
        Http::fake([self::GEMINI_URL => Http::response($this->judgeBatchAnswer([
            ['id' => 1, 'classification' => 'covered', 'matched_course_skill_id' => 31, 'reason' => 'Cocok.', 'evidence' => 'Praktikum Alpha'],
            // Beta picks Alpha's candidate (31): not one of Beta's own candidates, so the guardrail rejects it.
            ['id' => 2, 'classification' => 'covered', 'matched_course_skill_id' => 31, 'reason' => 'Salah kandidat.', 'evidence' => 'Praktikum Alpha'],
            ['id' => 3, 'classification' => 'missing', 'reason' => 'Tidak ada.'],
        ]))]);

        $results = $this->judge->judgeMany([
            1 => $this->judgeItem('Alpha', 31, 0.90),
            2 => $this->judgeItem('Beta', 32, 0.90),
            3 => $this->judgeItem('Gamma', 33, 0.90),
        ]);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $prompt = (string) ($request->data()['contents'][0]['parts'][0]['text'] ?? '');

            return str_contains($prompt, '<items>') && str_contains($prompt, 'Alpha') && str_contains($prompt, 'Gamma');
        });
        $this->assertSame(MatchClassification::Covered, $results[1]->classification);
        $this->assertSame(31, $results[1]->matchedCourseSkillId);
        $this->assertSame(MatchClassification::Missing, $results[2]->classification);
        $this->assertNull($results[2]->matchedCourseSkillId);
        $this->assertSame(MatchClassification::Missing, $results[3]->classification);
        $this->assertSame('match-judge-v3', $this->judge->promptVersion());
    }

    public function test_judge_many_asks_again_only_for_skills_without_a_valid_answer(): void
    {
        Config::set('skillbridge.gemini.judge_batch_size', 3);
        Config::set('skillbridge.gemini.chunk_retries', 2);

        Http::fakeSequence(self::GEMINI_URL)
            ->push($this->judgeBatchAnswer([
                ['id' => 1, 'classification' => 'partial', 'matched_course_skill_id' => 31, 'reason' => 'Sebagian.', 'evidence' => 'Praktikum Alpha'],
                ['id' => 1, 'classification' => 'covered', 'matched_course_skill_id' => 31, 'reason' => 'Ganda.', 'evidence' => 'Praktikum Alpha'],
                ['id' => 999, 'classification' => 'covered', 'matched_course_skill_id' => 31, 'reason' => 'Id asing.', 'evidence' => 'x'],
            ]))
            // A lone skill left over goes out as a normal single-skill prompt (match-judge-v1).
            ->push($this->judgeAnswer('partial', 32, 'Praktikum Beta'));

        $results = $this->judge->judgeMany([
            1 => $this->judgeItem('Alpha', 31, 0.90),
            2 => $this->judgeItem('Beta', 32, 0.90),
        ]);

        Http::assertSentCount(2);
        $retryPrompt = (string) (Http::recorded()[1][0]->data()['contents'][0]['parts'][0]['text'] ?? '');
        $this->assertStringContainsString('Praktikum Beta', $retryPrompt);
        $this->assertStringNotContainsString('Praktikum Alpha', $retryPrompt);
        // The duplicated "covered" answer is ignored: the first valid one wins.
        $this->assertSame(MatchClassification::Partial, $results[1]->classification);
        $this->assertSame(MatchClassification::Partial, $results[2]->classification);
    }
}
