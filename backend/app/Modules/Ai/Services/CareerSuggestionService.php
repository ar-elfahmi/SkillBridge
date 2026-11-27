<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

use App\Enums\CompetencyReviewStatus;
use App\Models\Career;
use App\Models\CourseSkill;
use App\Models\RpsDocument;
use App\Modules\Ai\GeminiClient;
use App\Modules\Ai\Jobs\GenerateCareerSuggestionsJob;
use RuntimeException;
use Throwable;

/**
 * Suggests careers that fit an RPS with one Gemini request. Laravel owns every guardrail:
 * ids must come from the offered careers, texts are length-limited and stripped of URLs.
 */
final class CareerSuggestionService
{
    public const PROMPT_VERSION = 'career-suggest-v1';

    private const MAX_SUGGESTIONS = 3;

    private const MAX_COMPETENCIES = 60;

    private const MAX_COMPETENCY_CHARS = 200;

    private const MAX_REASON_CHARS = 200;

    /** Schema retries after the first attempt (docs/AI_PIPELINE.md: max 2). */
    private const SCHEMA_RETRIES = 2;

    public function __construct(
        private readonly GeminiClient $gemini,
        private readonly AiQuotaGuard $quotaGuard,
    ) {}

    /**
     * Mark the document as processing and queue the job. Returns false (and changes nothing) when the daily
     * Gemini budget is already used up.
     */
    public function request(RpsDocument $document): bool
    {
        if ($this->quotaGuard->denial() !== null) {
            return false;
        }

        $document->update(['career_suggestions' => ['status' => 'processing', 'items' => []]]);
        GenerateCareerSuggestionsJob::dispatch($document->id);

        return true;
    }

    /**
     * @return list<array{career_id: int, reason: string}>
     *
     * @throws RuntimeException when the model never returns a parsable answer
     */
    public function suggest(RpsDocument $document): array
    {
        $competencies = $this->competencies($document);
        $careers = $this->careers($document);

        if ($competencies === [] || $careers === []) {
            return [];
        }

        $prompt = $this->buildPrompt($competencies, $careers);
        $allowed = array_column($careers, 'id');
        $parsedOnce = false;
        $items = [];

        for ($attempt = 0; $attempt <= self::SCHEMA_RETRIES; $attempt++) {
            $decoded = $this->decode($this->gemini->generateContent(
                'career_suggestion',
                $prompt,
                self::PROMPT_VERSION,
                $document,
                $this->responseSchema(),
            ));

            if ($decoded === null) {
                continue;
            }

            $parsedOnce = true;
            $items = $this->validate($decoded, $allowed);

            if ($items !== []) {
                break;
            }
        }

        if (! $parsedOnce) {
            throw new RuntimeException('Career suggestion output failed schema validation.');
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function competencies(RpsDocument $document): array
    {
        return $document->courseSkills()
            ->where('review_status', '!=', CompetencyReviewStatus::Rejected)
            ->orderBy('id')
            ->limit(self::MAX_COMPETENCIES)
            ->get()
            ->map(fn (CourseSkill $cs): string => mb_substr(trim($cs->skill.': '.$cs->competency), 0, self::MAX_COMPETENCY_CHARS))
            ->filter(fn (string $text): bool => $text !== ':')
            ->values()
            ->all();
    }

    /**
     * Only careers the owner can actually analyze (they have ready postings).
     *
     * @return list<array{id: int, name: string, description: string}>
     */
    private function careers(RpsDocument $document): array
    {
        $user = $document->user;
        if ($user === null) {
            return [];
        }

        return Career::withEligibleJobCount($user)
            ->orderBy('name')
            ->get()
            ->filter(fn (Career $c): bool => (int) $c->eligible_job_count > 0)
            ->map(fn (Career $c): array => ['id' => $c->id, 'name' => $c->name, 'description' => (string) $c->description])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $competencies
     * @param  list<array{id: int, name: string, description: string}>  $careers
     */
    private function buildPrompt(array $competencies, array $careers): string
    {
        $path = resource_path('prompts/'.self::PROMPT_VERSION.'.txt');
        $template = is_file($path) ? (string) file_get_contents($path) : "Pilih profesi:\n<data>{{career_context}}</data>";

        // Slashes stay escaped so a "</data>" inside the document cannot close the delimiter.
        $context = json_encode(
            ['competencies' => $competencies, 'careers' => $careers],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        return str_replace('{{career_context}}', $context, $template);
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'career_id' => ['type' => 'INTEGER'],
                    'reason' => ['type' => 'STRING'],
                ],
                'required' => ['career_id', 'reason'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return list<mixed>|null
     */
    private function decode(array $envelope): ?array
    {
        $parts = $envelope['candidates'][0]['content']['parts'] ?? null;
        if (! is_array($parts)) {
            return null;
        }

        foreach ($parts as $part) {
            if (is_array($part) && is_string($part['text'] ?? null)) {
                try {
                    $decoded = json_decode($part['text'], true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    return null;
                }

                return is_array($decoded) && array_is_list($decoded) ? $decoded : null;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $decoded
     * @param  list<int>  $allowed
     * @return list<array{career_id: int, reason: string}>
     */
    private function validate(array $decoded, array $allowed): array
    {
        $items = [];
        $seen = [];

        foreach ($decoded as $entry) {
            if (! is_array($entry) || ! is_int($entry['career_id'] ?? null) || ! is_string($entry['reason'] ?? null)) {
                continue;
            }

            $id = $entry['career_id'];
            $reason = $this->clean($entry['reason']);

            if (! in_array($id, $allowed, true) || isset($seen[$id]) || $reason === '') {
                continue;
            }

            $seen[$id] = true;
            $items[] = ['career_id' => $id, 'reason' => $reason];

            if (count($items) === self::MAX_SUGGESTIONS) {
                break;
            }
        }

        return $items;
    }

    private function clean(string $text): string
    {
        $text = (string) preg_replace('/https?:\/\/\S+|www\.\S+/i', '', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_substr($text, 0, self::MAX_REASON_CHARS);
    }
}
