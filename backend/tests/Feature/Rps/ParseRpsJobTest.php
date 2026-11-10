<?php

declare(strict_types=1);

namespace Tests\Feature\Rps;

use App\Models\RpsDocument;
use App\Models\RpsPage;
use App\Models\User;
use App\Modules\Rps\Enums\ParseStatus;
use App\Modules\Rps\Jobs\ParseRpsJob;
use App\Modules\Rps\Services\PdfInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PdfFixture;
use Tests\TestCase;

class ParseRpsJobTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE_1 = 'Rencana Pembelajaran Semester Pemrograman Web kode 24060511D21 bobot 2 SKS semester 2 Teknik Informatika';

    private const PAGE_2 = 'Sub-CPMK 9 Mahasiswa mampu menerapkan OOP pada pola MVC menggunakan framework PHP dan REST API';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
    }

    /**
     * @param  list<string>  $pageTexts
     */
    private function documentWithPdf(array $pageTexts): RpsDocument
    {
        $document = RpsDocument::factory()->create();
        Storage::disk('private')->put($document->file_path, PdfFixture::make($pageTexts));

        return $document;
    }

    private function parse(RpsDocument $document): RpsDocument
    {
        (new ParseRpsJob($document->id))->handle(app(PdfInspector::class));

        return $document->fresh() ?? $document;
    }

    public function test_digital_pdf_fills_pages_in_order_and_marks_parsed(): void
    {
        $document = $this->parse($this->documentWithPdf([self::PAGE_1, self::PAGE_2]));

        $this->assertSame(ParseStatus::Parsed, $document->parse_status);
        $this->assertSame(2, $document->page_count);
        $this->assertNull($document->error_message);

        $pages = $document->pages;
        $this->assertSame([1, 2], $pages->pluck('page_number')->all());
        $this->assertStringContainsString('Pemrograman Web', $pages[0]->text);
        $this->assertStringContainsString('pola MVC', $pages[1]->text);
    }

    public function test_page_text_whitespace_is_normalized(): void
    {
        $document = $this->parse($this->documentWithPdf(['Sub-CPMK   9     menerapkan    OOP pada pola MVC dengan framework PHP modern']));

        $this->assertStringNotContainsString('  ', $document->pages[0]->text);
    }

    public function test_pdf_without_text_layer_is_marked_needs_ocr(): void
    {
        $document = $this->parse($this->documentWithPdf(['', '']));

        $this->assertSame(ParseStatus::NeedsOcr, $document->parse_status);
        $this->assertStringContainsString('hasil scan', (string) $document->error_message);
        // Pages are still stored so the user/agents can inspect what was found.
        $this->assertSame(2, RpsPage::where('rps_document_id', $document->id)->count());
    }

    public function test_ocr_threshold_comes_from_config(): void
    {
        config(['skillbridge.upload.min_chars_per_page' => 1000]);

        $document = $this->parse($this->documentWithPdf([self::PAGE_1]));

        $this->assertSame(ParseStatus::NeedsOcr, $document->parse_status);
    }

    public function test_corrupt_pdf_is_marked_failed_with_safe_message(): void
    {
        $document = RpsDocument::factory()->create();
        Storage::disk('private')->put($document->file_path, "%PDF-1.4\nnot really a pdf\n%%EOF");

        $document = $this->parse($document);

        $this->assertSame(ParseStatus::Failed, $document->parse_status);
        $this->assertSame('Berkas PDF tidak dapat dibaca. Pastikan berkas tidak rusak atau terkunci.', $document->error_message);
        $this->assertSame(0, RpsPage::count());
    }

    public function test_missing_file_is_marked_failed(): void
    {
        $document = $this->parse(RpsDocument::factory()->create());

        $this->assertSame(ParseStatus::Failed, $document->parse_status);
        $this->assertStringContainsString('tidak ditemukan', (string) $document->error_message);
    }

    public function test_rerun_is_idempotent(): void
    {
        $document = $this->documentWithPdf([self::PAGE_1, self::PAGE_2]);

        $this->parse($document);
        $this->parse($document);

        $this->assertSame(2, RpsPage::where('rps_document_id', $document->id)->count());
    }

    public function test_retry_after_crash_replaces_partial_pages(): void
    {
        $document = $this->documentWithPdf([self::PAGE_1, self::PAGE_2]);
        // Simulate an earlier attempt that died mid-way.
        $document->update(['parse_status' => ParseStatus::Parsing]);
        RpsPage::factory()->create(['rps_document_id' => $document->id, 'page_number' => 1, 'text' => 'stale']);

        $document = $this->parse($document);

        $this->assertSame(ParseStatus::Parsed, $document->parse_status);
        $this->assertStringNotContainsString('stale', $document->pages[0]->text);
        $this->assertSame(2, $document->pages->count());
    }

    public function test_deleted_document_is_ignored(): void
    {
        $document = $this->documentWithPdf([self::PAGE_1]);
        $id = $document->id;
        $document->delete();

        (new ParseRpsJob($id))->handle(app(PdfInspector::class));

        $this->assertSame(0, RpsPage::count());
    }

    public function test_failed_hook_sets_safe_status(): void
    {
        $document = RpsDocument::factory()->create();

        (new ParseRpsJob($document->id))->failed(new \RuntimeException('internal detail'));

        $document->refresh();
        $this->assertSame(ParseStatus::Failed, $document->parse_status);
        $this->assertStringNotContainsString('internal', (string) $document->error_message);
    }

    public function test_queue_contract(): void
    {
        $job = new ParseRpsJob(1);

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 90], $job->backoff());
        $this->assertNotEmpty($job->middleware());
    }

    public function test_upload_then_queue_runs_parsing_end_to_end(): void
    {
        $user = User::factory()->create();

        // QUEUE_CONNECTION=sync in tests: the dispatched job runs immediately.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/rps', ['file' => PdfFixture::upload([self::PAGE_1, self::PAGE_2])])
            ->assertStatus(202);

        $document = RpsDocument::sole();
        $this->assertSame(ParseStatus::Parsed, $document->parse_status);
        $this->assertSame(2, $document->pages()->count());
    }
}
