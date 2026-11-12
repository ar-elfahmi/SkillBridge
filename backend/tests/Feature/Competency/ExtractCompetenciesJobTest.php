<?php

declare(strict_types=1);

namespace Tests\Feature\Competency;

use App\Enums\CompetencyOrigin;
use App\Enums\CompetencyReviewStatus;
use App\Enums\CompetencySourceType;
use App\Enums\SkillCategory;
use App\Enums\VerificationStatus;
use App\Models\AiRun;
use App\Models\CourseSkill;
use App\Models\RpsDocument;
use App\Models\RpsPage;
use App\Models\Skill;
use App\Modules\Ai\GeminiClient;
use App\Modules\Competency\Jobs\ExtractCompetenciesJob;
use App\Modules\Competency\Services\EvidenceVerifier;
use App\Modules\Competency\Services\SkillNormalizer;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ParseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ExtractCompetenciesJobTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/test-flash:generateContent';

    private const EMBED_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:batchEmbedContents';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'skillbridge.gemini.api_key' => 'fake-test-key',
            'skillbridge.gemini.flash_model' => 'test-flash',
            'skillbridge.gemini.embedding_model' => 'gemini-embedding-001',
            'skillbridge.gemini.embedding_dimensions' => 768,
            'skillbridge.gemini.timeout_seconds' => 30,
        ]);

        Http::preventStrayRequests();
        Sleep::fake();
    }

    /**
     * @return array<string, mixed>
     */
    private function geminiEmbeddingResponse(int $count = 5): array
    {
        $embeddings = [];
        for ($i = 0; $i < $count; $i++) {
            $embeddings[] = ['values' => array_fill(0, 768, 0.5)];
        }

        return ['embeddings' => $embeddings];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function geminiSuccessResponse(array $payload): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => json_encode($payload, JSON_THROW_ON_ERROR)],
                        ],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 250,
                'candidatesTokenCount' => 120,
                'totalTokenCount' => 370,
            ],
        ];
    }

    public function test_extracts_competencies_normalizes_verifies_evidence_and_saves(): void
    {
        // Setup initial skill dictionary
        $laravelSkill = Skill::create([
            'canonical_name' => 'Laravel Framework',
            'category' => SkillCategory::Framework,
            'aliases' => ['PHP Laravel', 'Laravel'],
        ]);

        $document = RpsDocument::factory()->create([
            'parse_status' => ParseStatus::Parsed,
            'extraction_status' => ExtractionStatus::Processing,
        ]);

        RpsPage::factory()->create([
            'rps_document_id' => $document->id,
            'page_number' => 1,
            'text' => 'CPMK 1: Mahasiswa mampu merancang REST API dengan framework Laravel secara mandiri.',
        ]);

        $mockPayload = [
            'course' => 'Pemrograman Web',
            'competencies' => [
                [
                    'skill' => 'PHP Laravel',
                    'category' => 'framework',
                    'competency' => 'Mampu merancang REST API dengan framework Laravel secara mandiri',
                    'evidence' => 'Mahasiswa mampu merancang REST API dengan framework Laravel',
                    'source_type' => 'CPMK',
                    'source_reference' => 'CPMK 1',
                    'week' => 4,
                    'page' => 1,
                ],
            ],
        ];

        Http::fake([
            self::URL => Http::response($this->geminiSuccessResponse($mockPayload)),
            self::EMBED_URL => Http::response($this->geminiEmbeddingResponse()),
        ]);

        $job = new ExtractCompetenciesJob($document->id);
        $job->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
        );

        $document->refresh();

        $this->assertSame(ExtractionStatus::Completed, $document->extraction_status);
        $this->assertNull($document->error_message);

        $competencies = CourseSkill::where('rps_document_id', $document->id)->get();
        $this->assertCount(1, $competencies);

        $item = $competencies->first();
        $this->assertSame($laravelSkill->id, $item->skill_id);
        $this->assertSame('PHP Laravel', $item->skill);
        $this->assertSame(SkillCategory::Framework, $item->category);
        $this->assertSame('Mampu merancang REST API dengan framework Laravel secara mandiri', $item->competency);
        $this->assertSame('Mahasiswa mampu merancang REST API dengan framework Laravel', $item->evidence);
        $this->assertSame(CompetencySourceType::Cpmk, $item->source_type);
        $this->assertSame('CPMK 1', $item->source_reference);
        $this->assertSame(4, $item->week);
        $this->assertSame(1, $item->page);
        $this->assertSame(VerificationStatus::Verified, $item->verification_status);
        $this->assertSame(CompetencyOrigin::Ai, $item->origin);
        $this->assertSame(CompetencyReviewStatus::Pending, $item->review_status);
        $this->assertFalse($item->user_edited);

        // Verify embedding fields (T4.3)
        $this->assertNotNull($item->embedding);
        $this->assertCount(768, $item->embedding);
        $this->assertSame('gemini-embedding-001', $item->embedding_model);
        $this->assertSame(768, $item->embedding_dimensions);
        $this->assertNotNull($item->text_hash);

        // Verify ai_runs audit was recorded
        $run = AiRun::where('purpose', 'rps_extraction')->first();
        $this->assertNotNull($run);
        $this->assertSame('test-flash', $run->model);
        $this->assertSame('rps-extract-v1', $run->prompt_version);
        $this->assertSame('success', $run->status);

        $embedRun = AiRun::where('purpose', 'embedding')->first();
        $this->assertNotNull($embedRun);
        $this->assertSame('gemini-embedding-001', $embedRun->model);
        $this->assertSame('success', $embedRun->status);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === self::URL
                && str_contains($request['contents'][0]['parts'][0]['text'], '[[PAGE 1]]')
                && str_contains($request['contents'][0]['parts'][0]['text'], 'Mahasiswa mampu merancang REST API');
        });
    }

    public function test_fake_evidence_is_marked_unverified_and_page_set_null(): void
    {
        $document = RpsDocument::factory()->create([
            'parse_status' => ParseStatus::Parsed,
            'extraction_status' => ExtractionStatus::Processing,
        ]);

        RpsPage::factory()->create([
            'rps_document_id' => $document->id,
            'page_number' => 1,
            'text' => 'Silabus Pemrograman Dasar: variabel, percabangan, perulangan, fungsi.',
        ]);

        $mockPayload = [
            'competencies' => [
                [
                    'skill' => 'Quantum Computing',
                    'category' => 'concept',
                    'competency' => 'Mampu membuat program kuantum dengan Qiskit',
                    'evidence' => 'Mahasiswa memahami algoritma Grover dan Shor pada komputer kuantum',
                    'source_type' => 'CPMK',
                    'source_reference' => 'CPMK 99',
                    'week' => 10,
                    'page' => 1,
                ],
            ],
        ];

        Http::fake([
            self::URL => Http::response($this->geminiSuccessResponse($mockPayload)),
            self::EMBED_URL => Http::response($this->geminiEmbeddingResponse()),
        ]);

        $job = new ExtractCompetenciesJob($document->id);
        $job->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
        );

        $document->refresh();
        $this->assertSame(ExtractionStatus::Completed, $document->extraction_status);

        $competency = CourseSkill::where('rps_document_id', $document->id)->first();
        $this->assertNotNull($competency);
        $this->assertSame(VerificationStatus::Unverified, $competency->verification_status);
        $this->assertNull($competency->page);
    }

    public function test_auto_creates_new_skill_when_not_in_dictionary(): void
    {
        $document = RpsDocument::factory()->create([
            'parse_status' => ParseStatus::Parsed,
        ]);

        RpsPage::factory()->create([
            'rps_document_id' => $document->id,
            'page_number' => 1,
            'text' => 'Materi: Pembuatan antarmuka web modern dengan SvelteKit.',
        ]);

        $mockPayload = [
            'competencies' => [
                [
                    'skill' => 'SvelteKit',
                    'category' => 'framework',
                    'competency' => 'Mampu membuat antarmuka web dengan SvelteKit',
                    'evidence' => 'Pembuatan antarmuka web modern dengan SvelteKit',
                    'source_type' => 'material',
                    'source_reference' => null,
                    'week' => 5,
                    'page' => 1,
                ],
            ],
        ];

        Http::fake([
            self::URL => Http::response($this->geminiSuccessResponse($mockPayload)),
            self::EMBED_URL => Http::response($this->geminiEmbeddingResponse()),
        ]);

        $job = new ExtractCompetenciesJob($document->id);
        $job->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
        );

        $newSkill = Skill::where('canonical_name', 'SvelteKit')->first();
        $this->assertNotNull($newSkill);
        $this->assertSame(SkillCategory::Framework, $newSkill->category);
        $this->assertSame([], $newSkill->aliases);

        $savedCompetency = CourseSkill::where('rps_document_id', $document->id)->first();
        $this->assertSame($newSkill->id, $savedCompetency->skill_id);
    }

    public function test_deduplicates_by_skill_id_and_source_reference(): void
    {
        $skill = Skill::create([
            'canonical_name' => 'Docker',
            'category' => SkillCategory::Tool,
            'aliases' => [],
        ]);

        $document = RpsDocument::factory()->create([
            'parse_status' => ParseStatus::Parsed,
        ]);

        RpsPage::factory()->create([
            'rps_document_id' => $document->id,
            'page_number' => 2,
            'text' => 'Tugas 1: Membuat Dockerfile dan kontainerisasi aplikasi web.',
        ]);

        // Two items with same skill_id and same source_reference (one verified, one fake)
        $mockPayload = [
            'competencies' => [
                [
                    'skill' => 'Docker',
                    'category' => 'tool',
                    'competency' => 'Kontainerisasi aplikasi',
                    'evidence' => 'Teks halusinasi yang tidak ada di dokumen',
                    'source_type' => 'assignment',
                    'source_reference' => 'Tugas 1',
                    'week' => 8,
                    'page' => 2,
                ],
                [
                    'skill' => 'Docker',
                    'category' => 'tool',
                    'competency' => 'Membuat Dockerfile dan kontainerisasi aplikasi web',
                    'evidence' => 'Membuat Dockerfile dan kontainerisasi aplikasi web',
                    'source_type' => 'assignment',
                    'source_reference' => 'Tugas 1',
                    'week' => 8,
                    'page' => 2,
                ],
            ],
        ];

        Http::fake([
            self::URL => Http::response($this->geminiSuccessResponse($mockPayload)),
            self::EMBED_URL => Http::response($this->geminiEmbeddingResponse()),
        ]);

        $job = new ExtractCompetenciesJob($document->id);
        $job->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
        );

        $competencies = CourseSkill::where('rps_document_id', $document->id)->get();
        $this->assertCount(1, $competencies);

        // Deduplication preferred the verified item
        $this->assertSame(VerificationStatus::Verified, $competencies->first()->verification_status);
        $this->assertSame(2, $competencies->first()->page);
    }

    public function test_invalid_schema_sets_failed_status(): void
    {
        $document = RpsDocument::factory()->create([
            'parse_status' => ParseStatus::Parsed,
        ]);

        RpsPage::factory()->create([
            'rps_document_id' => $document->id,
            'page_number' => 1,
            'text' => 'Sample RPS text',
        ]);

        // Missing required field 'competency'
        $invalidPayload = [
            'competencies' => [
                [
                    'skill' => 'Git',
                    'category' => 'tool',
                    'evidence' => 'Version control with Git',
                    'source_type' => 'material',
                ],
            ],
        ];

        Http::fake([
            self::URL => Http::response($this->geminiSuccessResponse($invalidPayload)),
        ]);

        $job = new ExtractCompetenciesJob($document->id);
        $job->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
        );

        $document->refresh();
        $this->assertSame(ExtractionStatus::Failed, $document->extraction_status);
        $this->assertSame('Gagal mengekstrak kompetensi dari dokumen RPS. Silakan coba beberapa saat lagi.', $document->error_message);
        $this->assertDatabaseEmpty('course_skills');
    }

    public function test_empty_competencies_completes_successfully(): void
    {
        $document = RpsDocument::factory()->create([
            'parse_status' => ParseStatus::Parsed,
        ]);

        RpsPage::factory()->create([
            'rps_document_id' => $document->id,
            'page_number' => 1,
            'text' => 'Tata tertib perkuliahan dan etika akademik.',
        ]);

        $mockPayload = [
            'competencies' => [],
        ];

        Http::fake([
            self::URL => Http::response($this->geminiSuccessResponse($mockPayload)),
            self::EMBED_URL => Http::response($this->geminiEmbeddingResponse()),
        ]);

        $job = new ExtractCompetenciesJob($document->id);
        $job->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
        );

        $document->refresh();
        $this->assertSame(ExtractionStatus::Completed, $document->extraction_status);
        $this->assertNull($document->error_message);
        $this->assertDatabaseEmpty('course_skills');
    }

    public function test_job_is_idempotent_when_rerun(): void
    {
        $skill = Skill::create([
            'canonical_name' => 'Python',
            'category' => SkillCategory::Language,
            'aliases' => [],
        ]);

        $document = RpsDocument::factory()->create([
            'parse_status' => ParseStatus::Parsed,
            'extraction_status' => ExtractionStatus::Processing,
        ]);

        RpsPage::factory()->create([
            'rps_document_id' => $document->id,
            'page_number' => 1,
            'text' => 'Dasar pemrograman Python untuk sains data.',
        ]);

        $mockPayload = [
            'competencies' => [
                [
                    'skill' => 'Python',
                    'category' => 'language',
                    'competency' => 'Mampu memprogram dengan bahasa Python',
                    'evidence' => 'Dasar pemrograman Python untuk sains data',
                    'source_type' => 'material',
                    'source_reference' => 'Modul 1',
                    'week' => 1,
                    'page' => 1,
                ],
            ],
        ];

        Http::fake([
            self::URL => Http::response($this->geminiSuccessResponse($mockPayload)),
            self::EMBED_URL => Http::response($this->geminiEmbeddingResponse()),
        ]);

        $job = new ExtractCompetenciesJob($document->id);
        $job->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
        );

        $this->assertSame(1, CourseSkill::where('rps_document_id', $document->id)->count());

        // Run second time (e.g. retry or forced re-extract)
        $document->update(['extraction_status' => ExtractionStatus::Processing]);

        $job->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
        );

        // Count should still be 1, not 2
        $this->assertSame(1, CourseSkill::where('rps_document_id', $document->id)->count());
    }

    public function test_failed_hook_sets_safe_error_message(): void
    {
        $document = RpsDocument::factory()->create([
            'parse_status' => ParseStatus::Parsed,
            'extraction_status' => ExtractionStatus::Processing,
        ]);

        (new ExtractCompetenciesJob($document->id))->failed(new \RuntimeException('Connection failed'));

        $document->refresh();
        $this->assertSame(ExtractionStatus::Failed, $document->extraction_status);
        $this->assertSame('Gagal mengekstrak kompetensi dari dokumen RPS. Silakan coba beberapa saat lagi.', $document->error_message);
    }
}
