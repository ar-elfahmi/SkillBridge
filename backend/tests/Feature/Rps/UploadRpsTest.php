<?php

declare(strict_types=1);

namespace Tests\Feature\Rps;

use App\Models\RpsDocument;
use App\Models\User;
use App\Modules\Rps\Jobs\ParseRpsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\PdfFixture;
use Tests\TestCase;

class UploadRpsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Queue::fake();
        $this->user = User::factory()->create();
    }

    private function upload(UploadedFile $file): TestResponse
    {
        return $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/rps', ['file' => $file]);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/rps', ['file' => PdfFixture::pages(1)])->assertUnauthorized();
    }

    public function test_valid_pdf_is_stored_privately_and_parse_job_dispatched(): void
    {
        $file = PdfFixture::pages(3, 'RPS Pemrograman Web.pdf');
        $hash = hash_file('sha256', (string) $file->getRealPath());

        $this->upload($file)
            ->assertStatus(202)
            ->assertJsonPath('data.original_name', 'RPS Pemrograman Web.pdf')
            ->assertJsonPath('data.parse_status', 'pending')
            ->assertJsonPath('data.extraction_status', 'pending')
            ->assertJsonPath('data.review_status', 'unreviewed')
            ->assertJsonPath('data.page_count', 3)
            ->assertJsonPath('data.course', null)
            ->assertJsonMissingPath('data.file_path');

        $document = RpsDocument::sole();
        $this->assertSame($this->user->id, $document->user_id);
        $this->assertSame($hash, $document->file_hash);
        $this->assertSame("rps/{$this->user->id}/{$hash}.pdf", $document->file_path);
        Storage::disk('private')->assertExists($document->file_path);

        Queue::assertPushed(ParseRpsJob::class, fn (ParseRpsJob $job) => $job->rpsDocumentId === $document->id);
    }

    public function test_rejects_non_pdf_content_even_with_pdf_extension(): void
    {
        $fake = UploadedFile::fake()->createWithContent('rps.pdf', 'just plain text, not a pdf');

        $this->upload($fake)
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Berkas harus berupa PDF.');

        $this->assertDatabaseCount('rps_documents', 0);
        Queue::assertNothingPushed();
    }

    public function test_rejects_pdf_content_with_wrong_extension(): void
    {
        $this->upload(PdfFixture::upload(['Halaman 1'], 'rps.txt'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_missing_file_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/rps', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Berkas RPS wajib diunggah.');
    }

    public function test_oversized_file_returns_413(): void
    {
        config(['skillbridge.upload.max_pdf_mb' => 1]);
        $file = PdfFixture::pages(1)->size(1025);

        $this->upload($file)
            ->assertStatus(413)
            ->assertJsonPath('message', 'Ukuran PDF melebihi batas 1 MB.');

        $this->assertDatabaseCount('rps_documents', 0);
    }

    public function test_too_many_pages_returns_422(): void
    {
        config(['skillbridge.upload.max_pages' => 2]);

        $this->upload(PdfFixture::pages(3))
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Jumlah halaman PDF melebihi batas 2 halaman.');

        $this->assertDatabaseCount('rps_documents', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_corrupt_pdf_returns_422_without_internal_details(): void
    {
        $corrupt = UploadedFile::fake()->createWithContent('rps.pdf', "%PDF-1.4\n%garbage without objects\n%%EOF\n");

        $response = $this->upload($corrupt)->assertUnprocessable();

        $this->assertContains($response->json('errors.file.0'), [
            'Berkas harus berupa PDF.',
            'Berkas PDF tidak dapat dibaca.',
        ]);
        $this->assertDatabaseCount('rps_documents', 0);
    }

    public function test_duplicate_upload_returns_409_with_existing_id(): void
    {
        $content = PdfFixture::make(['Halaman 1 RPS']);

        $first = $this->upload(UploadedFile::fake()->createWithContent('a.pdf', $content))->assertStatus(202);

        $this->upload(UploadedFile::fake()->createWithContent('b.pdf', $content))
            ->assertStatus(409)
            ->assertJsonPath('existing_id', $first->json('data.id'))
            ->assertJsonPath('message', 'RPS ini sudah pernah Anda unggah.');

        $this->assertDatabaseCount('rps_documents', 1);
        Queue::assertPushed(ParseRpsJob::class, 1);
    }

    public function test_same_file_from_another_user_is_accepted(): void
    {
        $content = PdfFixture::make(['Halaman 1 RPS']);
        RpsDocument::factory()->create(['file_hash' => hash('sha256', $content)]);

        $this->upload(UploadedFile::fake()->createWithContent('a.pdf', $content))->assertStatus(202);

        $this->assertDatabaseCount('rps_documents', 2);
    }

    public function test_upload_is_rate_limited_per_user(): void
    {
        config(['skillbridge.rate_limits.upload_per_minute' => 1]);

        $this->upload(PdfFixture::upload(['A']))->assertStatus(202);
        $this->upload(PdfFixture::upload(['B']))
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Terlalu banyak unggahan. Coba lagi dalam satu menit.');
    }
}
