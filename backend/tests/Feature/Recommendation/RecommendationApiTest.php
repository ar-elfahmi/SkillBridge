<?php

declare(strict_types=1);

namespace Tests\Feature\Recommendation;

use App\Enums\AnalysisStatus;
use App\Enums\JudgedBy;
use App\Enums\MatchClassification;
use App\Enums\RecommendationPriority;
use App\Models\Analysis;
use App\Models\Career;
use App\Models\Course;
use App\Models\Recommendation;
use App\Models\RpsDocument;
use App\Models\Skill;
use App\Models\SkillMatch;
use App\Models\User;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Analysis $analysis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $career = Career::factory()->create();
        $course = Course::factory()->create();

        $rps = RpsDocument::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $course->id,
            'extraction_status' => ExtractionStatus::Completed,
            'review_status' => ReviewStatus::Confirmed,
        ]);

        $this->analysis = Analysis::factory()->create([
            'user_id' => $this->user->id,
            'rps_document_id' => $rps->id,
            'career_id' => $career->id,
            'status' => AnalysisStatus::Completed,
        ]);
    }

    public function test_gets_recommendations_sorted_by_priority_descending(): void
    {
        $skillHigh = Skill::factory()->create(['canonical_name' => 'Docker']);
        $skillLow = Skill::factory()->create(['canonical_name' => 'Postman']);

        $matchHigh = SkillMatch::create([
            'analysis_id' => $this->analysis->id,
            'skill_id' => $skillHigh->id,
            'demand_count' => 2,
            'classification' => MatchClassification::Missing,
            'reason' => 'Missing skill',
            'judged_by' => JudgedBy::Rule,
        ]);

        $matchLow = SkillMatch::create([
            'analysis_id' => $this->analysis->id,
            'skill_id' => $skillLow->id,
            'demand_count' => 1,
            'classification' => MatchClassification::Partial,
            'reason' => 'Partial skill',
            'judged_by' => JudgedBy::Rule,
        ]);

        // Create low priority first, then high priority
        Recommendation::create([
            'analysis_id' => $this->analysis->id,
            'skill_match_id' => $matchLow->id,
            'title' => 'Panduan Postman',
            'reason' => 'Skill partial di RPS.',
            'priority' => RecommendationPriority::Low,
            'details' => ['topics' => ['Testing API'], 'learning_keywords' => ['postman']],
        ]);

        Recommendation::create([
            'analysis_id' => $this->analysis->id,
            'skill_match_id' => $matchHigh->id,
            'title' => 'Panduan Docker',
            'reason' => 'Skill missing di RPS.',
            'priority' => RecommendationPriority::High,
            'details' => ['topics' => ['Container'], 'learning_keywords' => ['docker']],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/analyses/{$this->analysis->id}/recommendations");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.skill', 'Docker')
            ->assertJsonPath('data.0.priority', 'high')
            ->assertJsonPath('data.0.title', 'Panduan Docker')
            ->assertJsonPath('data.1.skill', 'Postman')
            ->assertJsonPath('data.1.priority', 'low')
            ->assertJsonPath('data.1.title', 'Panduan Postman')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'skill',
                        'priority',
                        'title',
                        'reason',
                        'details' => [
                            'topics',
                            'learning_keywords',
                        ],
                    ],
                ],
                'meta' => [
                    'self_declared_skills',
                ],
            ]);
    }

    public function test_returns_404_for_non_owner(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->getJson("/api/analyses/{$this->analysis->id}/recommendations");

        $response->assertStatus(404);
    }

    public function test_returns_422_when_analysis_is_not_completed(): void
    {
        $pendingAnalysis = Analysis::factory()->create([
            'user_id' => $this->user->id,
            'status' => AnalysisStatus::Queued,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/analyses/{$pendingAnalysis->id}/recommendations");

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Analisis belum selesai diproses.',
            ]);
    }
}
