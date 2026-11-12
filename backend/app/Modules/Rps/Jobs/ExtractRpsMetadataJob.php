<?php

declare(strict_types=1);

namespace App\Modules\Rps\Jobs;

use App\Models\RpsDocument;
use App\Modules\Ai\Exceptions\GeminiException;
use App\Modules\Ai\GeminiClient;
use App\Modules\Rps\Enums\ParseStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExtractRpsMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $rpsDocumentId,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->rpsDocumentId))->dontRelease()];
    }

    public function handle(GeminiClient $gemini): void
    {
        $document = RpsDocument::find($this->rpsDocumentId);

        if ($document === null || $document->isMetadataConfirmed() || $document->parse_status !== ParseStatus::Parsed) {
            return;
        }

        if ($document->detected_metadata !== null) {
            return;
        }

        $pages = $document->pages()
            ->where('page_number', '<=', 3)
            ->orderBy('page_number')
            ->get();

        if ($pages->isEmpty()) {
            return;
        }

        $combinedText = $pages->pluck('text')->implode("\n\n");
        if (trim($combinedText) === '') {
            return;
        }

        $mode = strtolower(trim((string) config('skillbridge.gemini.mode', 'gemini')));
        $key = config('skillbridge.gemini.api_key');
        $model = config('skillbridge.gemini.flash_model');
        $timeout = config('skillbridge.gemini.timeout_seconds');

        if (! in_array($mode, ['offline', 'fallback', 'auto'], true)
            && (! is_string($key) || trim($key) === ''
                || ! is_string($model) || ! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,254}\z/', $model)
                || ! is_int($timeout) || $timeout < 1)) {
            return;
        }

        $promptPath = resource_path('prompts/rps-metadata-v1.txt');
        $template = file_exists($promptPath)
            ? (string) file_get_contents($promptPath)
            : "Ekstraksi metadata dari RPS:\n<document>\n{{text}}\n</document>";

        $prompt = str_replace('{{text}}', $combinedText, $template);
        if (strlen($prompt) > 100_000) {
            $prompt = mb_substr($prompt, 0, 100_000);
        }

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'code' => ['type' => 'STRING', 'nullable' => true],
                'name' => ['type' => 'STRING', 'nullable' => true],
                'credits' => ['type' => 'INTEGER', 'nullable' => true],
                'semester' => ['type' => 'INTEGER', 'nullable' => true],
                'study_program' => ['type' => 'STRING', 'nullable' => true],
            ],
            'required' => ['code', 'name', 'credits', 'semester', 'study_program'],
        ];

        try {
            $response = $gemini->generateContent(
                purpose: 'rps_metadata',
                prompt: $prompt,
                promptVersion: 'rps-metadata-v1',
                subject: $document,
                responseSchema: $schema,
            );

            $rawText = $this->extractCandidateText($response);
            if ($rawText === null) {
                throw new GeminiException('Respons AI tidak mengandung teks.');
            }

            $decoded = json_decode($rawText, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                throw new GeminiException('Format JSON hasil ekstraksi AI tidak valid.');
            }

            $validated = $this->validateMetadata($decoded);
            if ($validated === null) {
                throw new GeminiException('Skema metadata hasil ekstraksi AI tidak valid.');
            }

            $document->update([
                'detected_metadata' => $validated,
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            $this->markFailed($document, 'Gagal mengekstrak metadata secara otomatis. Silakan lengkapi metadata secara manual.');

            if (! app()->runningUnitTests()) {
                throw $e;
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $document = RpsDocument::find($this->rpsDocumentId);

        if ($document !== null && $document->detected_metadata === null && ! $document->isMetadataConfirmed()) {
            $this->markFailed($document, 'Gagal mengekstrak metadata secara otomatis. Silakan lengkapi metadata secara manual.');
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractCandidateText(array $response): ?string
    {
        $candidate = $response['candidates'][0] ?? null;
        if (! is_array($candidate)) {
            return null;
        }

        $parts = $candidate['content']['parts'] ?? null;
        if (! is_array($parts)) {
            return null;
        }

        foreach ($parts as $part) {
            if (is_array($part) && ($part['thought'] ?? false) !== true && is_string($part['text'] ?? null) && trim($part['text']) !== '') {
                return $part['text'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{code: ?string, name: ?string, credits: ?int, semester: ?int, study_program: ?string}|null
     */
    private function validateMetadata(array $data): ?array
    {
        $code = $data['code'] ?? null;
        $name = $data['name'] ?? null;
        $credits = $data['credits'] ?? null;
        $semester = $data['semester'] ?? null;
        $studyProgram = $data['study_program'] ?? null;

        if ($code !== null && (! is_string($code) || mb_strlen($code) > 255)) {
            return null;
        }

        if ($name !== null && (! is_string($name) || mb_strlen($name) > 255)) {
            return null;
        }

        if ($credits !== null && (! is_int($credits) || $credits < 0 || $credits > 32767)) {
            return null;
        }

        if ($semester !== null && (! is_int($semester) || $semester < 0 || $semester > 32767)) {
            return null;
        }

        if ($studyProgram !== null && (! is_string($studyProgram) || mb_strlen($studyProgram) > 255)) {
            return null;
        }

        return [
            'code' => $code !== null ? trim($code) : null,
            'name' => $name !== null ? trim($name) : null,
            'credits' => $credits,
            'semester' => $semester,
            'study_program' => $studyProgram !== null ? trim($studyProgram) : null,
        ];
    }

    private function markFailed(RpsDocument $document, string $message): void
    {
        $document->update(['error_message' => $message]);
    }
}
