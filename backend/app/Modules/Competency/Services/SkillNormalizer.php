<?php

declare(strict_types=1);

namespace App\Modules\Competency\Services;

use App\Enums\SkillCategory;
use App\Models\Skill;
use Illuminate\Database\UniqueConstraintViolationException;

class SkillNormalizer
{
    /**
     * In-memory cache for normalized skills during execution.
     * Key is lowercased trimmed name.
     *
     * @var array<string, Skill>
     */
    private array $cache = [];

    /**
     * Normalize a raw skill name to a canonical Skill model.
     *
     * If the name matches an existing canonical name or alias (case-insensitively),
     * returns that Skill model. Otherwise, creates a new entry in the dictionary.
     */
    public function normalize(string $rawName, SkillCategory|string|null $category = null): ?Skill
    {
        $cleaned = $this->cleanName($rawName);
        if ($cleaned === '') {
            return null;
        }

        $lookupKey = mb_strtolower($cleaned);

        if (isset($this->cache[$lookupKey])) {
            return $this->cache[$lookupKey];
        }

        // 1-2. Match on canonical_name, then on aliases (case-insensitive)
        $skill = $this->findExisting($cleaned);

        // 3. Combined names such as "Agile/Scrum": use the first part that is a known skill or alias.
        if ($skill === null && str_contains($cleaned, '/')) {
            foreach (array_map('trim', explode('/', $cleaned)) as $part) {
                if ($part !== '' && ($known = $this->findExisting($part)) !== null) {
                    $skill = $known;
                    break;
                }
            }
        }

        // 4. Not found in dictionary -> create new Skill entry
        if ($skill === null) {
            $resolvedCategory = $this->resolveCategory($category);

            try {
                $skill = Skill::create([
                    'canonical_name' => $cleaned,
                    'category' => $resolvedCategory,
                    'aliases' => [],
                ]);
            } catch (UniqueConstraintViolationException) {
                // Handle concurrent creation
                $skill = Skill::whereRaw('LOWER(canonical_name) = LOWER(?)', [$cleaned])->firstOrFail();
            }
        }

        $this->cache[$lookupKey] = $skill;

        return $skill;
    }

    /**
     * Helper to get only the canonical name string.
     */
    public function canonicalName(string $rawName, SkillCategory|string|null $category = null): ?string
    {
        return $this->normalize($rawName, $category)?->canonical_name;
    }

    /**
     * Helper to get only the Skill ID.
     */
    public function skillId(string $rawName, SkillCategory|string|null $category = null): ?int
    {
        return $this->normalize($rawName, $category)?->id;
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }

    private function findExisting(string $name): ?Skill
    {
        return Skill::whereRaw('LOWER(canonical_name) = LOWER(?)', [$name])->first()
            ?? Skill::whereRaw(
                'EXISTS (SELECT 1 FROM jsonb_array_elements_text(aliases) AS a WHERE LOWER(a) = LOWER(?))',
                [$name]
            )->first();
    }

    private function cleanName(string $rawName): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $rawName));
    }

    private function resolveCategory(SkillCategory|string|null $category): ?SkillCategory
    {
        if ($category instanceof SkillCategory) {
            return $category;
        }

        if (is_string($category) && trim($category) !== '') {
            return SkillCategory::tryFrom(mb_strtolower(trim($category)));
        }

        return null;
    }
}
