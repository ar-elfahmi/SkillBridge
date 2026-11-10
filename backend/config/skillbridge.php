<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SkillBridge configuration
|--------------------------------------------------------------------------
|
| Single source for model names, matching thresholds and upload limits.
| Application code must read these via config('skillbridge.*'), never env().
| Group keys per module; add new keys inside your own module section.
|
*/

return [

    // --- AI (Modules/Ai) ---
    'gemini' => [
        // Backend only. Never expose to frontend, logs or commits.
        'api_key' => env('GEMINI_API_KEY'),
        // Model names change over time: always set from env, never hardcode.
        'flash_model' => env('GEMINI_FLASH_MODEL'),
        'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),
        'embedding_dimensions' => (int) env('GEMINI_EMBEDDING_DIMENSIONS', 768),
        'timeout_seconds' => (int) env('GEMINI_TIMEOUT_SECONDS', 60),
    ],

    // --- Matching (Modules/Matching) ---
    // Placeholder values; NOT calibrated. See docs/AI_PIPELINE.md#threshold.
    'matching' => [
        'top_k' => (int) env('SKILLBRIDGE_TOP_K', 3),
        'sim_floor' => (float) env('SKILLBRIDGE_SIM_FLOOR', 0.50),
        'sim_high' => (float) env('SKILLBRIDGE_SIM_HIGH', 0.80),
    ],

    // --- Upload (Modules/Rps) ---
    'upload' => [
        'max_pdf_mb' => (int) env('SKILLBRIDGE_MAX_PDF_MB', 10),
        'max_pages' => (int) env('SKILLBRIDGE_MAX_PAGES', 60),
        // Average non-whitespace chars per page below this => needs_ocr. Heuristic, not calibrated.
        'min_chars_per_page' => (int) env('SKILLBRIDGE_MIN_CHARS_PER_PAGE', 50),
    ],

    // --- Rate limits (Modules/Auth) ---
    'rate_limits' => [
        'auth_per_minute' => (int) env('SKILLBRIDGE_AUTH_PER_MINUTE', 5),
        'upload_per_minute' => (int) env('SKILLBRIDGE_UPLOAD_PER_MINUTE', 10),
    ],

];
