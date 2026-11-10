<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class SkillbridgeConfigTest extends TestCase
{
    public function test_defaults_have_expected_types(): void
    {
        $this->assertIsString(config('skillbridge.gemini.embedding_model'));
        $this->assertIsInt(config('skillbridge.gemini.embedding_dimensions'));
        $this->assertIsInt(config('skillbridge.gemini.timeout_seconds'));
        $this->assertIsInt(config('skillbridge.matching.top_k'));
        $this->assertIsFloat(config('skillbridge.matching.sim_floor'));
        $this->assertIsFloat(config('skillbridge.matching.sim_high'));
        $this->assertIsInt(config('skillbridge.upload.max_pdf_mb'));
        $this->assertIsInt(config('skillbridge.upload.max_pages'));
    }

    public function test_thresholds_are_ordered_and_in_range(): void
    {
        $floor = config('skillbridge.matching.sim_floor');
        $high = config('skillbridge.matching.sim_high');

        $this->assertGreaterThanOrEqual(0.0, $floor);
        $this->assertLessThan($high, $floor);
        $this->assertLessThanOrEqual(1.0, $high);
        $this->assertGreaterThan(0, config('skillbridge.matching.top_k'));
    }

    public function test_config_can_be_overridden_in_tests(): void
    {
        config([
            'skillbridge.matching.top_k' => 5,
            'skillbridge.matching.sim_floor' => 0.42,
            'skillbridge.upload.max_pages' => 2,
            'skillbridge.gemini.flash_model' => 'fake-flash',
        ]);

        $this->assertSame(5, config('skillbridge.matching.top_k'));
        $this->assertSame(0.42, config('skillbridge.matching.sim_floor'));
        $this->assertSame(2, config('skillbridge.upload.max_pages'));
        $this->assertSame('fake-flash', config('skillbridge.gemini.flash_model'));
    }

    public function test_config_file_does_not_hardcode_api_key(): void
    {
        $source = (string) file_get_contents(config_path('skillbridge.php'));

        $this->assertMatchesRegularExpression("/'api_key'\s*=>\s*env\('GEMINI_API_KEY'\)\s*,/", $source);
    }
}
