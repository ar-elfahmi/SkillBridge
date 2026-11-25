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
                                    'text' => json_encode([[
                                        'id' => $matchDocker->id,
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
                                    ]]),
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

    /**
     * @return list<SkillMatch>
     */
    private function missingMatches(int $count): array
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

        $matches = [];
        for ($i = 1; $i <= $count; $i++) {
            $skill = Skill::factory()->create(['canonical_name' => "Skill{$i}"]);
            $matches[] = SkillMatch::create([
                'analysis_id' => $analysis->id,
                'skill_id' => $skill->id,
                'demand_count' => 1,
                'classification' => MatchClassification::Missing,
                'reason' => "Skill{$i} belum dipelajari.",
                'judged_by' => JudgedBy::Rule,
            ]);
        }

        return $matches;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    private function geminiAnswer(array $entries): array
    {
        return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($entries)]]]]]];
    }

    private function runJob(Analysis $analysis): void
    {
        app(GenerateRecommendationsJob::class, ['analysisId' => $analysis->id])->handle(
            app(GeminiClient::class),
            app(PriorityCalculator::class)
        );
    }

    public function test_skills_are_sent_in_batches_instead_of_one_call_each(): void
    {
        Config::set('skillbridge.gemini.recommendation_batch_size', 3);
        $matches = $this->missingMatches(7);

        Http::fake(function ($request) {
            $prompt = (string) ($request->data()['contents'][0]['parts'][0]['text'] ?? '');
            preg_match_all('/"id": (\d+)/', $prompt, $found);

            return Http::response($this->geminiAnswer(array_map(fn (string $id): array => [
                'id' => (int) $id,
                'title' => "Judul {$id}",
                'reason' => 'Alasan',
                'topics' => ['Topik'],
                'learning_keywords' => ['kata kunci'],
            ], $found[1])));
        });

        $this->runJob($matches[0]->analysis);

        Http::assertSentCount(3);
        $this->assertSame(7, Recommendation::where('analysis_id', $matches[0]->analysis_id)->count());
        $this->assertSame('Judul '.$matches[4]->id, Recommendation::where('skill_match_id', $matches[4]->id)->value('title'));
    }

    public function test_foreign_duplicate_and_missing_ids_never_reach_the_wrong_skill(): void
    {
        Config::set('skillbridge.gemini.recommendation_batch_size', 5);
        Config::set('skillbridge.gemini.chunk_retries', 0);
        [$first, $second] = $this->missingMatches(2);

        Http::fake([self::GEMINI_URL => Http::response($this->geminiAnswer([
            ['id' => $first->id, 'title' => 'Judul pertama', 'reason' => 'R', 'topics' => ['T'], 'learning_keywords' => ['K']],
            ['id' => $first->id, 'title' => 'Judul ganda', 'reason' => 'R', 'topics' => ['T'], 'learning_keywords' => ['K']],
            ['id' => 999999, 'title' => 'Id asing', 'reason' => 'R', 'topics' => ['T'], 'learning_keywords' => ['K']],
        ]))]);

        $this->runJob($first->analysis);

        $this->assertSame('Judul pertama', Recommendation::where('skill_match_id', $first->id)->value('title'));
        // The second skill got no valid answer: deterministic fallback, not someone else's text.
        $this->assertSame('Fundamental Skill2', Recommendation::where('skill_match_id', $second->id)->value('title'));
        $this->assertSame(2, Recommendation::where('analysis_id', $first->analysis_id)->count());
    }

    public function test_only_skills_without_an_answer_are_asked_again(): void
    {
        Config::set('skillbridge.gemini.recommendation_batch_size', 5);
        Config::set('skillbridge.gemini.chunk_retries', 2);
        [$first, $second] = $this->missingMatches(2);

        Http::fakeSequence(self::GEMINI_URL)
            ->push($this->geminiAnswer([
                ['id' => $first->id, 'title' => 'Judul A', 'reason' => 'R', 'topics' => ['T'], 'learning_keywords' => ['K']],
            ]))
            ->push($this->geminiAnswer([
                ['id' => $second->id, 'title' => 'Judul B', 'reason' => 'R', 'topics' => ['T'], 'learning_keywords' => ['K']],
            ]));

        $this->runJob($first->analysis);

        Http::assertSentCount(2);
        $recorded = Http::recorded();
        $retryPrompt = (string) ($recorded[1][0]->data()['contents'][0]['parts'][0]['text'] ?? '');
        $this->assertStringContainsString('"id": '.$second->id, $retryPrompt);
        $this->assertStringNotContainsString('"id": '.$first->id.',', $retryPrompt);
        $this->assertSame('Judul A', Recommendation::where('skill_match_id', $first->id)->value('title'));
        $this->assertSame('Judul B', Recommendation::where('skill_match_id', $second->id)->value('title'));
    }

    public function test_daily_quota_keeps_what_exists_and_uses_fallback_for_the_rest(): void
    {
        [$match] = $this->missingMatches(1);

        Http::fake([self::GEMINI_URL => Http::response(['error' => ['details' => [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel']]]]]], 429)]);

        $this->runJob($match->analysis);

        Http::assertSentCount(1);
        $this->assertSame('Fundamental Skill1', Recommendation::where('skill_match_id', $match->id)->value('title'));
    }
}
