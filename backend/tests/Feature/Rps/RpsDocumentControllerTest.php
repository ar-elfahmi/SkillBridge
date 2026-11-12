<?php

declare(strict_types=1);

namespace Tests\Feature\Rps;

use App\Models\Course;
use App\Models\RpsDocument;
use App\Models\RpsPage;
use App\Models\User;
use App\Modules\Rps\Enums\ParseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RpsDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->user = User::factory()->create();
    }

    public function test_get_rps_list_requires_authentication(): void
    {
        $this->getJson('/api/rps')->assertUnauthorized();
    }

    public function test_get_rps_list_returns_only_own_documents_ordered_by_descending_id(): void
    {
        $otherUser = User::factory()->create();

        $doc1 = RpsDocument::factory()->create(['user_id' => $this->user->id]);
        $doc2 = RpsDocument::factory()->create(['user_id' => $this->user->id]);
        $otherDoc = RpsDocument::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/rps')
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame($doc2->id, $data[0]['id']);
        $this->assertSame($doc1->id, $data[1]['id']);
    }

    public function test_get_rps_list_returns_empty_array_when_user_has_no_documents(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/rps')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_get_rps_detail_requires_authentication(): void
    {
        $doc = RpsDocument::factory()->create(['user_id' => $this->user->id]);

        $this->getJson("/api/rps/{$doc->id}")->assertUnauthorized();
    }

    public function test_get_rps_detail_returns_404_for_other_users_document(): void
    {
        $otherUser = User::factory()->create();
        $doc = RpsDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/rps/{$doc->id}")
            ->assertNotFound();
    }

    public function test_get_rps_detail_returns_expected_structure(): void
    {
        $course = Course::factory()->create([
            'code' => 'IF101',
            'name' => 'Dasar Pemrograman',
            'credits' => 3,
            'semester' => 1,
            'study_program' => 'Teknik Informatika',
        ]);

        $doc = RpsDocument::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $course->id,
            'parse_status' => ParseStatus::Parsed,
            'detected_metadata' => [
                'code' => 'IF101',
                'name' => 'Dasar Pemrograman',
                'credits' => 3,
                'semester' => 1,
                'study_program' => 'Teknik Informatika',
            ],
            'page_count' => 5,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/rps/{$doc->id}")
            ->assertOk();

        $response->assertJsonPath('data.id', $doc->id)
            ->assertJsonPath('data.parse_status', 'parsed')
            ->assertJsonPath('data.course.code', 'IF101')
            ->assertJsonPath('data.detected_metadata.code', 'IF101')
            ->assertJsonPath('data.detected_metadata.credits', 3)
            ->assertJsonPath('data.page_count', 5)
            ->assertJsonMissingPath('data.file_path');
    }

    public function test_patch_rps_metadata_requires_authentication(): void
    {
        $doc = RpsDocument::factory()->create(['user_id' => $this->user->id]);

        $this->patchJson("/api/rps/{$doc->id}", ['name' => 'Web Dev'])->assertUnauthorized();
    }

    public function test_patch_rps_metadata_returns_404_for_other_users_document(): void
    {
        $otherUser = User::factory()->create();
        $doc = RpsDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/rps/{$doc->id}", ['name' => 'Web Dev'])
            ->assertNotFound();
    }

    public function test_patch_rps_metadata_rejects_empty_payload(): void
    {
        $doc = RpsDocument::factory()->create([
            'user_id' => $this->user->id,
            'parse_status' => ParseStatus::Parsed,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/rps/{$doc->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payload']);
    }

    public function test_patch_rps_metadata_rejects_unknown_fields(): void
    {
        $doc = RpsDocument::factory()->create([
            'user_id' => $this->user->id,
            'parse_status' => ParseStatus::Parsed,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/rps/{$doc->id}", ['invalid_key' => 'hacked'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invalid_key']);
    }

    public function test_patch_rps_metadata_rejects_when_parse_status_is_not_parsed(): void
    {
        $doc = RpsDocument::factory()->create([
            'user_id' => $this->user->id,
            'parse_status' => ParseStatus::Pending,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/rps/{$doc->id}", ['name' => 'Web Dev'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parse_status']);
    }

    public function test_patch_rps_metadata_rejects_when_metadata_already_confirmed(): void
    {
        $doc = RpsDocument::factory()->create([
            'user_id' => $this->user->id,
            'parse_status' => ParseStatus::Parsed,
            'metadata_confirmed_at' => now(),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/rps/{$doc->id}", ['name' => 'Web Dev'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['metadata']);
    }

    public function test_patch_rps_metadata_validates_field_types_and_bounds(): void
    {
        $doc = RpsDocument::factory()->create([
            'user_id' => $this->user->id,
            'parse_status' => ParseStatus::Parsed,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/rps/{$doc->id}", [
                'credits' => -1,
                'semester' => 40000,
                'code' => str_repeat('A', 256),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credits', 'semester', 'code']);
    }

    public function test_patch_rps_metadata_updates_fields_and_preserves_unmentioned_fields(): void
    {
        $doc = RpsDocument::factory()->create([
            'user_id' => $this->user->id,
            'parse_status' => ParseStatus::Parsed,
            'error_message' => 'Gagal mengekstrak metadata secara otomatis.',
            'detected_metadata' => [
                'code' => 'IF101',
                'name' => 'Nama Lama',
                'credits' => 3,
                'semester' => 1,
                'study_program' => 'Teknik Informatika',
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/rps/{$doc->id}", [
                'name' => 'Nama Baru',
                'credits' => 4,
            ])
            ->assertOk();

        $response->assertJsonPath('data.detected_metadata.name', 'Nama Baru')
            ->assertJsonPath('data.detected_metadata.credits', 4)
            ->assertJsonPath('data.detected_metadata.code', 'IF101')
            ->assertJsonPath('data.detected_metadata.semester', 1)
            ->assertJsonPath('data.detected_metadata.study_program', 'Teknik Informatika')
            ->assertJsonPath('data.error_message', null);

        $doc->refresh();
        $this->assertSame('Nama Baru', $doc->detected_metadata['name']);
        $this->assertSame(4, $doc->detected_metadata['credits']);
        $this->assertSame('IF101', $doc->detected_metadata['code']);
        $this->assertNull($doc->error_message);
    }

    public function test_patch_rps_metadata_clears_field_when_explicitly_sent_null(): void
    {
        $doc = RpsDocument::factory()->create([
            'user_id' => $this->user->id,
            'parse_status' => ParseStatus::Parsed,
            'detected_metadata' => [
                'code' => 'IF101',
                'name' => 'Nama',
                'credits' => 3,
                'semester' => 1,
                'study_program' => 'Teknik Informatika',
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/rps/{$doc->id}", [
                'code' => null,
            ])
            ->assertOk();

        $response->assertJsonPath('data.detected_metadata.code', null)
            ->assertJsonPath('data.detected_metadata.name', 'Nama');

        $doc->refresh();
        $this->assertNull($doc->detected_metadata['code']);
    }

    public function test_delete_rps_requires_authentication(): void
    {
        $doc = RpsDocument::factory()->create(['user_id' => $this->user->id]);

        $this->deleteJson("/api/rps/{$doc->id}")->assertUnauthorized();
    }

    public function test_delete_rps_returns_404_for_other_users_document(): void
    {
        $otherUser = User::factory()->create();
        $doc = RpsDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/rps/{$doc->id}")
            ->assertNotFound();
    }

    public function test_delete_rps_deletes_document_pages_and_private_file(): void
    {
        $course = Course::factory()->create();
        $doc = RpsDocument::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $course->id,
            'file_path' => "rps/{$this->user->id}/test.pdf",
        ]);

        RpsPage::factory()->create(['rps_document_id' => $doc->id, 'page_number' => 1]);
        RpsPage::factory()->create(['rps_document_id' => $doc->id, 'page_number' => 2]);

        Storage::disk('private')->put($doc->file_path, 'PDF content');

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/rps/{$doc->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('rps_documents', ['id' => $doc->id]);
        $this->assertDatabaseMissing('rps_pages', ['rps_document_id' => $doc->id]);
        Storage::disk('private')->assertMissing($doc->file_path);

        // Associated Course record is kept
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }
}
