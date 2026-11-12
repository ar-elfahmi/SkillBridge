<?php

declare(strict_types=1);

namespace Tests\Feature\Industry;

use App\Enums\SkillCategory;
use App\Models\AiRun;
use App\Models\Career;
use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Models\Skill;
use App\Modules\Ai\EmbeddingService;
use App\Modules\Ai\GeminiClient;
use App\Modules\Competency\Services\EvidenceVerifier;
use App\Modules\Competency\Services\SkillNormalizer;
use App\Modules\Industry\Jobs\ExtractJobSkillsJob;
use App\Modules\Rps\Enums\ExtractionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ExtractJobSkillsJobTest extends TestCase
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
                'promptTokenCount' => 200,
                'candidatesTokenCount' => 100,
                'totalTokenCount' => 300,
            ],
        ];
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

    public function test_extracts_job_skills_verifies_evidence_normalizes_and_embeds(): void
    {
        $career = Career::factory()->create();
        $posting = JobPosting::factory()->create([
            'career_id' => $career->id,
            'description' => 'We are seeking a Backend Engineer with solid experience in Laravel and Redis to build scalable web APIs.',
            'qualifications' => 'Must have 3+ years of experience in PHP Laravel Framework.',
            'extraction_status' => ExtractionStatus::Pending,
        ]);

        $laravelSkill = Skill::create([
            'canonical_name' => 'Laravel Framework',
            'category' => SkillCategory::Framework,
            'aliases' => ['PHP Laravel', 'Laravel'],
        ]);

        $mockPayload = [
            'skills' => [
                [
                    'skill' => 'PHP Laravel',
                    'category' => 'framework',
                    'requirement_text' => 'Solid experience in Laravel to build scalable web APIs',
                    'evidence' => 'experience in Laravel and Redis to build scalable web APIs',
                ],
                [
                    'skill' => 'Redis',
                    'category' => 'tool',
                    'requirement_text' => 'Experience using Redis for caching and high throughput',
                    'evidence' => 'solid experience in Laravel and Redis',
                ],
            ],
        ];

        Http::fake([
            self::URL => Http::response($this->geminiSuccessResponse($mockPayload)),
            self::EMBED_URL => Http::response($this->geminiEmbeddingResponse(5)),
        ]);

        $job = new ExtractJobSkillsJob($posting->id);
        $job->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
            app(EmbeddingService::class),
        );

        $posting->refresh();
        $this->assertSame(ExtractionStatus::Completed, $posting->extraction_status);

        $skills = JobSkill::where('job_posting_id', $posting->id)->get();
        $this->assertCount(2, $skills);

        $laravelItem = $skills->firstWhere('skill', 'PHP Laravel');
        $this->assertNotNull($laravelItem);
        $this->assertSame($laravelSkill->id, $laravelItem->skill_id);
        $this->assertSame('Solid experience in Laravel to build scalable web APIs', $laravelItem->requirement_text);
        $this->assertNotNull($laravelItem->embedding);
        $this->assertCount(768, $laravelItem->embedding);
        $this->assertSame('gemini-embedding-001', $laravelItem->embedding_model);
        $this->assertSame(768, $laravelItem->embedding_dimensions);
        $this->assertNotNull($laravelItem->text_hash);

        $redisItem = $skills->firstWhere('skill', 'Redis');
        $this->assertNotNull($redisItem);
        $this->assertNotNull($redisItem->embedding);

        // Verify AiRun records
        $this->assertTrue(AiRun::where('purpose', 'job_extraction')->exists());
        $this->assertTrue(AiRun::where('purpose', 'embedding')->exists());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === self::URL
                && str_contains($request['contents'][0]['parts'][0]['text'], 'Backend Engineer with solid experience in Laravel');
        });
    }

    public function test_fake_evidence_is_discarded(): void
    {
        $career = Career::factory()->create();
        $posting = JobPosting::factory()->create([
            'career_id' => $career->id,
            'description' => 'Hiring Python Django Developer for fintech project.',
            'qualifications' => null,
            'extraction_status' => ExtractionStatus::Pending,
        ]);

        $mockPayload = [
            'skills' => [
                [
                    'skill' => 'Blockchain',
                    'category' => 'concept',
                    'requirement_text' => 'Must understand smart contracts and Solidity',
                    'evidence' => 'Experience building smart contracts on Ethereum', // Hallucinated
                ],
            ],
        ];

        Http::fake([
            self::URL => Http::response($this->geminiSuccessResponse($mockPayload)),
            self::EMBED_URL => Http::response($this->geminiEmbeddingResponse(5)),
        ]);

        $job = new ExtractJobSkillsJob($posting->id);
        $job->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
            app(EmbeddingService::class),
        );

        $posting->refresh();
        $this->assertSame(ExtractionStatus::Completed, $posting->extraction_status);
        $this->assertDatabaseEmpty('job_skills');
    }

    public function test_auto_creates_new_skill_when_not_in_dictionary(): void
    {
        $career = Career::factory()->create();
        $posting = JobPosting::factory()->create([
            'career_id' => $career->id,
            'description' => 'We use Rust for low-level systems programming.',
            'extraction_status' => ExtractionStatus::Pending,
        ]);

        $mockPayload = [
            'skills' => [
                [
                    'skill' => 'Rust',
                    'category' => 'language',
                    'requirement_text' => 'Rust for low-level systems programming',
                    'evidence' => 'We use Rust for low-level systems programming',
                ],
            ],
        ];

        Http::fake([
            self::URL => Http::response($this->geminiSuccessResponse($mockPayload)),
            self::EMBED_URL => Http::response($this->geminiEmbeddingResponse(5)),
        ]);

        $job = new ExtractJobSkillsJob($posting->id);
        $job->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
            app(EmbeddingService::class),
        );

        $rustSkill = Skill::where('canonical_name', 'Rust')->first();
        $this->assertNotNull($rustSkill);
        $this->assertSame(SkillCategory::Language, $rustSkill->category);

        $jobSkill = JobSkill::where('job_posting_id', $posting->id)->first();
        $this->assertSame($rustSkill->id, $jobSkill->skill_id);
    }

    public function test_deduplicates_by_skill_id(): void
    {
        $career = Career::factory()->create();
        $posting = JobPosting::factory()->create([
            'career_id' => $career->id,
            'description' => 'Docker is required. Containerization with Docker for microservices.',
            'extraction_status' => ExtractionStatus::Pending,
        ]);

        $dockerSkill = Skill::create([
            'canonical_name' => 'Docker',
            'category' => SkillCategory::Tool,
            'aliases' => [],
        ]);

        $mockPayload = [
            'skills' => [
                [
                    'skill' => 'Docker',
                    'category' => 'tool',
                    'requirement_text' => 'Docker is required',
                    'evidence' => 'Docker is required',
                ],
                [
                    'skill' => 'Docker',
                    'category' => 'tool',
                    'requirement_text' => 'Containerization with Docker for microservices and CI/CD pipelines',
                    'evidence' => 'Containerization with Docker for microservices',
                ],
            ],
        ];

        Http::fake([
            self::URL => Http::response($this->geminiSuccessResponse($mockPayload)),
            self::EMBED_URL => Http::response($this->geminiEmbeddingResponse(5)),
        ]);

        $job = new ExtractJobSkillsJob($posting->id);
        $job->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
            app(EmbeddingService::class),
        );

        $skills = JobSkill::where('job_posting_id', $posting->id)->get();
        $this->assertCount(1, $skills);
        // Kept the longer requirement_text
        $this->assertSame('Containerization with Docker for microservices and CI/CD pipelines', $skills->first()->requirement_text);
    }

    public function test_invalid_schema_sets_failed_status(): void
    {
        $career = Career::factory()->create();
        $posting = JobPosting::factory()->create([
            'career_id' => $career->id,
            'description' => 'Sample job posting description.',
            'extraction_status' => ExtractionStatus::Pending,
        ]);

        $invalidPayload = [
            'skills' => [
                [
                    'skill' => 'Python',
                    // Missing requirement_text and evidence
                ],
            ],
        ];

        Http::fake([
            self::URL => Http::response($this->geminiSuccessResponse($invalidPayload)),
        ]);

        $job = new ExtractJobSkillsJob($posting->id);
        $job->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
            app(EmbeddingService::class),
        );

        $posting->refresh();
        $this->assertSame(ExtractionStatus::Failed, $posting->extraction_status);
        $this->assertDatabaseEmpty('job_skills');
    }

    public function test_idempotent_rerun_replaces_previous_skills(): void
    {
        $career = Career::factory()->create();
        $posting = JobPosting::factory()->create([
            'career_id' => $career->id,
            'description' => 'Golang developer needed for high concurrency microservices.',
            'extraction_status' => ExtractionStatus::Pending,
        ]);

        $mockPayload = [
            'skills' => [
                [
                    'skill' => 'Golang',
                    'category' => 'language',
                    'requirement_text' => 'Golang developer for high concurrency',
                    'evidence' => 'Golang developer needed for high concurrency microservices',
                ],
            ],
        ];

        Http::fake([
            self::URL => Http::response($this->geminiSuccessResponse($mockPayload)),
            self::EMBED_URL => Http::response($this->geminiEmbeddingResponse(5)),
        ]);

        $job = new ExtractJobSkillsJob($posting->id);
        $job->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
            app(EmbeddingService::class),
        );

        $this->assertSame(1, JobSkill::where('job_posting_id', $posting->id)->count());

        // Re-run
        $posting->update(['extraction_status' => ExtractionStatus::Processing]);
        $job->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
            app(EmbeddingService::class),
        );

        $this->assertSame(1, JobSkill::where('job_posting_id', $posting->id)->count());
    }

    public function test_failed_hook_sets_failed_status(): void
    {
        $career = Career::factory()->create();
        $posting = JobPosting::factory()->create([
            'career_id' => $career->id,
            'extraction_status' => ExtractionStatus::Processing,
        ]);

        (new ExtractJobSkillsJob($posting->id))->failed(new \RuntimeException('Connection failed'));

        $posting->refresh();
        $this->assertSame(ExtractionStatus::Failed, $posting->extraction_status);
    }
}
