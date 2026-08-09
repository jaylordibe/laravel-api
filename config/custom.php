<?php

/*
|--------------------------------------------------------------------------
| Custom Config
|--------------------------------------------------------------------------
|
| This file contains the custom config variables.
| Avoid calling the env function outside the config files.
| We are using this file in favor of configuration caching to give our application a speed boost.
| https://laravel.com/docs/10.x/configuration#configuration-caching
|
| If you execute the config:cache command during your deployment process,
| you should be sure that you are only calling the env function from within your configuration files.
| Once the configuration has been cached, the .env file will not be loaded;
| therefore, the env function will only return external, system level environment variables.
|
*/

return [
    'numeric_regex' => '[0-9]+',
    'app_domain' => env('APP_DOMAIN'),
    'app_frontend_url' => env('APP_FRONTEND_URL'),
    'sysad_email' => env('SYSAD_EMAIL'),
    'sysad_password' => env('SYSAD_PASSWORD'),
    'appad_email' => env('APPAD_EMAIL'),
    'appad_password' => env('APPAD_PASSWORD'),
    'firebase_project_id' => env('FIREBASE_PROJECT_ID'),
    'firebase_project_service_account_file' => env('FIREBASE_PROJECT_SERVICE_ACCOUNT_FILE'),

    /*
    |--------------------------------------------------------------------------
    | Rate limits (requests per minute)
    |--------------------------------------------------------------------------
    |
    | Read by the named limiters in AppServiceProvider::boot(). The defaults
    | below are the production floor and are deliberately strict — raise one
    | only with a reason, and never to work around a client that retries badly.
    |
    | They are env-overridable for exactly one legitimate case: an ephemeral
    | throwaway environment being probed by a scanner. The DAST workflow
    | (.github/workflows/security-dast.yml) raises them so the global limiters
    | do not answer most of the scan with 429 and gut its coverage. Never raise
    | them in a real environment to make a load test look better.
    |
    */
    'rate_limits' => [
        'public' => (int) env('RATE_LIMIT_PUBLIC', 60),
        'sensitive' => (int) env('RATE_LIMIT_SENSITIVE', 5),
        'api_per_token' => (int) env('RATE_LIMIT_API_PER_TOKEN', 60),
        'api_per_user' => (int) env('RATE_LIMIT_API_PER_USER', 120),
        'api_per_ip' => (int) env('RATE_LIMIT_API_PER_IP', 300),
        'heavy' => (int) env('RATE_LIMIT_HEAVY', 10),
    ],
];
