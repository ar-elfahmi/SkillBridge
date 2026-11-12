<?php

declare(strict_types=1);

namespace Tests\Feature\Competency;

use App\Enums\CompetencyOrigin;
use App\Enums\CompetencyReviewStatus;
use App\Enums\CompetencySourceType;
use App\Enums\SkillCategory;
use App\Enums\VerificationStatus;
use App\Models\Course;
use App\Models\CourseSkill;
use App\Models\RpsDocument;
use App\Models\Skill;
use App\Models\User;
use App\Modules\Competency\Jobs\ExtractCompetenciesJob;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ParseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CompetencyExtractionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_extract_or_competencies(): void
    {
        $document = RpsDocument::factory()->create();

        $this->postJson("/api/rps/{$document->id}/extract")->assertUnauthorized();
        $this->getJson("/api/rps/{$document->id}/competencies")->assertUnauthorized();
    }

    public function test_non_owner_gets_404_on_extract_and_competencies(): void
    {
        $owner = User::factory()->create(['role' => 'student']);
        $stranger = User::factory()->create(['role' => 'student']);
        $document = RpsDocument::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger)
            ->postJson("/api/rps/{$document->id}/extract")
            ->assertNotFound();

        $this->actingAs($stranger)
            ->getJson("/api/rps/{$document->id}/competencies")
            ->assertNotFound();
    }

    public function test_cannot_extract_if_document_is_not_parsed(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $document = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'parse_status' => ParseStatus::Pending,
            'detected_metadata' => ['code' => 'IF101'],
        ]);

        $this->actingAs($user)
            ->postJson("/api/rps/{$document->id}/extract")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Dokumen RPS belum selesai diparsing atau memerlukan OCR.');
    }

    public function test_cannot_extract_if_metadata_code_is_missing_or_empty(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $document = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'parse_status' => ParseStatus::Parsed,
            'detected_metadata' => ['code' => null, 'name' => 'Pemrograman Web'],
        ]);

        $this->actingAs($user)
            ->postJson("/api/rps/{$document->id}/extract")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_cannot_extract_if_already_processing(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $document = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'parse_status' => ParseStatus::Parsed,
            'extraction_status' => ExtractionStatus::Processing,
            'detected_metadata' => ['code' => 'IF201', 'name' => 'Struktur Data'],
        ]);

        $this->actingAs($user)
            ->postJson("/api/rps/{$document->id}/extract")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Proses ekstraksi kompetensi sedang berjalan.');
    }

    public function test_confirms_metadata_creates_course_and_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => 'student']);
        $document = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'original_name' => 'RPS Web.pdf',
            'parse_status' => ParseStatus::Parsed,
            'extraction_status' => ExtractionStatus::Pending,
            'detected_metadata' => [
                'code' => 'IF301',
                'name' => 'Pemrograman Web Lanjut',
                'credits' => 3,
                'semester' => 5,
                'study_program' => 'Sistem Informasi',
            ],
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/rps/{$document->id}/extract")
            ->assertStatus(202)
            ->assertJsonPath('data.id', $document->id)
            ->assertJsonPath('data.extraction_status', 'processing')
            ->assertJsonPath('data.course.code', 'IF301')
            ->assertJsonPath('data.course.name', 'Pemrograman Web Lanjut')
            ->assertJsonPath('data.course.credits', 3)
            ->assertJsonPath('data.course.semester', 5)
            ->assertJsonPath('data.course.study_program', 'Sistem Informasi');

        $document->refresh();
        $this->assertSame(ExtractionStatus::Processing, $document->extraction_status);
        $this->assertNotNull($document->metadata_confirmed_at);
        $this->assertNotNull($document->course_id);

        $course = Course::where('code', 'IF301')->first();
        $this->assertNotNull($course);
        $this->assertSame('Pemrograman Web Lanjut', $course->name);
        $this->assertSame(3, $course->credits);
        $this->assertSame(5, $course->semester);
        $this->assertSame('Sistem Informasi', $course->study_program);

        Queue::assertPushed(ExtractCompetenciesJob::class, function (ExtractCompetenciesJob $job) use ($document): bool {
            return $job->rpsDocumentId === $document->id;
        });
    }

    public function test_reuses_existing_course_when_code_matches(): void
    {
        Queue::fake();

        $existingCourse = Course::create([
            'code' => 'IF101',
            'name' => 'Algoritma & Pemrograman',
            'credits' => 4,
            'semester' => 1,
            'study_program' => 'Informatika',
        ]);

        $user = User::factory()->create(['role' => 'student']);
        $document = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'parse_status' => ParseStatus::Parsed,
            'detected_metadata' => [
                'code' => 'IF101',
                'name' => 'Nama Berbeda Dari PDF',
            ],
        ]);

        $this->actingAs($user)
            ->postJson("/api/rps/{$document->id}/extract")
            ->assertStatus(202);

        $document->refresh();
        $this->assertSame($existingCourse->id, $document->course_id);
        $this->assertSame(1, Course::where('code', 'IF101')->count());
    }

    public function test_gets_competencies_with_canonical_skill_and_contract_structure(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $document = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'parse_status' => ParseStatus::Parsed,
            'extraction_status' => ExtractionStatus::Completed,
        ]);

        $canonicalSkill = Skill::create([
            'canonical_name' => 'MVC Architecture',
            'category' => SkillCategory::Concept,
            'aliases' => ['MVC'],
        ]);

        $skillWithoutEntry = CourseSkill::create([
            'rps_document_id' => $document->id,
            'skill_id' => null,
            'skill' => 'Unknown Skill',
            'category' => SkillCategory::Other,
            'competency' => 'Mampu mengerti konsep umum',
            'evidence' => 'Konsep umum pemrograman',
            'source_type' => CompetencySourceType::Material,
            'origin' => CompetencyOrigin::Ai,
            'review_status' => CompetencyReviewStatus::Pending,
            'user_edited' => false,
            'source_reference' => null,
            'week' => 2,
            'page' => 1,
            'verification_status' => VerificationStatus::Unverified,
        ]);

        $verifiedSkill = CourseSkill::create([
            'rps_document_id' => $document->id,
            'skill_id' => $canonicalSkill->id,
            'skill' => 'PHP MVC',
            'category' => SkillCategory::Concept,
            'competency' => 'Mampu menerapkan OOP pada pola MVC',
            'evidence' => 'Penerapan OOP pada pola MVC',
            'source_type' => CompetencySourceType::SubCpmk,
            'origin' => CompetencyOrigin::Ai,
            'review_status' => CompetencyReviewStatus::Pending,
            'user_edited' => false,
            'source_reference' => 'Sub-CPMK 9',
            'week' => 7,
            'page' => 3,
            'verification_status' => VerificationStatus::Verified,
        ]);

        $rejectedSkill = CourseSkill::create([
            'rps_document_id' => $document->id,
            'skill_id' => null,
            'skill' => 'Old Skill',
            'category' => SkillCategory::Other,
            'competency' => 'Skill yang ditolak oleh reviewer',
            'evidence' => 'Bukti lama',
            'source_type' => CompetencySourceType::Assignment,
            'origin' => CompetencyOrigin::Ai,
            'review_status' => CompetencyReviewStatus::Rejected,
            'user_edited' => false,
            'source_reference' => 'Tugas 3',
            'week' => 9,
            'page' => 4,
            'verification_status' => VerificationStatus::Verified,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/rps/{$document->id}/competencies")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        // Check verified item matching contract docs/API.md §3 lines 104-126
        $response->assertJsonFragment([
            'id' => $verifiedSkill->id,
            'skill' => 'PHP MVC',
            'canonical_skill' => 'MVC Architecture',
            'category' => 'concept',
            'competency' => 'Mampu menerapkan OOP pada pola MVC',
            'evidence' => 'Penerapan OOP pada pola MVC',
            'source_type' => 'Sub-CPMK',
            'source_reference' => 'Sub-CPMK 9',
            'week' => 7,
            'page' => 3,
            'verification_status' => 'verified',
            'origin' => 'ai',
            'review_status' => 'pending',
            'user_edited' => false,
        ]);

        // Check fallback canonical_skill when skill_id is null
        $response->assertJsonFragment([
            'id' => $skillWithoutEntry->id,
            'skill' => 'Unknown Skill',
            'canonical_skill' => 'Unknown Skill',
            'verification_status' => 'unverified',
        ]);

        // Check rejected competency is still returned per contract
        $response->assertJsonFragment([
            'id' => $rejectedSkill->id,
            'review_status' => 'rejected',
        ]);
    }

    public function test_returns_empty_data_when_document_has_no_competencies(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $document = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'parse_status' => ParseStatus::Parsed,
        ]);

        $this->actingAs($user)
            ->getJson("/api/rps/{$document->id}/competencies")
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
