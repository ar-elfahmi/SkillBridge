<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Request counters live in shared Redis state: keep them off unless a test opts in.
        config(['skillbridge.gemini.limits.enabled' => false]);
    }
}
