<?php

declare(strict_types=1);

namespace Tests\Feature\Industry;

use App\Modules\Industry\Services\JobPostingImporter;
use Tests\TestCase;

class DeriveQualificationsTest extends TestCase
{
    private JobPostingImporter $importer;

    private \ReflectionMethod $derive;

    private \ReflectionMethod $strip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = app(JobPostingImporter::class);
        $this->derive = new \ReflectionMethod($this->importer, 'deriveQualifications');
        $this->strip = new \ReflectionMethod($this->importer, 'stripLocaleSuffix');
    }

    private function derive(string $description): ?string
    {
        return $this->derive->invoke($this->importer, $description);
    }

    public function test_extracts_skill_list_under_indonesian_header(): void
    {
        $desc = "[DESKRIPSI SINTETIS] Bukan teks asli.\n\nRingkasan asli dari sumber:\nFull-cycle trainee program.\n- Allowance\n\nSkill yang umum dibutuhkan untuk profesi ini:\n- Python\n- Go\n- C++\n- Java\n- AWS";

        $result = $this->derive($desc);

        $this->assertStringContainsString('Python', $result);
        $this->assertStringContainsString('AWS', $result);
        $this->assertStringNotContainsString('Allowance', $result);
    }

    public function test_extracts_explicit_requirements_section(): void
    {
        $desc = "About us\nWe build rockets.\n\nRequirements:\n- 5 years of experience with Go\n- Bachelor degree in CS\n\nBenefits:\n- Great pay";

        $result = $this->derive($desc);

        $this->assertStringContainsString('5 years of experience with Go', $result);
        $this->assertStringNotContainsString('Great pay', $result);
    }

    public function test_extracts_first_bullet_list(): void
    {
        $desc = "We are hiring!\n\n* Handle deployments\n* Maintain clusters\n* On-call rotation\n* Write docs\n\nApply now.";

        $result = $this->derive($desc);

        $this->assertStringContainsString('Handle deployments', $result);
        $this->assertStringContainsString('Write docs', $result);
        $this->assertStringNotContainsString('Apply now', $result);
    }

    public function test_extracts_qualification_signal_sentences(): void
    {
        $desc = 'You will join our platform team. We expect 3+ years of experience with Kubernetes. Familiar with CI/CD tools is a plus. Our office is in Berlin.';

        $result = $this->derive($desc);

        $this->assertStringContainsString('experience with Kubernetes', $result);
    }

    public function test_returns_null_when_no_signals(): void
    {
        $this->assertNull($this->derive('Short ad with no signals at all here.'));
    }

    public function test_strips_locale_suffix_from_title(): void
    {
        $cases = [
            'AI Software Developer (m/f/d)' => 'AI Software Developer',
            'Engineer (all genders)' => 'Engineer',
            'DevOps (w/m/d)' => 'DevOps',
            'Data Scientist (M/W/D) ' => 'Data Scientist',
            'Clean Title' => 'Clean Title',
        ];

        foreach ($cases as $input => $expected) {
            $this->assertSame($expected, $this->strip->invoke($this->importer, $input), "Input: {$input}");
        }
    }
}