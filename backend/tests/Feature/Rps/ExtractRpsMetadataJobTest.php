<?php

declare(strict_types=1);

namespace Tests\Feature\Rps;

use App\Models\AiRun;
use App\Models\Course;
use App\Models\RpsDocument;
use App\Models\RpsPage;
use App\Modules\Ai\GeminiClient;
use App\Modules\Rps\Enums\ParseStatus;
use App\Modules\Rps\Jobs\ExtractRpsMetadataJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ExtractRpsMetadataJobTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/test-flash:generateContent';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'skillbridge.gemini.api_key' => 'fake-test-key',
            'skillbridge.gemini.flash_model' => 'test-flash',
            'skillbridge.gemini.timeout_seconds' => 30,
        ]);

        Http::preventStrayRequests();
        Sleep::fake();
    }

    private function geminiSuccessResponse(array $metadata): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => json_encode($metadata, JSON_THROW_ON_ERROR)],
                        ],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 150,
                'candidatesTokenCount' => 35,
                'totalTokenCount' => 185,
            ],
        ];
    }

    public function test_extracts_metadata_from_pages_and_saves_to_document(): void
    {
        $document = RpsDocument::factory()->create(['parse_status' => ParseStatus::Parsed]);
        RpsPage::factory()->create([
            'rps_document_id' => $document->id,
            'page_number' => 1,
            'text' => 'RPS Pemrograman Web kode IF202 3 SKS Semester 4 Teknik Informatika',
        ]);

        $mockMetadata = [
            'code' => 'IF202',
            'name' => 'Pemrograman Web',
            'credits' => 3,
            'semester' => 4,
            'study_program' => 'Teknik Informatika',
        ];

        Http::fake([
            self::URL => Http::response($this->geminiSuccessResponse($mockMetadata)),
        ]);

        (new ExtractRpsMetadataJob($document->id))->handle(app(GeminiClient::class));

        $document->refresh();

        $this->assertNotNull($document->detected_metadata);
        $this->assertSame('IF202', $document->detected_metadata['code']);
        $this->assertSame('Pemrograman Web', $document->detected_metadata['name']);
        $this->assertSame(3, $document->detected_metadata['credits']);
        $this->assertSame(4, $document->detected_metadata['semester']);
        $this->assertSame('Teknik Informatika', $document->detected_metadata['study_program']);
        $this->assertNull($document->error_message);

        // Verify ai_runs audit was recorded
        $run = AiRun::where('purpose', 'rps_metadata')->first();
        $this->assertNotNull($run);
        $this->assertSame('test-flash', $run->model);
        $this->assertSame('rps-metadata-v1', $run->prompt_version);
        $this->assertSame('success', $run->status);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === self::URL
                && str_contains($request['contents'][0]['parts'][0]['text'], 'IF202');
        });
    }

    public function test_handles_null_values_in_extracted_metadata(): void
    {
        $document = RpsDocument::factory()->create(['parse_status' => ParseStatus::Parsed]);
        RpsPage::factory()->create([
            'rps_document_id' => $document->id,
            'page_number' => 1,
            'text' => 'Mata kuliah tidak bernama',
        ]);

        $mockMetadata = [
            'code' => null,
            'name' => null,
            'credits' => null,
            'semester' => null,
            'study_program' => null,
        ];

        Http::fake([
            self::URL => Http::response($this->geminiSuccessResponse($mockMetadata)),
        ]);

        (new ExtractRpsMetadataJob($document->id))->handle(app(GeminiClient::class));

        $document->refresh();

        $this->assertSame($mockMetadata, $document->detected_metadata);
        $this->assertNull($document->error_message);
    }

    public function test_skips_when_parse_status_is_not_parsed(): void
    {
        $document = RpsDocument::factory()->create(['parse_status' => ParseStatus::Pending]);
        RpsPage::factory()->create(['rps_document_id' => $document->id, 'page_number' => 1, 'text' => 'Sample']);

        (new ExtractRpsMetadataJob($document->id))->handle(app(GeminiClient::class));

        $this->assertNull($document->fresh()->detected_metadata);
        Http::assertNothingSent();
    }

    public function test_skips_when_metadata_already_confirmed(): void
    {
        $course = Course::factory()->create();
        $document = RpsDocument::factory()->create([
            'parse_status' => ParseStatus::Parsed,
            'course_id' => $course->id,
            'metadata_confirmed_at' => now(),
        ]);
        RpsPage::factory()->create(['rps_document_id' => $document->id, 'page_number' => 1, 'text' => 'Sample']);

        (new ExtractRpsMetadataJob($document->id))->handle(app(GeminiClient::class));

        $this->assertNull($document->fresh()->detected_metadata);
        Http::assertNothingSent();
    }

    public function test_skips_when_detected_metadata_already_exists(): void
    {
        $document = RpsDocument::factory()->create([
            'parse_status' => ParseStatus::Parsed,
            'detected_metadata' => ['code' => 'EXISTING', 'name' => 'Existing', 'credits' => 3, 'semester' => 1, 'study_program' => null],
        ]);
        RpsPage::factory()->create(['rps_document_id' => $document->id, 'page_number' => 1, 'text' => 'Sample']);

        (new ExtractRpsMetadataJob($document->id))->handle(app(GeminiClient::class));

        $this->assertSame('EXISTING', $document->fresh()->detected_metadata['code']);
        Http::assertNothingSent();
    }

    public function test_failed_hook_sets_safe_error_message(): void
    {
        $document = RpsDocument::factory()->create(['parse_status' => ParseStatus::Parsed]);

        (new ExtractRpsMetadataJob($document->id))->failed(new \RuntimeException('Connection failed'));

        $document->refresh();
        $this->assertNull($document->detected_metadata);
        $this->assertSame('Gagal mengekstrak metadata secara otomatis. Silakan lengkapi metadata secara manual.', $document->error_message);
    }
}
