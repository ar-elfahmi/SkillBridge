<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\AnalysisStatus;
use App\Enums\CompetencyReviewStatus;
use App\Enums\VerificationStatus;
use App\Models\AiRun;
use App\Models\Analysis;
use App\Models\Career;
use App\Models\JobPosting;
use App\Models\RpsDocument;
use App\Models\RpsPage;
use App\Models\User;
use App\Modules\Ai\EmbeddingService;
use App\Modules\Ai\GeminiClient;
use App\Modules\Ai\OfflineAiEngine;
use App\Modules\Competency\Jobs\ExtractCompetenciesJob;
use App\Modules\Industry\Jobs\ExtractJobSkillsJob;
use App\Modules\Matching\Jobs\RunAnalysisJob;
use App\Modules\Recommendation\Jobs\GenerateRecommendationsJob;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ParseStatus;
use App\Modules\Rps\Enums\ReviewStatus;
use App\Modules\Rps\Jobs\ExtractRpsMetadataJob;
use App\Support\Cosine;
use Database\Seeders\CareerSeeder;
use Database\Seeders\SkillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class OfflineAiEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Sleep::fake();

        $this->seed([SkillSeeder::class, CareerSeeder::class]);
    }

    public function test_full_end_to_end_pipeline_runs_completely_offline_without_api_key_or_http_calls(): void
    {
        Http::fake();

        config([
            'skillbridge.gemini.mode' => 'offline',
            'skillbridge.gemini.api_key' => null,
            'skillbridge.gemini.flash_model' => null,
            'skillbridge.gemini.embedding_dimensions' => 64,
        ]);

        $user = User::factory()->create();
        $document = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'parse_status' => ParseStatus::Parsed,
            'extraction_status' => ExtractionStatus::Pending,
            'review_status' => ReviewStatus::Unreviewed,
        ]);

        RpsPage::factory()->create([
            'rps_document_id' => $document->id,
            'page_number' => 1,
            'text' => "Mata Kuliah: Pemrograman Web Lanjut\nKode MK: IF301\nBobot: 3 SKS\nSemester: 5\nProgram Studi: Teknik Informatika\nCPMK-1: Mahasiswa mampu menerapkan pemrograman PHP dan arsitektur MVC menggunakan Laravel.",
        ]);
        RpsPage::factory()->create([
            'rps_document_id' => $document->id,
            'page_number' => 2,
            'text' => 'Sub-CPMK-2: Mahasiswa mampu merancang REST API dan mengelola basis data PostgreSQL. Minggu 6: Praktikum penggunaan Git untuk kolaborasi kode.',
        ]);

        // 1. Metadata extraction offline
        app()->call([new ExtractRpsMetadataJob($document->id), 'handle']);
        $document->refresh();

        $this->assertNotNull($document->detected_metadata);
        $this->assertSame('IF301', $document->detected_metadata['code']);
        $this->assertSame('Pemrograman Web Lanjut', $document->detected_metadata['name']);
        $this->assertSame(3, $document->detected_metadata['credits']);
        $this->assertSame(5, $document->detected_metadata['semester']);

        // 2. Competency extraction + embedding offline
        app()->call([new ExtractCompetenciesJob($document->id), 'handle']);
        $document->refresh();

        $this->assertSame(ExtractionStatus::Completed, $document->extraction_status);
        $this->assertGreaterThanOrEqual(4, $document->courseSkills()->count());

        // Every extracted competency has verified substring evidence and 64-dim embedding
        foreach ($document->courseSkills as $cs) {
            $this->assertSame(VerificationStatus::Verified, $cs->verification_status);
            $this->assertNotNull($cs->embedding);
            $this->assertCount(64, $cs->embedding);
        }

        // Confirm review
        $document->courseSkills()->update(['review_status' => CompetencyReviewStatus::Confirmed]);
        $document->update(['review_status' => ReviewStatus::Confirmed, 'reviewed_at' => now()]);

        // 3. Job skill extraction offline
        $career = Career::where('slug', 'backend-developer')->firstOrFail();
        $jobPosting = JobPosting::factory()->create([
            'career_id' => $career->id,
            'created_by' => $user->id,
            'title' => 'Backend Engineer',
            'company' => 'PT Nusantara',
            'description' => 'Merancang REST API menggunakan Laravel dan mengelola basis data PostgreSQL. Menyiapkan container Docker dan caching Redis.',
            'qualifications' => 'Menguasai PHP dan Git.',
            'extraction_status' => ExtractionStatus::Pending,
        ]);

        app()->call([new ExtractJobSkillsJob($jobPosting->id), 'handle']);
        $jobPosting->refresh();

        $this->assertSame(ExtractionStatus::Completed, $jobPosting->extraction_status);
        $this->assertGreaterThanOrEqual(5, $jobPosting->jobSkills()->count());

        // 4. Matching & Analysis offline
        $analysis = Analysis::factory()->create([
            'user_id' => $user->id,
            'rps_document_id' => $document->id,
            'career_id' => $career->id,
            'status' => AnalysisStatus::Queued,
        ]);
        $analysis->jobPostings()->attach([$jobPosting->id]);

        app()->call([new RunAnalysisJob($analysis->id), 'handle']);
        $analysis->refresh();

        $this->assertSame(AnalysisStatus::Completed, $analysis->status);
        $this->assertGreaterThan(0, $analysis->summary['covered']);
        $this->assertGreaterThan(0, $analysis->summary['missing']);

        // 5. Recommendations offline
        app()->call([new GenerateRecommendationsJob($analysis->id), 'handle']);

        $this->assertGreaterThan(0, $analysis->recommendations()->count());

        // Zero external HTTP requests were made throughout the entire pipeline!
        Http::assertNothingSent();
        $this->assertGreaterThan(0, AiRun::where('model', 'offline-local-v1')->count());
    }

    public function test_fallback_mode_automatically_recovers_when_gemini_returns_429_rate_limit(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'Quota exceeded']], 429),
        ]);

        config([
            'skillbridge.gemini.mode' => 'fallback',
            'skillbridge.gemini.api_key' => 'test-key',
            'skillbridge.gemini.flash_model' => 'gemini-flash-test',
            'skillbridge.gemini.timeout_seconds' => 10,
            'skillbridge.gemini.embedding_dimensions' => 32,
        ]);

        $client = app(GeminiClient::class);
        $envelope = $client->generateContent(
            'rps_metadata',
            "<document>Mata Kuliah: Algoritma dan Pemrograman\nKode MK: IF101\nBobot: 4 SKS</document>",
            'rps-meta-v1'
        );

        $text = $envelope['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $decoded = json_decode($text, true);

        $this->assertIsArray($decoded);
        $this->assertSame('IF101', $decoded['code']);
        $this->assertSame(4, $decoded['credits']);

        $embeddingService = app(EmbeddingService::class);
        $vector = $embeddingService->embed('Laravel Framework: Membangun REST API');

        $this->assertCount(32, $vector);
    }

    public function test_semantic_feature_hashing_produces_high_similarity_for_same_skill_and_low_for_unrelated(): void
    {
        $engine = new OfflineAiEngine;

        $v1 = $engine->embedText('Laravel Framework: Merancang dan membangun REST API menggunakan Laravel', 256);
        $v2 = $engine->embedText('Laravel Framework: Membangun layanan RESTful API dengan framework PHP Laravel', 256);
        $vUnrelated = $engine->embedText('Power BI: Membuat dashboard visualisasi data penjualan dan laporan Excel', 256);

        $simRelated = Cosine::similarity($v1, $v2);
        $simUnrelated = Cosine::similarity($v1, $vUnrelated);

        $this->assertGreaterThan(0.65, $simRelated);
        $this->assertLessThan(0.30, $simUnrelated);
    }
}
