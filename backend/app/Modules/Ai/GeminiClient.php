<?php

declare(strict_types=1);

namespace App\Modules\Ai;

use App\Models\AiRun;
use App\Modules\Ai\Exceptions\GeminiException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use JsonException;
use SensitiveParameter;

final class GeminiClient
{
    /**
     * Return the provider envelope; callers must validate generated JSON against their schema.
     *
     * @param  array<string, mixed>  $responseSchema
     * @return array<string, mixed>
     */
    public function generateContent(
        string $purpose,
        #[SensitiveParameter] string $prompt,
        ?string $promptVersion = null,
        ?Model $subject = null,
        array $responseSchema = [],
    ): array {
        if (! in_array($purpose, ['rps_metadata', 'rps_extraction', 'job_extraction', 'match_judge', 'recommendation'], true)
            || trim($prompt) === '' || strlen($prompt) > 100_000
            || ($promptVersion !== null && ! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,254}\z/', $promptVersion))
            || ($subject !== null && ! $subject->exists)) {
            throw new InvalidArgumentException('Parameter panggilan AI tidak valid.');
        }

        $payload = ['contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]]];

        if ($responseSchema !== []) {
            $payload['generationConfig'] = [
                'responseMimeType' => 'application/json',
                'responseSchema' => $responseSchema,
            ];
        }

        try {
            $inputHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            throw new InvalidArgumentException('Parameter panggilan AI tidak valid.');
        }

        $mode = strtolower(trim((string) config('skillbridge.gemini.mode', 'gemini')));
        if ($mode === 'offline') {
            return $this->runOffline($purpose, $prompt, $promptVersion, $inputHash, $subject);
        }

        $provider = strtolower(trim((string) config('skillbridge.gemini.provider', config('skillbridge.ai.provider', 'gemini'))));
        $baseUrl = config('skillbridge.gemini.base_url', config('skillbridge.ai.base_url'));
        $key = config('skillbridge.gemini.api_key', config('skillbridge.ai.api_key'));
        $model = config('skillbridge.gemini.flash_model', config('skillbridge.ai.model'));
        $timeout = (int) config('skillbridge.gemini.timeout_seconds', config('skillbridge.ai.timeout_seconds', 60));

        if (is_string($model)) {
            $modelTrimmed = strtolower(trim($model));
            if (in_array($modelTrimmed, ['deepseek', 'deepsek'], true)) {
                $model = 'deepseek/deepseek-v4.1-flash';
            } elseif (in_array($modelTrimmed, ['deepseek-pro', 'deepsek-pro'], true)) {
                $model = 'deepseek/deepseek-v4-pro';
            }
        }

        $isOpenAi = ! empty($baseUrl) || in_array($provider, ['openai', 'openai_compatible', 'tokenrouter', 'openrouter', 'custom'], true);

        $hasValidConfig = is_string($key) && trim($key) !== ''
            && is_string($model) && preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._\/-]{0,254}\z/', $model)
            && is_int($timeout) && $timeout >= 1;

        if (! $hasValidConfig) {
            if (in_array($mode, ['fallback', 'auto'], true)) {
                return $this->runOffline($purpose, $prompt, $promptVersion, $inputHash, $subject);
            }

            throw new GeminiException;
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $started = hrtime(true);
            $response = null;
            $data = null;
            $error = 'connection_error';

            try {
                if ($isOpenAi) {
                    $endpoint = is_string($baseUrl) && trim($baseUrl) !== '' ? trim($baseUrl) : 'https://api.openai.com/v1/chat/completions';
                    if (! str_ends_with($endpoint, '/chat/completions')) {
                        $endpoint = rtrim($endpoint, '/').'/chat/completions';
                    }

                    $openAiPayload = [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'user', 'content' => $prompt],
                        ],
                    ];

                    if ($responseSchema !== [] || str_contains($prompt, 'JSON')) {
                        $openAiPayload['response_format'] = ['type' => 'json_object'];
                    }

                    $req = Http::withToken($key)
                        ->acceptJson()
                        ->asJson()
                        ->timeout($timeout)
                        ->connectTimeout(min(10, $timeout))
                        ->withoutRedirecting();

                    if (app()->isLocal() || ! empty(env('AI_SKIP_SSL_VERIFY', false))) {
                        $req = $req->withoutVerifying();
                    }

                    $response = $req->post($endpoint, $openAiPayload);
                    $rawJson = $response->json();

                    if ($response->successful() && is_array($rawJson)) {
                        $choice = $rawJson['choices'][0] ?? null;
                        $content = $choice['message']['content'] ?? null;

                        if (is_string($content) && trim($content) !== '') {
                            // Strip reasoning tags if present (e.g. <think>...</think>)
                            $cleanContent = (string) preg_replace('/<think>.*?<\/think>/s', '', $content);
                            $cleanContent = trim($cleanContent);

                            // Strip markdown json code block if present
                            if (str_starts_with($cleanContent, '```json') && str_ends_with($cleanContent, '```')) {
                                $cleanContent = trim(substr($cleanContent, 7, -3));
                            } elseif (str_starts_with($cleanContent, '```') && str_ends_with($cleanContent, '```')) {
                                $cleanContent = trim(substr($cleanContent, 3, -3));
                            }

                            $usageRaw = $rawJson['usage'] ?? [];
                            $data = [
                                'candidates' => [
                                    [
                                        'content' => [
                                            'parts' => [
                                                ['text' => $cleanContent],
                                            ],
                                            'role' => 'model',
                                        ],
                                        'finishReason' => 'STOP',
                                        'index' => 0,
                                    ],
                                ],
                                'usageMetadata' => [
                                    'promptTokenCount' => $usageRaw['prompt_tokens'] ?? null,
                                    'candidatesTokenCount' => $usageRaw['completion_tokens'] ?? null,
                                    'totalTokenCount' => $usageRaw['total_tokens'] ?? null,
                                ],
                            ];
                            $error = null;
                        } else {
                            $error = 'invalid_response';
                        }
                    } else {
                        $error = 'http_'.$response->status();
                    }
                } else {
                    $response = Http::withHeaders(['x-goog-api-key' => $key])
                        ->acceptJson()
                        ->asJson()
                        ->timeout($timeout)
                        ->connectTimeout(min(10, $timeout))
                        ->withoutRedirecting()
                        ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", $payload);

                    $data = $response->json();
                    $error = $response->successful() ? $this->responseError($data) : 'http_'.$response->status();
                }
            } catch (ConnectionException) {
                // Never retain provider exceptions: they can contain request data.
            }

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

            if ($error === null) {
                return $data;
            }

            $retryable = $response === null || in_array($response->status(), [408, 429], true) || $response->serverError();

            if (! $retryable || $attempt === 3) {
                if (in_array($mode, ['fallback', 'auto'], true)) {
                    return $this->runOffline($purpose, $prompt, $promptVersion, $inputHash, $subject);
                }

                throw new GeminiException;
            }

            Sleep::for(2 ** ($attempt - 1))->seconds();
        }

        throw new GeminiException;
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
