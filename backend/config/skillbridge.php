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

    // --- Recommendation (Modules/Recommendation) ---
    // Priority rules from docs/AI_PIPELINE.md §7; computed by Laravel, never by the LLM.
    'recommendation' => [
        'priority' => [
            // Share of selected job postings asking for the skill that counts as "high demand".
            'high_demand_ratio' => (float) env('SKILLBRIDGE_PRIORITY_HIGH_DEMAND_RATIO', 0.5),
            'missing_high_demand' => 'high',
            'missing' => 'medium',
            'partial_high_demand' => 'medium',
            'partial' => 'low',
        ],
    ],

    // --- Job scraping (Modules/Industry, skillbridge:scrape-jobs) ---
    // Offline collection only: output is a JSON file reviewed by a human, then
    // loaded with skillbridge:import-jobs. Only sources whose terms allow
    // automated access are listed here (see docs/PRD.md §8).
    'scraping' => [
        'user_agent' => env('SKILLBRIDGE_SCRAPER_USER_AGENT', 'SkillBridgeDatasetBot/1.0 (academic research)'),
        'timeout_seconds' => (int) env('SKILLBRIDGE_SCRAPER_TIMEOUT', 30),
        'output_dir' => storage_path('app/private/scraped'),
        'sources' => [
            // Terms: credit Remotive + link back, max 2 req/min, a few runs per day.
            'remotive' => [
                'endpoint' => 'https://remotive.com/api/remote-jobs',
                'categories' => ['software-dev', 'data'],
                'delay_seconds' => 35,
            ],
            'arbeitnow' => [
                'endpoint' => 'https://www.arbeitnow.com/api/job-board-api',
                'max_pages' => 10,
                'delay_seconds' => 2,
            ],
            // Terms: link to Himalayas as source, keep applicationLink unchanged.
            'himalayas' => [
                'endpoint' => 'https://himalayas.app/jobs/api/search',
                'country' => env('SKILLBRIDGE_SCRAPER_HIMALAYAS_COUNTRY', 'Indonesia'),
                'max_pages' => 25,
                'delay_seconds' => 2,
                // Only postings whose parentCategories intersect this list (IT family).
                'it_categories' => ['Developer', 'Data Science', 'IT', 'DevOps', 'Engineering', 'Software Engineering'],
            ],
            // Terms: credit Jobicy, keep links to jobicy.com, sync at most hourly.
            'jobicy' => [
                'endpoint' => 'https://jobicy.com/api/jobs',
                // IT-family industry slugs (GET /api/jobs?get=industries).
                'industries' => ['engineering', 'data-science', 'web-app-design'],
                'geo' => env('SKILLBRIDGE_SCRAPER_JOBICY_GEO'), // e.g. "apac"; null = all
                'delay_seconds' => 5,
            ],
        ],
        // Rumpun TI only: titles containing any of these are dropped even if a
        // career keyword matches (e.g. "API Sales Manager").
        'exclude_title_keywords' => [
            'sales', 'marketing', 'recruiter', 'recruitment', 'talent acquisition', 'account executive',
            'account manager', 'customer success', 'customer support', 'copywriter', 'content writer',
            'teacher', 'tutor', 'accountant', 'lawyer', 'nurse',
        ],
        // Title keywords -> career slug. First match wins, so order matters
        // (full stack before frontend/backend). Unmatched postings are skipped.
        'career_keywords' => [
            'full-stack-developer' => ['full stack', 'full-stack', 'fullstack'],
            'data-analyst' => ['data analyst', 'business intelligence', 'bi analyst', 'analytics analyst', 'reporting analyst'],
            'frontend-developer' => ['frontend', 'front-end', 'front end', 'react', 'vue', 'angular', 'ui developer', 'ui engineer'],
            'backend-developer' => ['backend', 'back-end', 'back end', 'api', 'laravel', 'php', 'node', 'golang', 'django', 'rails', 'java developer', 'python developer'],
        ],
    ],

];
