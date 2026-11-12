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
use App\Modules\Matching\Services\MatchJudge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
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
}
