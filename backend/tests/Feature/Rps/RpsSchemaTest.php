<?php

declare(strict_types=1);

namespace Tests\Feature\Rps;

use App\Models\Course;
use App\Models\RpsDocument;
use App\Models\RpsPage;
use App\Models\User;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ParseStatus;
use App\Modules\Rps\Enums\ReviewStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RpsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_document_has_default_statuses(): void
    {
        $document = RpsDocument::factory()->create()->fresh();

        $this->assertNotNull($document);
        $this->assertSame(ParseStatus::Pending, $document->parse_status);
        $this->assertSame(ExtractionStatus::Pending, $document->extraction_status);
        $this->assertSame(ReviewStatus::Unreviewed, $document->review_status);
        $this->assertNull($document->reviewed_at);
        $this->assertNull($document->course_id);
    }

    public function test_file_path_is_hidden_from_serialization(): void
    {
        $document = RpsDocument::factory()->create();

        $this->assertArrayNotHasKey('file_path', $document->toArray());
    }

    public function test_same_user_cannot_store_duplicate_file_hash(): void
    {
        $document = RpsDocument::factory()->create();

        $this->expectException(QueryException::class);

        RpsDocument::factory()->create([
            'user_id' => $document->user_id,
            'file_hash' => $document->file_hash,
        ]);
    }

    public function test_different_users_may_upload_same_file(): void
    {
        $document = RpsDocument::factory()->create();

        RpsDocument::factory()->create(['file_hash' => $document->file_hash]);

        $this->assertSame(2, RpsDocument::where('file_hash', $document->file_hash)->count());
    }

    public function test_page_numbers_are_unique_per_document(): void
    {
        $page = RpsPage::factory()->create(['page_number' => 1]);

        $this->expectException(QueryException::class);

        RpsPage::factory()->create(['rps_document_id' => $page->rps_document_id, 'page_number' => 1]);
    }

    public function test_pages_are_ordered_and_cascade_on_document_delete(): void
    {
        $document = RpsDocument::factory()->create();
        RpsPage::factory()->create(['rps_document_id' => $document->id, 'page_number' => 2]);
        RpsPage::factory()->create(['rps_document_id' => $document->id, 'page_number' => 1]);

        $this->assertSame([1, 2], $document->pages->pluck('page_number')->all());

        $document->delete();
        $this->assertDatabaseCount('rps_pages', 0);
    }

    public function test_documents_cascade_on_user_delete(): void
    {
        $document = RpsDocument::factory()->create();

        $document->user->delete();

        $this->assertModelMissing($document);
    }

    public function test_deleting_course_keeps_document_and_nulls_course(): void
    {
        $course = Course::factory()->create();
        $document = RpsDocument::factory()->create(['course_id' => $course->id]);

        $course->delete();

        $this->assertNull($document->fresh()?->course_id);
    }

    public function test_policy_allows_owner_and_hides_from_others(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $document = RpsDocument::factory()->create(['user_id' => $owner->id]);

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($owner)->allows($ability, $document));

            $response = Gate::forUser($other)->inspect($ability, $document);
            $this->assertTrue($response->denied());
            $this->assertSame(404, $response->status());
        }
    }
}
