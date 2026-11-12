<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\AiRun;
use App\Models\Career;
use App\Models\CourseSkill;
use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Models\RpsDocument;
use App\Models\Skill;
use App\Modules\Ai\EmbeddingService;
use App\Modules\Ai\Exceptions\GeminiException;
use App\Support\Cosine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:batchEmbedContents';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'skillbridge.gemini.api_key' => 'fake-embedding-key',
            'skillbridge.gemini.embedding_model' => 'gemini-embedding-001',
            'skillbridge.gemini.embedding_dimensions' => 768,
            'skillbridge.gemini.timeout_seconds' => 30,
        ]);

        Http::preventStrayRequests();
        Sleep::fake();
    }

    /**
     * @param  list<list<float>>  $vectors
     * @return array<string, mixed>
     */
    private function geminiEmbeddingBatchResponse(array $vectors): array
    {
        $embeddings = [];
        foreach ($vectors as $v) {
            $embeddings[] = ['values' => $v];
        }

        return ['embeddings' => $embeddings];
    }

    /**
     * @return list<float>
     */
    private function dummyVector(int $dim = 768, float $val = 0.5): array
    {
        return array_fill(0, $dim, $val);
    }

    public function test_embeds_single_text_and_returns_normalized_vector(): void
    {
        $rawVector = $this->dummyVector(768, 1.0);
        Http::fake([
            self::URL => Http::response($this->geminiEmbeddingBatchResponse([$rawVector])),
        ]);

        $service = app(EmbeddingService::class);
        $result = $service->embed('Laravel Framework: Mahasiswa mampu membuat REST API');

        $this->assertCount(768, $result);

        // Verify L2 normalized: sum of squares should equal 1.0
        $normSq = 0.0;
        foreach ($result as $v) {
            $normSq += $v * $v;
        }
        $this->assertEqualsWithDelta(1.0, $normSq, 1e-5);

        // Verify AiRun record
        $run = AiRun::where('purpose', 'embedding')->first();
        $this->assertNotNull($run);
        $this->assertSame('gemini-embedding-001', $run->model);
        $this->assertSame('success', $run->status);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === self::URL
                && $request['requests'][0]['taskType'] === 'SEMANTIC_SIMILARITY'
                && $request['requests'][0]['outputDimensionality'] === 768;
        });
    }

    public function test_embed_batch_handles_multiple_texts(): void
    {
        $v1 = $this->dummyVector(768, 0.2);
        $v2 = $this->dummyVector(768, 0.4);

        Http::fake([
            self::URL => Http::response($this->geminiEmbeddingBatchResponse([$v1, $v2])),
        ]);

        $service = app(EmbeddingService::class);
        $results = $service->embedBatch(['Text 1', 'Text 2']);

        $this->assertCount(2, $results);
        $this->assertCount(768, $results[0]);
        $this->assertCount(768, $results[1]);
    }

    public function test_cache_hit_does_not_call_gemini_api(): void
    {
        $document = RpsDocument::factory()->create();
        $text = 'Git: Menguasai branching dan pull request';
        $hash = hash('sha256', $text);
        $cachedVector = Cosine::l2Normalize($this->dummyVector(768, 0.8));

        CourseSkill::factory()->create([
            'rps_document_id' => $document->id,
            'text_hash' => $hash,
            'embedding_model' => 'gemini-embedding-001',
            'embedding_dimensions' => 768,
            'embedding' => $cachedVector,
        ]);

        $service = app(EmbeddingService::class);
        $result = $service->embed($text);

        $this->assertEqualsWithDelta($cachedVector[0], $result[0], 1e-6);
        $this->assertEqualsWithDelta($cachedVector[100], $result[100], 1e-6);

        // No HTTP request should be sent on cache hit
        Http::assertNothingSent();
    }

    public function test_cross_cache_from_job_skills_works(): void
    {
        $career = Career::factory()->create();
        $posting = JobPosting::factory()->create(['career_id' => $career->id]);
        $text = 'Docker: Containerization with multi-stage builds';
        $hash = hash('sha256', $text);
        $cachedVector = Cosine::l2Normalize($this->dummyVector(768, 0.3));

        JobSkill::factory()->create([
            'job_posting_id' => $posting->id,
            'text_hash' => $hash,
            'embedding_model' => 'gemini-embedding-001',
            'embedding_dimensions' => 768,
            'embedding' => $cachedVector,
        ]);

        $service = app(EmbeddingService::class);
        $result = $service->embed($text);

        $this->assertEqualsWithDelta($cachedVector[0], $result[0], 1e-6);
        Http::assertNothingSent();
    }

    public function test_cache_miss_due_to_different_model_or_dimensions_calls_api(): void
    {
        $document = RpsDocument::factory()->create();
        $text = 'React: Building component tree';
        $hash = hash('sha256', $text);
        $oldVector = Cosine::l2Normalize($this->dummyVector(512, 0.9));

        // Stored with dimension 512 and different model
        CourseSkill::factory()->create([
            'rps_document_id' => $document->id,
            'text_hash' => $hash,
            'embedding_model' => 'old-model',
            'embedding_dimensions' => 512,
            'embedding' => $oldVector,
        ]);

        $newVector = $this->dummyVector(768, 0.4);
        Http::fake([
            self::URL => Http::response($this->geminiEmbeddingBatchResponse([$newVector])),
        ]);

        $service = app(EmbeddingService::class);
        $result = $service->embed($text);

        $this->assertCount(768, $result);
        Http::assertSentCount(1);
    }

    public function test_embed_course_skills_attaches_embeddings_and_hashes(): void
    {
        $document = RpsDocument::factory()->create();
        $skill = Skill::factory()->create(['canonical_name' => 'PostgreSQL']);
        $courseSkill = CourseSkill::factory()->create([
            'rps_document_id' => $document->id,
            'skill_id' => $skill->id,
            'skill' => 'Postgres',
            'competency' => 'Mampu merancang query SQL kompleks',
            'embedding' => null,
            'text_hash' => null,
        ]);

        $rawVector = $this->dummyVector(768, 0.5);
        Http::fake([
            self::URL => Http::response($this->geminiEmbeddingBatchResponse([$rawVector])),
        ]);

        $service = app(EmbeddingService::class);
        $service->embedCourseSkills([$courseSkill], $document);

        $courseSkill->refresh();

        $expectedText = 'PostgreSQL: Mampu merancang query SQL kompleks';
        $expectedHash = hash('sha256', $expectedText);

        $this->assertNotNull($courseSkill->embedding);
        $this->assertCount(768, $courseSkill->embedding);
        $this->assertSame('gemini-embedding-001', $courseSkill->embedding_model);
        $this->assertSame(768, $courseSkill->embedding_dimensions);
        $this->assertSame($expectedHash, $courseSkill->text_hash);
    }

    public function test_embed_job_skills_attaches_embeddings_and_hashes(): void
    {
        $career = Career::factory()->create();
        $posting = JobPosting::factory()->create(['career_id' => $career->id]);
        $skill = Skill::factory()->create(['canonical_name' => 'Kubernetes']);
        $jobSkill = JobSkill::factory()->create([
            'job_posting_id' => $posting->id,
            'skill_id' => $skill->id,
            'skill' => 'K8s',
            'requirement_text' => 'Experience with Kubernetes cluster management',
            'embedding' => null,
            'text_hash' => null,
        ]);

        $rawVector = $this->dummyVector(768, 0.7);
        Http::fake([
            self::URL => Http::response($this->geminiEmbeddingBatchResponse([$rawVector])),
        ]);

        $service = app(EmbeddingService::class);
        $service->embedJobSkills([$jobSkill], $posting);

        $jobSkill->refresh();

        $expectedText = 'Kubernetes: Experience with Kubernetes cluster management';
        $expectedHash = hash('sha256', $expectedText);

        $this->assertNotNull($jobSkill->embedding);
        $this->assertCount(768, $jobSkill->embedding);
        $this->assertSame('gemini-embedding-001', $jobSkill->embedding_model);
        $this->assertSame(768, $jobSkill->embedding_dimensions);
        $this->assertSame($expectedHash, $jobSkill->text_hash);
    }

    public function test_retries_on_transient_failure_and_succeeds(): void
    {
        $vector = $this->dummyVector(768, 0.5);

        Http::fake([
            self::URL => Http::sequence()
                ->push(['error' => 'Too Many Requests'], 429)
                ->push($this->geminiEmbeddingBatchResponse([$vector]), 200),
        ]);

        $service = app(EmbeddingService::class);
        $result = $service->embed('Transient test');

        $this->assertCount(768, $result);
        $this->assertSame(2, AiRun::where('purpose', 'embedding')->count());
        $this->assertSame('failed', AiRun::orderBy('id')->first()->status);
        $this->assertSame('success', AiRun::orderByDesc('id')->first()->status);
    }

    public function test_fails_and_throws_exception_on_permanent_failure(): void
    {
        Http::fake([
            self::URL => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $this->expectException(GeminiException::class);

        $service = app(EmbeddingService::class);
        $service->embed('Fail test');
    }
}
