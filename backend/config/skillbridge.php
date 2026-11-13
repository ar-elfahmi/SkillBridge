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
    'ai' => [
        // Provider: 'openai' (OpenAI-compatible / tokenrouter / OpenRouter), 'gemini', or 'offline'
        'provider' => env('AI_PROVIDER', env('AI_BASE_URL') ? 'openai' : 'gemini'),
        'base_url' => env('AI_BASE_URL'),
        'api_key' => env('AI_API_KEY') ?: env('GEMINI_API_KEY'),
        'model' => env('AI_MODEL') ?: env('GEMINI_FLASH_MODEL', 'deepseek/deepseek-v4.1-flash'),
        'mode' => env('SKILLBRIDGE_AI_MODE', 'fallback'),
        'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', env('GEMINI_TIMEOUT_SECONDS', 60)),
    ],
    'gemini' => [
        'provider' => env('AI_PROVIDER', env('AI_BASE_URL') ? 'openai' : 'gemini'),
        'base_url' => env('AI_BASE_URL'),
        // Execution mode: "gemini" (strict cloud API), "offline" (100% local PHP engine), or "fallback" (cloud with local fallback).
        'mode' => env('SKILLBRIDGE_AI_MODE', 'gemini'),
        // Backend only. Never expose to frontend, logs or commits.
        'api_key' => env('AI_API_KEY') ?: env('GEMINI_API_KEY'),
        // Model names change over time: always set from env, never hardcode.
        'flash_model' => (function () {
            $m = env('AI_MODEL') ?: env('GEMINI_FLASH_MODEL', 'gemini-2.5-flash');
            if (in_array(strtolower(trim((string) $m)), ['deepseek', 'deepsek'], true)) {
                return 'deepseek/deepseek-v4.1-flash';
            }
            return $m;
        })(),
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
