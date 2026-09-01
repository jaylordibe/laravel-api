<?php

use App\Http\Middleware\RestrictApiDocsAccess;

return [
    /*
     * Which routes to document. String or array form; use Scramble::routes() for custom selection.
     *
     * 'api_path' => [
     *     'include' => 'api',
     *     'exclude' => ['api/internal'],
     * ],
     *
     * Without *, patterns match path segments (api matches api and api/users, not apiary).
     * With *, Str::is is used (e.g. api/v*).
     *
     * One static include → default server is /{include} and paths are stripped (/users).
     * Multiple includes or wildcards → server defaults to / and paths stay full (/api/users).
     * Override with `servers`, or use Scramble::registerApi() for separate bases.
     */
    'api_path' => 'api',

    /*
     * Your API domain. By default, app domain is used. This is also a part of the default API routes
     * matcher, so when implementing your own, make sure you use this config if needed.
     */
    'api_domain' => null,

    /*
     * The path where your OpenAPI specification will be exported.
     */
    'export_path' => 'api.json',

    /*
     * Cache configuration for the generated OpenAPI document.
     *
     * Use `scramble:cache` to warm the cache and `scramble:clear` to invalidate it.
     */
    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'info' => [
        /*
         * API version.
         */
        'version' => env('API_VERSION', '0.0.1'),

        /*
         * Description rendered on the home page of the API documentation (`/docs/api`).
         */
        'description' => '',
    ],

    'ui' => [
        'title' => null,
    ],

    'renderer' => 'elements',

    'renderers' => [
        /*
         * Stoplight Elements config options: https://docs.stoplight.io/docs/elements/b074dc47b2826-elements-configuration-options
         */
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        /*
         * Scalar API reference config options: https://scalar.com/products/api-references/configuration
         */
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => 'https://proxy.scalar.com',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
    ],

    /*
     * The list of servers of the API. By default, when `null`, server URL will be created from
     * `scramble.api_path` and `scramble.api_domain` config variables. When providing an array, you
     * will need to specify the local server URL manually (if needed).
     *
     * Example of non-default config (final URLs are generated using Laravel `url` helper):
     *
     * ```php
     * 'servers' => [
     *     'Live' => 'api',
     *     'Prod' => 'https://scramble.dedoc.co/api',
     * ],
     * ```
     */
    'servers' => null,

    /**
     * Determines how Scramble stores the descriptions of enum cases.
     * Available options:
     * - 'description' – Case descriptions are stored as the enum schema's description using table formatting.
     * - 'extension' – Case descriptions are stored in the `x-enumDescriptions` enum schema extension.
     *
     *    @see https://redocly.com/docs-legacy/api-reference-docs/specification-extensions/x-enum-descriptions
     * - false - Case descriptions are ignored.
     */
    'enum_cases_description_strategy' => 'description',

    /**
     * Determines how Scramble stores the names of enum cases.
     * Available options:
     * - 'names' – Case names are stored in the `x-enumNames` enum schema extension.
     * - 'varnames' - Case names are stored in the `x-enum-varnames` enum schema extension.
     * - false - Case names are not stored.
     */
    'enum_cases_names_strategy' => false,

    /**
     * When Scramble encounters deep objects in query parameters, it flattens the parameters so the generated
     * OpenAPI document correctly describes the API. Flattening deep query parameters is relevant until
     * OpenAPI 3.2 is released and query string structure can be described properly.
     *
     * For example, this nested validation rule describes the object with `bar` property:
     * `['foo.bar' => ['required', 'int']]`.
     *
     * When `flatten_deep_query_parameters` is `true`, Scramble will document the parameter like so:
     * `{"name":"foo[bar]", "schema":{"type":"int"}, "required":true}`.
     *
     * When `flatten_deep_query_parameters` is `false`, Scramble will document the parameter like so:
     *  `{"name":"foo", "schema": {"type":"object", "properties":{"bar":{"type": "int"}}, "required": ["bar"]}, "required":true}`.
     */
    'flatten_deep_query_parameters' => true,

    /*
     * Middleware applied to BOTH docs routes. Scramble registers `/docs/api`
     * (the UI) and `/docs/api.json` (the document) from this one array, so
     * there is no way to gate the page and leave the document open — which is
     * the right constraint, since the document is the part that actually
     * describes the contract.
     *
     * RestrictApiDocsAccess replaces the package's own RestrictedDocsAccess.
     * The package version allows `local` and otherwise asks the `viewApiDocs`
     * gate, but no gate can pass here: these routes carry the `web` group and
     * this API has no session login, so the caller is always a guest. Opening
     * the docs on a deployed environment needs authentication, not a gate. See
     * that class for the full rule set and `custom.api_docs` for the settings.
     *
     * `throttle:api-docs` runs BEFORE the gate, deliberately. Middleware after
     * a rejection never runs, so putting the limiter second would leave
     * credential guesses uncounted — the one case where the limit matters. It
     * costs a rejected caller nothing else: document generation happens after
     * the gate, so an unauthenticated request is answered cheaply and cannot be
     * used to drive CPU.
     */
    'middleware' => [
        'web',
        'throttle:api-docs',
        RestrictApiDocsAccess::class,
    ],

    'extensions' => [],

    /*
     * Automatically document API security (OpenAPI `security` / `securitySchemes`) based on route
     * middleware.
     *
     * Disabled by default. Uncomment the line below to enable `MiddlewareAuthSecurityStrategy`.
     * When at least one documented route uses middleware matching the configured patterns (by default
     * `auth` and `auth:*`), bearer auth is applied globally. Routes without matching middleware are
     * marked as public (`security: []`).
     *
     * Set to `null` explicitly to disable. If you already configure security manually via
     * `afterOpenApiGenerated` / `extendOpenApi`, keep this disabled to avoid duplicate schemes.
     *
     * Customize with a class-string or [class, options]:
     *
     * 'security_strategy' => [
     *     \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
     *     [
     *         'middleware' => ['auth', 'auth:*'],
     *         'scheme' => \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer'),
     *     ],
     * ],
     */
    /*
     * ENABLED DELIBERATELY. Every non-public route in routes/api.php sits behind
     * `auth:api` (Passport), and this strategy reads that middleware to mark each
     * documented operation as bearer-secured — while routes without it are
     * marked `security: []`, i.e. explicitly public.
     *
     * That distinction is not cosmetic. It is the machine-readable statement of
     * which endpoints are reachable without a token, so a reviewer (and the ZAP
     * scan in .github/workflows/security-dast.yml, which reads this document)
     * sees the public surface without re-deriving it from the route file. A
     * route accidentally left outside `auth:api` shows up here as public, which
     * is exactly where you want to notice it.
     */
    /*
     * DO NOT add a 'scheme' => SecurityScheme::http('bearer') option here, even
     * though the package's own example above shows one. `php artisan
     * config:cache` serializes this array with var_export(), and a
     * SecurityScheme instance implements no __set_state(), so the object form
     * fails the cache with "value at scramble.security_strategy.1.scheme is
     * non-serializable" — which takes down every deployment that caches config
     * (the Dockerfile does, and the `docker` job in .github/workflows/test.yml
     * exists to catch exactly this). Everything in a config file must be
     * var_export-safe.
     *
     * Nothing is lost by omitting it: MiddlewareAuthSecurityStrategy's
     * constructor already defaults $scheme to SecurityScheme::http('bearer'),
     * so the documented output is byte-for-byte identical.
     */
    'security_strategy' => [
        \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
        [
            'middleware' => ['auth', 'auth:*'],
        ],
    ],
];
