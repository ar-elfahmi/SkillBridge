<?php

declare(strict_types=1);

namespace App\Modules\Ai;

use App\Models\AiRun;
use App\Models\CourseSkill;
use App\Models\JobSkill;
use App\Modules\Ai\Exceptions\GeminiException;
use App\Support\Cosine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use JsonException;

class EmbeddingService
{
    /**
     * @var array<string, list<float>> In-memory cache keyed by "model:dimensions:hash"
     */
    private array $memoryCache = [];

    public function getModel(): string
    {
        $model = config('skillbridge.gemini.embedding_model', 'gemini-embedding-001');

        return is_string($model) && trim($model) !== '' ? trim($model) : 'gemini-embedding-001';
    }

    public function getDimensions(): int
    {
        $dim = config('skillbridge.gemini.embedding_dimensions', 768);

        return is_int($dim) && $dim > 0 ? $dim : 768;
    }

    public function computeHash(string $text): string
    {
        return hash('sha256', trim($text));
    }

    public function clearMemoryCache(): void
    {
        $this->memoryCache = [];
    }

    /**
     * Embed a single text string, returning an L2-normalized float vector.
     *
     * @return list<float>
     */
    public function embed(string $text, ?Model $subject = null): array
    {
        $batch = $this->embedBatch([$text], $subject);

        return $batch[0] ?? [];
    }

    /**
     * Embed multiple text strings in batch.
     * Reuses cached vectors across course_skills and job_skills.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts, ?Model $subject = null): array
    {
        if ($texts === []) {
            return [];
        }

        $model = $this->getModel();
        $dimensions = $this->getDimensions();

        /** @var array<int, list<float>> $results */
        $results = [];
        /** @var array<int, string> $uncachedTexts */
        $uncachedTexts = [];
        /** @var array<string, list<int>> $hashToIndices */
        $hashToIndices = [];

        foreach ($texts as $index => $text) {
            $trimmed = trim($text);
            $hash = $this->computeHash($trimmed);
            $cacheKey = "{$model}:{$dimensions}:{$hash}";

            // 1. Check in-memory cache
            if (isset($this->memoryCache[$cacheKey])) {
                $results[$index] = $this->memoryCache[$cacheKey];

                continue;
            }

            // 2. Check cross-table database cache (course_skills and job_skills)
            $dbCached = $this->lookupDbCache($hash, $model, $dimensions);
            if ($dbCached !== null) {
                $this->memoryCache[$cacheKey] = $dbCached;
                $results[$index] = $dbCached;

                continue;
            }

            // Mark for API embedding
            $uncachedTexts[$index] = $trimmed;
            $hashToIndices[$hash][] = $index;
        }

        if ($uncachedTexts === []) {
            ksort($results);

            return array_values($results);
        }

        // Deduplicate texts for API call to save tokens and quota
        $uniqueTexts = array_values(array_unique($uncachedTexts));

        // Call Gemini batch embedding API in chunks of up to 100
        $chunks = array_chunk($uniqueTexts, 100);
        foreach ($chunks as $chunk) {
            $vectors = $this->callGeminiBatchEmbed($chunk, $model, $dimensions, $subject);

            foreach ($chunk as $chunkIdx => $chunkText) {
                $vector = $vectors[$chunkIdx];
                $hash = $this->computeHash($chunkText);
                $cacheKey = "{$model}:{$dimensions}:{$hash}";

                $this->memoryCache[$cacheKey] = $vector;

                if (isset($hashToIndices[$hash])) {
                    foreach ($hashToIndices[$hash] as $originalIndex) {
                        $results[$originalIndex] = $vector;
                    }
                }
            }
        }

        ksort($results);

        return array_values($results);
    }

    /**
     * Embed and attach vector embeddings to CourseSkill models.
     * Text format: "{canonical_skill}: {competency}" (AI_PIPELINE.md §4).
     *
     * @param  iterable<CourseSkill>  $courseSkills
     */
    public function embedCourseSkills(iterable $courseSkills, ?Model $subject = null): void
    {
        $items = [];
        $texts = [];

        foreach ($courseSkills as $courseSkill) {
            $canonical = $courseSkill->skillEntry?->canonical_name ?? $courseSkill->skill;
            $text = "{$canonical}: {$courseSkill->competency}";
            $items[] = [
                'model' => $courseSkill,
                'text' => $text,
                'hash' => $this->computeHash($text),
            ];
            $texts[] = $text;
        }

        if ($items === []) {
            return;
        }

        $vectors = $this->embedBatch($texts, $subject);

        $modelName = $this->getModel();
        $dimensions = $this->getDimensions();

        DB::transaction(function () use ($items, $vectors, $modelName, $dimensions): void {
            foreach ($items as $idx => $item) {
                $item['model']->update([
                    'embedding' => $vectors[$idx],
                    'embedding_model' => $modelName,
                    'embedding_dimensions' => $dimensions,
                    'text_hash' => $item['hash'],
                ]);
            }
        });
    }

    /**
     * Embed and attach vector embeddings to JobSkill models.
     * Text format: "{canonical_skill}: {requirement_text}" (AI_PIPELINE.md §4).
     *
     * @param  iterable<JobSkill>  $jobSkills
     */
    public function embedJobSkills(iterable $jobSkills, ?Model $subject = null): void
    {
        $items = [];
        $texts = [];

        foreach ($jobSkills as $jobSkill) {
            $canonical = $jobSkill->skillEntry?->canonical_name ?? $jobSkill->skill;
            $text = "{$canonical}: {$jobSkill->requirement_text}";
            $items[] = [
                'model' => $jobSkill,
                'text' => $text,
                'hash' => $this->computeHash($text),
            ];
            $texts[] = $text;
        }

        if ($items === []) {
            return;
        }

        $vectors = $this->embedBatch($texts, $subject);

        $modelName = $this->getModel();
        $dimensions = $this->getDimensions();

        DB::transaction(function () use ($items, $vectors, $modelName, $dimensions): void {
            foreach ($items as $idx => $item) {
                $item['model']->update([
                    'embedding' => $vectors[$idx],
                    'embedding_model' => $modelName,
                    'embedding_dimensions' => $dimensions,
                    'text_hash' => $item['hash'],
                ]);
            }
        });
    }

    /**
     * Cross-table lookup in course_skills and job_skills.
     *
     * @return list<float>|null
     */
    private function lookupDbCache(string $hash, string $model, int $dimensions): ?array
    {
        // 1. Check course_skills
        $cached = CourseSkill::where('text_hash', $hash)
            ->where('embedding_model', $model)
            ->where('embedding_dimensions', $dimensions)
            ->whereNotNull('embedding')
            ->value('embedding');

        // 2. Check job_skills if not found
        if ($cached === null) {
            $cached = JobSkill::where('text_hash', $hash)
                ->where('embedding_model', $model)
                ->where('embedding_dimensions', $dimensions)
                ->whereNotNull('embedding')
                ->value('embedding');
        }

        if ($cached === null) {
            return null;
        }

        if (is_string($cached)) {
            $cached = json_decode($cached, true);
        }

        if (! is_array($cached) || empty($cached)) {
            return null;
        }

        return array_map(fn ($val): float => (float) $val, $cached);
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    private function callGeminiBatchEmbed(array $texts, string $model, int $dimensions, ?Model $subject): array
    {
        $key = config('skillbridge.gemini.api_key');
        $timeout = (int) config('skillbridge.gemini.timeout_seconds', 60);

        if (! is_string($key) || trim($key) === '') {
            throw new GeminiException('Kunci API Gemini belum dikonfigurasi.');
        }

        $requests = [];
        $cleanModel = ltrim($model, 'models/');

        foreach ($texts as $text) {
            $requests[] = [
                'model' => "models/{$cleanModel}",
                'content' => [
                    'parts' => [
                        ['text' => $text],
                    ],
                ],
                'taskType' => 'SEMANTIC_SIMILARITY',
                'outputDimensionality' => $dimensions,
            ];
        }

        $payload = ['requests' => $requests];

        try {
            $inputHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            throw new InvalidArgumentException('Parameter embedding tidak valid.');
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $started = hrtime(true);
            $response = null;
            $data = null;
            $error = 'connection_error';

            try {
                $response = Http::withHeaders(['x-goog-api-key' => $key])
                    ->acceptJson()
                    ->asJson()
                    ->timeout($timeout)
                    ->connectTimeout(min(10, $timeout))
                    ->withoutRedirecting()
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$cleanModel}:batchEmbedContents", $payload);

                $data = $response->json();
                if ($response->successful()) {
                    if (is_array($data) && isset($data['embeddings']) && is_array($data['embeddings'])) {
                        $error = null;
                    } else {
                        $error = 'invalid_response';
                    }
                } else {
                    $error = 'http_'.$response->status();
                }
            } catch (ConnectionException) {
                $error = 'connection_error';
            }

            AiRun::create([
                'purpose' => 'embedding',
                'model' => $model,
                'prompt_version' => null,
                'input_hash' => $inputHash,
                'status' => $error === null ? 'success' : 'failed',
                'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'usage' => null,
                'error' => $error,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
            ]);

            if ($error === null && is_array($data) && isset($data['embeddings']) && is_array($data['embeddings'])) {
                $embeddings = $data['embeddings'];
                $resultVectors = [];

                foreach ($embeddings as $idx => $item) {
                    $values = $item['values'] ?? null;
                    if (! is_array($values) || empty($values)) {
                        throw new GeminiException('Respons embedding tidak mengandung vektor nilai.');
                    }

                    // L2 normalize the vector
                    $resultVectors[] = Cosine::l2Normalize($values);
                }

                return $resultVectors;
            }

            $retryable = $response === null || in_array($response->status(), [408, 429], true) || $response->serverError();
            if (! $retryable || $attempt === 3) {
                throw new GeminiException;
            }

            Sleep::for(2 ** ($attempt - 1))->seconds();
        }

        throw new GeminiException;
    }
}
