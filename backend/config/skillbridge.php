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
        // Execution mode: "gemini" (strict cloud API), "offline" (100% local PHP engine), or "fallback" (cloud with local fallback).
        'mode' => env('SKILLBRIDGE_AI_MODE', 'gemini'),
        // Backend only. Never expose to frontend, logs or commits.
        'api_key' => env('GEMINI_API_KEY'),
        // Model names change over time: always set from env, never hardcode.
        'flash_model' => env('GEMINI_FLASH_MODEL'),
        'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),
        'embedding_dimensions' => (int) env('GEMINI_EMBEDDING_DIMENSIONS', 768),
        'timeout_seconds' => (int) env('GEMINI_TIMEOUT_SECONDS', 20),
        // Thinking level sent as generationConfig.thinkingConfig.thinkingLevel (minimal|low|medium|high).
        // Gemini 3+ models only: older models reject the field. Empty string = do not send.
        'thinking_level' => env('GEMINI_THINKING_LEVEL', 'minimal'),
        // Max in-flight requests inside one Http::pool (RPS chunks).
        'max_concurrent' => (int) env('GEMINI_MAX_CONCURRENT', 3),
        // Extra attempts for a single failed chunk (schema or transport), never the whole document.
        'chunk_retries' => (int) env('GEMINI_CHUNK_RETRIES', 2),
        // Skills written into one recommendation prompt. One call per batch instead of one per skill.
        'recommendation_batch_size' => (int) env('GEMINI_RECOMMENDATION_BATCH_SIZE', 5),
        // Internal request counters. Flash RPM is 14 of 15 (~93%), RPD is ~80% of 500; embedding is ~80%
        // of 100 RPM / 1000 RPD. One batchEmbedContents call counts as one request.
        'limits' => [
            'enabled' => (bool) env('SKILLBRIDGE_QUOTA_ENABLED', true),
            // Provider RPD resets at midnight Pacific time, not UTC.
            'reset_timezone' => env('SKILLBRIDGE_QUOTA_RESET_TIMEZONE', 'America/Los_Angeles'),
            'redis_prefix' => env('SKILLBRIDGE_QUOTA_REDIS_PREFIX', 'skillbridge:ai-quota'),
            'flash' => [
                'rpm' => (int) env('GEMINI_FLASH_RPM', 14),
                'rpd' => (int) env('GEMINI_FLASH_RPD', 400),
            ],
            'embedding' => [
                'rpm' => (int) env('GEMINI_EMBEDDING_RPM', 80),
                'rpd' => (int) env('GEMINI_EMBEDDING_RPD', 800),
            ],
        ],
        // Extraction jobs: explicit timeout (seconds) must stay below REDIS_QUEUE_RETRY_AFTER.
        'jobs' => [
            'timeout_seconds' => (int) env('GEMINI_JOB_TIMEOUT_SECONDS', 120),
            // Jobs that call Gemini many times in a row (analysis judge, recommendations).
            'long_timeout_seconds' => (int) env('GEMINI_LONG_JOB_TIMEOUT_SECONDS', 300),
            // In-process waits (seconds are taken from the limiter) before a looping job gives up.
            'max_rate_limit_waits' => (int) env('GEMINI_MAX_RATE_LIMIT_WAITS', 3),
            // Time window in which a job waiting for RPM may be released back to the queue.
            'retry_until_minutes' => (int) env('GEMINI_JOB_RETRY_UNTIL_MINUTES', 15),
            'max_exceptions' => (int) env('GEMINI_JOB_MAX_EXCEPTIONS', 3),
            // Job postings extracted at the same time by one grouped job.
            'concurrent_postings' => (int) env('GEMINI_CONCURRENT_POSTINGS', 2),
            // Requests kept free for user-triggered flows when bulk commands plan their budget.
            'reserved_daily_requests' => (int) env('GEMINI_RESERVED_DAILY_REQUESTS', 100),
        ],
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
        'ai_per_minute' => (int) env('SKILLBRIDGE_AI_PER_MINUTE', 10),
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
                'categories' => ['software-development', 'data', 'devops', 'qa', 'artificial-intelligence', 'information-technology', 'design'],
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
                'it_categories' => ['Developer', 'Data Science', 'IT', 'DevOps', 'Engineering', 'Software Engineering', 'Design', 'Cybersecurity', 'QA', 'Research & Development'],
            ],
            // Terms: credit Jobicy, keep links to jobicy.com, sync at most hourly.
            'jobicy' => [
                'endpoint' => 'https://jobicy.com/api/jobs',
                // IT-family industry slugs (GET /api/jobs?get=industries).
                'industries' => ['engineering', 'data-science', 'admin', 'qa-testing', 'cybersecurity', 'technical-support', 'web-app-design'],
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
            'business developer', 'business development', 'content developer', 'curriculum developer',
            'mechanical', 'civil engineer', 'chemical engineer', 'electrical engineer', 'process engineer',
            'graphic designer', 'interior designer', 'fashion',
        ],
        // Title keywords -> career slug (slugs from CareerSeeder). First match
        // wins, so specific roles come before generic ones (e.g. "react native"
        // -> mobile before "react" -> frontend; "software engineer" is the
        // catch-all). Unmatched postings are skipped.
        'career_keywords' => [
            'full-stack-developer' => ['full stack', 'full-stack', 'fullstack'],
            'machine-learning-engineer' => ['machine learning', 'ml engineer', 'mlops', 'ai engineer', 'ai/ml', 'deep learning', 'computer vision', 'nlp', 'llm', 'genai', 'generative ai'],
            'data-scientist' => ['data scientist', 'data science'],
            'data-engineer' => ['data engineer', 'analytics engineer', 'etl', 'big data', 'data platform', 'data warehouse'],
            'data-analyst' => ['data analyst', 'business intelligence', 'bi analyst', 'bi developer', 'analytics analyst', 'reporting analyst', 'power bi', 'tableau'],
            'database-administrator' => ['database administrator', 'dba', 'database engineer', 'database developer'],
            'cybersecurity-engineer' => ['security engineer', 'security analyst', 'cybersecurity', 'cyber security', 'information security', 'infosec', 'appsec', 'application security', 'penetration tester', 'pentester', 'soc analyst', 'devsecops', 'security architect'],
            'devops-engineer' => ['devops', 'site reliability', 'sre', 'platform engineer', 'release engineer', 'build engineer', 'kubernetes'],
            'cloud-engineer' => ['cloud engineer', 'cloud architect', 'cloud developer', 'aws', 'azure', 'gcp', 'google cloud'],
            'network-engineer' => ['network engineer', 'network administrator', 'network architect', 'noc engineer', 'noc analyst'],
            'system-administrator' => ['system administrator', 'systems administrator', 'sysadmin', 'linux administrator', 'windows administrator', 'infrastructure engineer', 'systems engineer', 'it infrastructure'],
            'qa-engineer' => ['qa', 'quality assurance', 'test engineer', 'software tester', 'tester', 'sdet', 'test automation', 'automation tester', 'quality engineer'],
            'mobile-developer' => ['mobile', 'android', 'ios', 'flutter', 'react native', 'kotlin', 'swift', 'swiftui', 'xamarin'],
            'game-developer' => ['game developer', 'game programmer', 'game engineer', 'gameplay', 'unity', 'unreal'],
            'embedded-iot-engineer' => ['embedded', 'firmware', 'iot', 'internet of things', 'microcontroller'],
            'ui-ux-designer' => ['ui/ux', 'ui ux', 'ux designer', 'ui designer', 'ux researcher', 'product designer', 'interaction designer', 'ux/ui', 'ux'],
            'frontend-developer' => ['frontend', 'front-end', 'front end', 'react', 'reactjs', 'vue', 'vuejs', 'angular', 'nextjs', 'next.js', 'ui developer', 'ui engineer', 'javascript developer', 'typescript developer'],
            'backend-developer' => ['backend', 'back-end', 'back end', 'api', 'laravel', 'php', 'node', 'nodejs', 'node.js', 'golang', 'go developer', 'django', 'rails', 'ruby', 'java developer', 'java engineer', 'python developer', 'python engineer', '.net', 'c#', 'spring boot', 'elixir', 'rust'],
            'system-analyst' => ['system analyst', 'systems analyst', 'it business analyst', 'business systems analyst', 'it analyst', 'technical business analyst', 'erp consultant', 'sap consultant', 'functional consultant'],
            'it-support' => ['it support', 'helpdesk', 'help desk', 'service desk', 'technical support', 'desktop support', 'it technician', 'support engineer', 'it specialist', 'it officer'],
            'software-engineer' => ['software engineer', 'software developer', 'software development engineer', 'programmer', 'application developer', 'web developer', 'developer', 'coder', 'staff engineer', 'principal engineer', 'tech lead', 'engineering manager', 'solutions architect', 'software architect'],
        ],
    ],

];
