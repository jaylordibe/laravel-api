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

    /*
    |--------------------------------------------------------------------------
    | Health probes
    |--------------------------------------------------------------------------
    |
    | The readiness endpoint is deliberately NOT rate-limited — Laravel's limiter
    | resolves through the Redis-backed cache, so a limiter in front of it would
    | throw during the very Redis outage the probe exists to report. What bounds
    | it instead is time: every probe has a hard ceiling, so a slow dependency
    | cannot park php-fpm workers until the pool is exhausted.
    |
    */
    'health' => [
        'database_timeout_milliseconds' => (int) env('HEALTH_DB_TIMEOUT_MS', 3000),
    ],

    /*
     * NOTE: trusted proxies are NOT configured here.
     *
     * They live in config/trustedproxy.php, because Laravel's TrustProxies
     * middleware reads `config('trustedproxy.proxies')` itself — including the
     * comma-splitting, trimming and '*' handling. Re-implementing that parsing
     * here and pushing the result into the middleware from a service provider
     * is what this template used to do; the framework already does it.
     */

    /*
    |--------------------------------------------------------------------------
    | Horizon dashboard access
    |--------------------------------------------------------------------------
    |
    | Comma-separated emails allowed to open the Horizon dashboard outside local.
    | EMPTY denies everyone, which is the correct default: failed-job payloads
    | shown there carry whatever the job was handed.
    |
    */
    'horizon_dashboard_emails' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('HORIZON_DASHBOARD_EMAILS', ''))),
        static fn (string $email): bool => $email !== ''
    )),

    /*
    |--------------------------------------------------------------------------
    | Object storage
    |--------------------------------------------------------------------------
    |
    | Which disk App\Utils\FileUtil reads and writes, and how long the signed URLs
    | it hands out remain valid.
    |
    | The disk defaults to the application's default disk (FILESYSTEM_DISK), so
    | swapping storage providers is one environment variable and no code change.
    | It is a separate setting so a fork can send user uploads somewhere other
    | than the default disk without redefining what "default" means.
    |
    */
    'storage' => [
        'disk' => env('APP_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),

        /*
         * Signed-URL lifetime in minutes. Short on purpose: a signed URL is a
         * bearer credential for one object, and it is routinely pasted into chat,
         * logged by an intermediary and left in browser history. Long enough to
         * click, not long enough to circulate.
         */
        'temporary_url_ttl' => (int) env('APP_STORAGE_TEMPORARY_URL_TTL', 15),

        /*
         * Upload limits for user-supplied images, applied in the Form Request.
         */
        'max_image_upload_kilobytes' => (int) env('APP_MAX_IMAGE_UPLOAD_KB', 5120),
    ],
];
