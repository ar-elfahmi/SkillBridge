<?php

declare(strict_types=1);

namespace Tests\Feature\Recommendation;

use App\Enums\AnalysisStatus;
use App\Enums\JudgedBy;
use App\Enums\MatchClassification;
use App\Enums\RecommendationPriority;
use App\Enums\SkillCategory;
use App\Models\Analysis;
use App\Models\Career;
use App\Models\JobPosting;
use App\Models\Recommendation;
use App\Models\RpsDocument;
use App\Models\Skill;
use App\Models\SkillMatch;
use App\Models\User;
use App\Modules\Ai\GeminiClient;
use App\Modules\Recommendation\Jobs\GenerateRecommendationsJob;
use App\Modules\Recommendation\Services\PriorityCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateRecommendationsJobTest extends TestCase
{
    use RefreshDatabase;

    private const string GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('skillbridge.gemini.api_key', 'test-key');
        Config::set('skillbridge.gemini.flash_model', 'gemini-2.5-flash');
        Config::set('skillbridge.recommendation.priority.high_demand_ratio', 0.5);
    }

    public function test_generates_recommendations_and_strips_urls(): void
    {
        $user = User::factory()->create();
        $career = Career::factory()->create();
        $rps = RpsDocument::factory()->create(['user_id' => $user->id]);

        $job1 = JobPosting::factory()->create(['career_id' => $career->id]);
        $job2 = JobPosting::factory()->create(['career_id' => $career->id]);

        $analysis = Analysis::factory()->create([
            'user_id' => $user->id,
            'rps_document_id' => $rps->id,
            'career_id' => $career->id,
            'status' => AnalysisStatus::Completed,
        ]);
        $analysis->jobPostings()->sync([$job1->id, $job2->id]); // 2 postings

        $dockerSkill = Skill::factory()->create([
            'canonical_name' => 'Docker',
            'category' => SkillCategory::Tool,
        ]);

        $laravelSkill = Skill::factory()->create([
            'canonical_name' => 'Laravel',
            'category' => SkillCategory::Framework,
        ]);

        // Docker is missing, requested by 2/2 jobs (ratio 1.0 >= 0.5) -> priority: high
        $matchDocker = SkillMatch::create([
            'analysis_id' => $analysis->id,
            'skill_id' => $dockerSkill->id,
            'demand_count' => 2,
            'classification' => MatchClassification::Missing,
            'reason' => 'Tidak ditemukan di RPS.',
            'judged_by' => JudgedBy::Rule,
        ]);

        // Laravel is covered -> should NOT generate recommendation
        SkillMatch::create([
            'analysis_id' => $analysis->id,
            'skill_id' => $laravelSkill->id,
            'demand_count' => 2,
            'classification' => MatchClassification::Covered,
            'reason' => 'Tercakup lengkap di CPMK.',
            'judged_by' => JudgedBy::Llm,
        ]);

        // Mock LLM response with hallucinated URLs that must be stripped
        Http::fake([
            self::GEMINI_URL => Http::response([
                'candidates' => [
                    [
                        'finishReason' => 'STOP',
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'title' => 'Panduan Docker untuk Mahasiswa https://docker.com/docs',
                                        'reason' => 'Skill Docker belum ditemukan di RPS. Lihat www.example.com',
                                        'topics' => [
                                            'Container dan Image di https://hub.docker.com',
                                            'Dockerfile Dasar',
                                        ],
                                        'learning_keywords' => [
                                            'docker tutorial',
                                            'docker run command https://docker.com',
                                        ],
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        app(GenerateRecommendationsJob::class, ['analysisId' => $analysis->id])->handle(
            app(GeminiClient::class),
            app(PriorityCalculator::class)
        );

        $recommendations = Recommendation::where('analysis_id', $analysis->id)->get();

        $this->assertCount(1, $recommendations);
        $reco = $recommendations->first();

        $this->assertSame($matchDocker->id, $reco->skill_match_id);
        $this->assertSame(RecommendationPriority::High, $reco->priority);

        // Verify URL stripping guardrail
        $this->assertSame('Panduan Docker untuk Mahasiswa', $reco->title);
        $this->assertSame('Skill Docker belum ditemukan di RPS. Lihat', $reco->reason);

        $this->assertContains('Container dan Image di', $reco->details['topics']);
        $this->assertContains('Dockerfile Dasar', $reco->details['topics']);
        $this->assertContains('docker tutorial', $reco->details['learning_keywords']);
        $this->assertContains('docker run command', $reco->details['learning_keywords']);

        // Assert no URL substring exists anywhere in details
        $json = json_encode($reco->details);
        $this->assertStringNotContainsString('http', (string) $json);
        $this->assertStringNotContainsString('www', (string) $json);
    }

    public function test_handles_gemini_failure_gracefully_with_fallback(): void
    {
        $user = User::factory()->create();
        $career = Career::factory()->create();
        $rps = RpsDocument::factory()->create(['user_id' => $user->id]);

        $job = JobPosting::factory()->create(['career_id' => $career->id]);

        $analysis = Analysis::factory()->create([
            'user_id' => $user->id,
            'rps_document_id' => $rps->id,
            'career_id' => $career->id,
            'status' => AnalysisStatus::Completed,
        ]);
        $analysis->jobPostings()->sync([$job->id]);

        $skill = Skill::factory()->create(['canonical_name' => 'Redis']);

        $match = SkillMatch::create([
            'analysis_id' => $analysis->id,
            'skill_id' => $skill->id,
            'demand_count' => 1,
            'classification' => MatchClassification::Missing,
            'reason' => 'Redis belum dipelajari.',
            'judged_by' => JudgedBy::Rule,
        ]);

        // Mock 500 error from Gemini
        Http::fake([
            self::GEMINI_URL => Http::response(['error' => 'Server Error'], 500),
        ]);

        app(GenerateRecommendationsJob::class, ['analysisId' => $analysis->id])->handle(
            app(GeminiClient::class),
            app(PriorityCalculator::class)
        );

        $recommendations = Recommendation::where('analysis_id', $analysis->id)->get();
        $this->assertCount(1, $recommendations);
        $reco = $recommendations->first();

        $this->assertSame($match->id, $reco->skill_match_id);
        $this->assertSame('Fundamental Redis', $reco->title);
        $this->assertSame(RecommendationPriority::High, $reco->priority);
    }
}
