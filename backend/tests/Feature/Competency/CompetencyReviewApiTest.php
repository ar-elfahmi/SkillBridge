<?php

declare(strict_types=1);

namespace Tests\Feature\Competency;

use App\Enums\CompetencyOrigin;
use App\Enums\CompetencyReviewStatus;
use App\Enums\SkillCategory;
use App\Enums\VerificationStatus;
use App\Models\CourseSkill;
use App\Models\RpsDocument;
use App\Models\Skill;
use App\Models\User;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ParseStatus;
use App\Modules\Rps\Enums\ReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompetencyReviewApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'skillbridge.gemini.api_key' => 'test-key-review',
            'skillbridge.gemini.embedding_model' => 'gemini-embedding-001',
            'skillbridge.gemini.embedding_dimensions' => 4,
        ]);
    }

    public function test_patch_competency_updates_review_status_without_setting_user_edited(): void
    {
        $user = User::factory()->create();
        $document = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'parse_status' => ParseStatus::Parsed,
            'extraction_status' => ExtractionStatus::Completed,
        ]);
        $competency = CourseSkill::factory()->create([
            'rps_document_id' => $document->id,
            'review_status' => CompetencyReviewStatus::Pending,
            'user_edited' => false,
        ]);

        $response = $this->actingAs($user)->patchJson(
            "/api/rps/{$document->id}/competencies/{$competency->id}",
            ['review_status' => 'confirmed']
        );

        $response->assertOk()
            ->assertJsonPath('data.id', $competency->id)
            ->assertJsonPath('data.review_status', 'confirmed')
            ->assertJsonPath('data.user_edited', false);

        $this->assertSame(CompetencyReviewStatus::Confirmed, $competency->fresh()?->review_status);
        $this->assertFalse($competency->fresh()?->user_edited);
    }

    public function test_patch_competency_edits_skill_and_competency_normalizes_and_re_embeds(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embeddings' => [
                    ['values' => [0.6, 0.8, 0.0, 0.0]],
                ],
            ], 200),
        ]);

        $laravel = Skill::factory()->create([
            'canonical_name' => 'Laravel Framework',
            'category' => SkillCategory::Framework,
            'aliases' => ['PHP Laravel', 'Laravel'],
        ]);

        $user = User::factory()->create();
        $document = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'parse_status' => ParseStatus::Parsed,
            'extraction_status' => ExtractionStatus::Completed,
        ]);
        $competency = CourseSkill::factory()->create([
            'rps_document_id' => $document->id,
            'skill' => 'PHP Dasar',
            'competency' => 'Membuat skrip PHP sederhana.',
            'evidence' => 'Pembuatan skrip PHP pada minggu 2',
            'review_status' => CompetencyReviewStatus::Pending,
            'user_edited' => false,
            'text_hash' => 'old-hash',
        ]);

        $response = $this->actingAs($user)->patchJson(
            "/api/rps/{$document->id}/competencies/{$competency->id}",
            [
                'review_status' => 'confirmed',
                'skill' => 'PHP Laravel',
                'competency' => 'Membangun aplikasi web dengan arsitektur MVC Laravel.',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.skill', 'PHP Laravel')
            ->assertJsonPath('data.canonical_skill', 'Laravel Framework')
            ->assertJsonPath('data.competency', 'Membangun aplikasi web dengan arsitektur MVC Laravel.')
            ->assertJsonPath('data.evidence', 'Pembuatan skrip PHP pada minggu 2')
            ->assertJsonPath('data.review_status', 'confirmed')
            ->assertJsonPath('data.user_edited', true);

        $fresh = $competency->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame($laravel->id, $fresh->skill_id);
        $this->assertTrue($fresh->user_edited);
        $this->assertNotSame('old-hash', $fresh->text_hash);
        $this->assertNotEmpty($fresh->embedding);
    }

    public function test_patch_competency_rejects_evidence_modification_and_non_owner(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $document = RpsDocument::factory()->create([
            'user_id' => $owner->id,
            'extraction_status' => ExtractionStatus::Completed,
        ]);
        $competency = CourseSkill::factory()->create([
            'rps_document_id' => $document->id,
        ]);

        // Rejects evidence tampering (Rule 8)
        $this->actingAs($owner)->patchJson(
            "/api/rps/{$document->id}/competencies/{$competency->id}",
            ['evidence' => 'Kutipan palsu baru']
        )->assertStatus(422);

        // Stranger gets 404
        $this->actingAs($stranger)->patchJson(
            "/api/rps/{$document->id}/competencies/{$competency->id}",
            ['review_status' => 'confirmed']
        )->assertNotFound();
    }

    public function test_post_competency_adds_user_origin_competency_with_null_evidence_and_unverified_status(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embeddings' => [
                    ['values' => [1.0, 0.0, 0.0, 0.0]],
                ],
            ], 200),
        ]);

        $docker = Skill::factory()->create([
            'canonical_name' => 'Docker',
            'category' => SkillCategory::Tool,
            'aliases' => ['Docker Container'],
        ]);

        $user = User::factory()->create();
        $document = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'parse_status' => ParseStatus::Parsed,
            'extraction_status' => ExtractionStatus::Completed,
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/rps/{$document->id}/competencies",
            [
                'skill' => 'Docker Container',
                'competency' => 'Membungkus aplikasi web dalam container Docker.',
            ]
        );

        $response->assertCreated()
            ->assertJsonPath('data.skill', 'Docker Container')
            ->assertJsonPath('data.canonical_skill', 'Docker')
            ->assertJsonPath('data.origin', 'user')
            ->assertJsonPath('data.source_type', 'user')
            ->assertJsonPath('data.evidence', null)
            ->assertJsonPath('data.verification_status', 'unverified')
            ->assertJsonPath('data.review_status', 'pending');

        $created = CourseSkill::where('rps_document_id', $document->id)->sole();
        $this->assertSame($docker->id, $created->skill_id);
        $this->assertSame(CompetencyOrigin::User, $created->origin);
        $this->assertSame(VerificationStatus::Unverified, $created->verification_status);
        $this->assertNull($created->evidence);
        $this->assertNotEmpty($created->embedding);
    }

    public function test_delete_competency_allows_user_origin_and_rejects_ai_origin(): void
    {
        $user = User::factory()->create();
        $document = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'extraction_status' => ExtractionStatus::Completed,
        ]);

        $aiCompetency = CourseSkill::factory()->create([
            'rps_document_id' => $document->id,
            'origin' => CompetencyOrigin::Ai,
        ]);

        $userCompetency = CourseSkill::factory()->create([
            'rps_document_id' => $document->id,
            'origin' => CompetencyOrigin::User,
            'source_type' => 'user',
            'evidence' => null,
        ]);

        // AI competency cannot be deleted; must use review_status = rejected
        $this->actingAs($user)
            ->deleteJson("/api/rps/{$document->id}/competencies/{$aiCompetency->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('course_skills', ['id' => $aiCompetency->id]);

        // User-added competency can be deleted
        $this->actingAs($user)
            ->deleteJson("/api/rps/{$document->id}/competencies/{$userCompetency->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('course_skills', ['id' => $userCompetency->id]);
    }

    public function test_confirm_review_validates_prerequisites_and_confirms_document(): void
    {
        $user = User::factory()->create();
        $document = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'parse_status' => ParseStatus::Parsed,
            'extraction_status' => ExtractionStatus::Completed,
            'review_status' => ReviewStatus::Unreviewed,
        ]);

        $c1 = CourseSkill::factory()->create([
            'rps_document_id' => $document->id,
            'review_status' => CompetencyReviewStatus::Confirmed,
        ]);
        $c2 = CourseSkill::factory()->create([
            'rps_document_id' => $document->id,
            'review_status' => CompetencyReviewStatus::Pending,
        ]);

        // Fails with 422 while c2 is still pending
        $this->actingAs($user)
            ->postJson("/api/rps/{$document->id}/confirm-review")
            ->assertStatus(422);

        $this->assertSame(ReviewStatus::Unreviewed, $document->fresh()?->review_status);

        // Reject c2, then confirm-review succeeds
        $c2->update(['review_status' => CompetencyReviewStatus::Rejected]);

        $response = $this->actingAs($user)
            ->postJson("/api/rps/{$document->id}/confirm-review");

        $response->assertOk()
            ->assertJsonPath('data.id', $document->id)
            ->assertJsonPath('data.review_status', 'confirmed');

        $freshDoc = $document->fresh();
        $this->assertSame(ReviewStatus::Confirmed, $freshDoc?->review_status);
        $this->assertNotNull($freshDoc?->reviewed_at);
    }

    public function test_confirm_review_supports_confirm_all_pending_flag(): void
    {
        $user = User::factory()->create();
        $document = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'parse_status' => ParseStatus::Parsed,
            'extraction_status' => ExtractionStatus::Completed,
            'review_status' => ReviewStatus::Unreviewed,
        ]);

        $c1 = CourseSkill::factory()->create([
            'rps_document_id' => $document->id,
            'review_status' => CompetencyReviewStatus::Pending,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/rps/{$document->id}/confirm-review", [
                'confirm_all_pending' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.review_status', 'confirmed');

        $this->assertSame(CompetencyReviewStatus::Confirmed, $c1->fresh()?->review_status);
        $this->assertSame(ReviewStatus::Confirmed, $document->fresh()?->review_status);
    }
}
