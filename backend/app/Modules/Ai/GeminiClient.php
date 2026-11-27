<?php

declare(strict_types=1);

namespace App\Modules\Ai;

use App\Models\AiRun;
use App\Modules\Ai\Exceptions\GeminiException;
use App\Modules\Ai\Exceptions\GeminiQuotaExhaustedException;
use App\Modules\Ai\Exceptions\GeminiRateLimitedException;
use App\Modules\Ai\Services\GeminiQuota;
use App\Modules\Ai\Services\RateLimitExceptionFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use JsonException;
use SensitiveParameter;

final class GeminiClient
{
    private const PURPOSES = ['rps_metadata', 'rps_extraction', 'job_extraction', 'match_judge', 'recommendation', 'career_suggestion'];

    private const THINKING_LEVELS = ['minimal', 'low', 'medium', 'high'];

    public function __construct(private readonly ?GeminiQuota $quota = null) {}

    /**
     * Return the provider envelope; callers must validate generated JSON against their schema.
     *
     * Every real HTTP attempt first reserves one request in the "flash" budget (RPM and RPD).
     * A 429 is never retried here: it is surfaced as GeminiRateLimitedException (wait and retry later)
     * or GeminiQuotaExhaustedException (daily budget gone) so the caller can release or fail the job.
     * In "fallback"/"auto" mode those cases switch to the local engine instead of throwing.
     *
     * @param  array<string, mixed>  $responseSchema
     * @return array<string, mixed>
     *
     * @throws GeminiRateLimitedException
     * @throws GeminiQuotaExhaustedException
     * @throws GeminiException
     */
    public function generateContent(
        string $purpose,
        #[SensitiveParameter] string $prompt,
        ?string $promptVersion = null,
        ?Model $subject = null,
        array $responseSchema = [],
    ): array {
        $this->assertValid($purpose, $prompt, $promptVersion, $subject);

        $payload = $this->buildPayload($prompt, $responseSchema);
        $inputHash = $this->hashPayload($payload);

        $mode = $this->mode();
        if ($mode === 'offline') {
            return $this->runOffline($purpose, $prompt, $promptVersion, $inputHash, $subject);
        }

        $provider = $this->providerConfig();
        if ($provider === null) {
            if ($this->canFallBack($mode)) {
                return $this->runOffline($purpose, $prompt, $promptVersion, $inputHash, $subject);
            }

            throw new GeminiException;
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $this->quota()->reserveOrFail(GeminiQuota::BUCKET_FLASH);
            } catch (GeminiRateLimitedException|GeminiQuotaExhaustedException $exception) {
                if ($this->canFallBack($mode)) {
                    return $this->runOffline($purpose, $prompt, $promptVersion, $inputHash, $subject);
                }

                throw $exception;
            }

            $started = hrtime(true);
            $response = null;
            $error = 'connection_error';

            try {
                $response = Http::withHeaders(['x-goog-api-key' => $provider['key']])
                    ->acceptJson()
                    ->asJson()
                    ->timeout($provider['timeout'])
                    ->connectTimeout(min(10, $provider['timeout']))
                    ->withoutRedirecting()
                    ->post($this->endpoint($provider['model']), $payload);

                $error = $this->classify($response);
            } catch (ConnectionException) {
                // Never retain provider exceptions: they can contain request data.
            }

            $this->recordRun($purpose, $provider['model'], $promptVersion, $inputHash, $error, $started, $response, $subject);

            if ($error === null) {
                /** @var array<string, mixed> */
                return $response?->json() ?? [];
            }

            if ($response !== null && $response->status() === 429) {
                if ($this->canFallBack($mode)) {
                    return $this->runOffline($purpose, $prompt, $promptVersion, $inputHash, $subject);
                }

                throw RateLimitExceptionFactory::fromResponse($response, $this->quota(), GeminiQuota::BUCKET_FLASH);
            }

            $retryable = $response === null || in_array($response->status(), [408], true) || $response->serverError();

            if (! $retryable || $attempt === 3) {
                if ($this->canFallBack($mode)) {
                    return $this->runOffline($purpose, $prompt, $promptVersion, $inputHash, $subject);
                }

                throw new GeminiException;
            }

            Sleep::for(2 ** ($attempt - 1))->seconds();
        }

        throw new GeminiException;
    }

    /**
     * Send several independent requests at the same time (one Http::pool, bounded concurrency).
     *
     * Each id is attempted once. The result holds, per id, either the provider envelope or the
     * exception explaining why that request has no result (rate limited, quota exhausted, failure),
     * so the caller can retry only the failed ids. One reservation covers the whole group.
     *
     * @param  array<string, array{prompt: string, prompt_version?: ?string, subject?: ?Model, response_schema?: array<string, mixed>}>  $requests
     * @return array<string, array<string, mixed>|GeminiException>
     *
     * @throws GeminiException when the provider configuration is invalid and no local fallback is allowed
     */
    public function generateContentConcurrent(string $purpose, array $requests, ?int $concurrency = null): array
    {
        if ($requests === []) {
            return [];
        }

        $payloads = [];
        $hashes = [];
        foreach ($requests as $id => $request) {
            $this->assertValid($purpose, $request['prompt'], $request['prompt_version'] ?? null, $request['subject'] ?? null);
            $payloads[$id] = $this->buildPayload($request['prompt'], $request['response_schema'] ?? []);
            $hashes[$id] = $this->hashPayload($payloads[$id]);
        }

        $mode = $this->mode();
        $provider = $mode === 'offline' ? null : $this->providerConfig();

        if ($mode === 'offline' || $provider === null) {
            if ($mode !== 'offline' && ! $this->canFallBack($mode)) {
                throw new GeminiException;
            }

            return $this->runOfflineGroup($purpose, $requests, $hashes);
        }

        $results = [];
        $width = max(1, min($concurrency ?? (int) config('skillbridge.gemini.max_concurrent', 3), count($requests)));

        foreach (array_chunk(array_keys($requests), $this->groupSize(count($requests))) as $group) {
            try {
                $this->quota()->reserveOrFail(GeminiQuota::BUCKET_FLASH, count($group));
            } catch (GeminiRateLimitedException|GeminiQuotaExhaustedException $exception) {
                foreach ($group as $id) {
                    $results[$id] = $this->canFallBack($mode)
                        ? $this->runOffline($purpose, $requests[$id]['prompt'], $requests[$id]['prompt_version'] ?? null, $hashes[$id], $requests[$id]['subject'] ?? null)
                        : $exception;
                }

                continue;
            }

            $started = hrtime(true);

            $responses = Http::pool(fn (Pool $pool): array => array_map(
                fn (string $id) => $pool->as($id)
                    ->withHeaders(['x-goog-api-key' => $provider['key']])
                    ->acceptJson()
                    ->asJson()
                    ->timeout($provider['timeout'])
                    ->connectTimeout(min(10, $provider['timeout']))
                    ->withoutRedirecting()
                    ->post($this->endpoint($provider['model']), $payloads[$id]),
                $group,
            ), $width);

            foreach ($group as $id) {
                $response = $responses[$id] ?? null;
                $response = $response instanceof Response ? $response : null;
                $error = $response === null ? 'connection_error' : $this->classify($response);

                $this->recordRun($purpose, $provider['model'], $requests[$id]['prompt_version'] ?? null, $hashes[$id], $error, $started, $response, $requests[$id]['subject'] ?? null);

                if ($error === null) {
                    /** @var array<string, mixed> */
                    $results[$id] = $response?->json() ?? [];

                    continue;
                }

                if ($this->canFallBack($mode)) {
                    $results[$id] = $this->runOffline($purpose, $requests[$id]['prompt'], $requests[$id]['prompt_version'] ?? null, $hashes[$id], $requests[$id]['subject'] ?? null);

                    continue;
                }

                $results[$id] = $response !== null && $response->status() === 429
                    ? RateLimitExceptionFactory::fromResponse($response, $this->quota(), GeminiQuota::BUCKET_FLASH)
                    : new GeminiException;
            }
        }

        return $results;
    }

    private function quota(): GeminiQuota
    {
        return $this->quota ?? app(GeminiQuota::class);
    }

    private function mode(): string
    {
        return strtolower(trim((string) config('skillbridge.gemini.mode', 'gemini')));
    }

    private function canFallBack(string $mode): bool
    {
        return in_array($mode, ['fallback', 'auto'], true);
    }

    /**
     * @return array{key: string, model: string, timeout: int}|null
     */
    private function providerConfig(): ?array
    {
        $key = config('skillbridge.gemini.api_key');
        $model = config('skillbridge.gemini.flash_model');
        $timeout = config('skillbridge.gemini.timeout_seconds');

        $valid = is_string($key) && trim($key) !== ''
            && is_string($model) && preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,254}\z/', $model)
            && is_int($timeout) && $timeout >= 1;

        return $valid ? ['key' => $key, 'model' => $model, 'timeout' => $timeout] : null;
    }

    private function endpoint(string $model): string
    {
        return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
    }

    private function assertValid(string $purpose, string $prompt, ?string $promptVersion, ?Model $subject): void
    {
        if (! in_array($purpose, self::PURPOSES, true)
            || trim($prompt) === '' || strlen($prompt) > 100_000
            || ($promptVersion !== null && ! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,254}\z/', $promptVersion))
            || ($subject !== null && ! $subject->exists)) {
            throw new InvalidArgumentException('Parameter panggilan AI tidak valid.');
        }
    }

    /**
     * @param  array<string, mixed>  $responseSchema
     * @return array<string, mixed>
     */
    private function buildPayload(#[SensitiveParameter] string $prompt, array $responseSchema): array
    {
        $payload = ['contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]]];
        $generationConfig = [];

        if ($responseSchema !== []) {
            $generationConfig['responseMimeType'] = 'application/json';
            $generationConfig['responseSchema'] = $responseSchema;
        }

        // thinkingLevel is only valid on Gemini 3+ models; an empty config value leaves the field out.
        $thinking = strtolower(trim((string) config('skillbridge.gemini.thinking_level', '')));
        if (in_array($thinking, self::THINKING_LEVELS, true)) {
            $generationConfig['thinkingConfig'] = ['thinkingLevel' => strtoupper($thinking)];
        }

        if ($generationConfig !== []) {
            $payload['generationConfig'] = $generationConfig;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hashPayload(array $payload): string
    {
        try {
            return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            throw new InvalidArgumentException('Parameter panggilan AI tidak valid.');
        }
    }

    private function groupSize(int $count): int
    {
        if (! $this->quota()->isEnabled()) {
            return $count;
        }

        return max(1, min($count, (int) config('skillbridge.gemini.limits.flash.rpm', $count)));
    }

    private function classify(Response $response): ?string
    {
        return $response->successful() ? $this->responseError($response->json()) : 'http_'.$response->status();
    }

    private function recordRun(
        string $purpose,
        string $model,
        ?string $promptVersion,
        string $inputHash,
        ?string $error,
        int|float $started,
        ?Response $response,
        ?Model $subject,
    ): void {
        $data = $response?->json();
        $usage = [];
        $metadata = is_array($data) ? ($data['usageMetadata'] ?? null) : null;

        if (is_array($metadata)) {
            foreach (['promptTokenCount', 'candidatesTokenCount', 'totalTokenCount', 'cachedContentTokenCount', 'thoughtsTokenCount', 'toolUsePromptTokenCount'] as $field) {
                if (isset($metadata[$field]) && is_int($metadata[$field]) && $metadata[$field] >= 0) {
                    $usage[$field] = $metadata[$field];
                }
            }
        }

        AiRun::create([
            'purpose' => $purpose,
            'model' => $model,
            'prompt_version' => $promptVersion,
            'input_hash' => $inputHash,
            'status' => $error === null ? 'success' : 'failed',
            'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'usage' => $usage === [] ? null : $usage,
            'error' => $error,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
        ]);
    }

    /**
     * @param  array<string, array{prompt: string, prompt_version?: ?string, subject?: ?Model, response_schema?: array<string, mixed>}>  $requests
     * @param  array<string, string>  $hashes
     * @return array<string, array<string, mixed>>
     */
    private function runOfflineGroup(string $purpose, array $requests, array $hashes): array
    {
        $results = [];

        foreach ($requests as $id => $request) {
            $results[$id] = $this->runOffline($purpose, $request['prompt'], $request['prompt_version'] ?? null, $hashes[$id], $request['subject'] ?? null);
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function runOffline(
        string $purpose,
        #[SensitiveParameter] string $prompt,
        ?string $promptVersion,
        string $inputHash,
        ?Model $subject,
    ): array {
        $started = hrtime(true);
        $data = (new OfflineAiEngine)->generateEnvelope($purpose, $prompt);

        AiRun::create([
            'purpose' => $purpose,
            'model' => 'offline-local-v1',
            'prompt_version' => $promptVersion,
            'input_hash' => $inputHash,
            'status' => 'success',
            'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'usage' => $data['usageMetadata'] ?? null,
            'error' => null,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
        ]);

        return $data;
    }

    private function responseError(mixed $data): ?string
    {
        if (! is_array($data)) {
            return 'invalid_response';
        }

        if (isset($data['promptFeedback']['blockReason'])) {
            return 'blocked_response';
        }

        $candidate = $data['candidates'][0] ?? null;

        if (! is_array($candidate)) {
            return 'invalid_response';
        }

        if (($candidate['finishReason'] ?? null) !== 'STOP') {
            return 'incomplete_response';
        }

        $parts = $candidate['content']['parts'] ?? null;

        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (is_array($part) && ($part['thought'] ?? false) !== true && is_string($part['text'] ?? null) && trim($part['text']) !== '') {
                    return null;
                }
            }
        }

        return 'invalid_response';
    }
}
