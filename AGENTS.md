<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- laravel/framework (LARAVEL) - v13
- laravel/horizon (HORIZON) - v5
- laravel/passport (PASSPORT) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- phpunit/phpunit (PHPUNIT) - v12

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>


---

# Repository truth — laravel-api

Repository truth for every coding agent. Keep it short: only what applies to
most changes. The reasoning behind each rule lives in
[`docs/engineering-conventions.md`](docs/engineering-conventions.md);
situational playbooks live in `.claude/skills/`.

Engineering methodology (gates, risk tiers, evidence language, review lenses,
`/work-item`) comes from the `himoa` plugin and is not restated here.
**Where a framework standard conflicts with this file, this file wins.** It
also supersedes any parent-workspace `CLAUDE.md`. No `tasks/` directory.

**This file holds rules, not history.** Add a rule only when a change alters
something most changes need; feature detail belongs in the code, its tests and
`docs/`, and what changed and when belongs in the commit history.

## Project

Laravel 13 / PHP 8.5 **API starter template**: the base every new API is
forked from, so a defect here propagates into every fork. Ships `User`
(Passport auth), `AppVersion`, `DeviceToken`, `ActivityLog`, `JobStatus`,
`Constant`.

PostgreSQL; Redis queues via Horizon; Passport bearer tokens; Spatie
`laravel-permission`, `laravel-activitylog`, `laravel-data`; `brick/math`;
`dedoc/scramble` for OpenAPI. Composer, `composer.lock` committed.

Everything runs in Docker. Most commands run inside the
`${SERVICE_NAME}-api` container (`laravel-api` by default):
`docker exec -it laravel-api bash -c "php artisan <cmd>"`. Local dev and
production use different images; `DEPLOYMENT.md` owns the runtime contract.

## Canonical commands

| Purpose | Command | Notes |
|---|---|---|
| Install | `composer install` | In container |
| Lint / format check | `php artisan app:format --check` | Evidence is `--check` |
| Format (apply) | `php artisan app:format` | Mandatory closing step on every change |
| Unit + feature tests | `php artisan test --parallel` | In container |
| Full test run (host) | `./test.sh` | `--parallel`; each worker migrates + seeds its own DB (`RefreshDatabase`) |
| Single test (host) | `./test.sh <Filter> <path/to/Test.php>` | |
| CI flavour (host) | `./test-pipeline.sh` | What `.github/workflows/test.yml` runs |
| Migration status | `php artisan migrate:status` | Read-only; applying is human-owned |
| Security scan | `composer audit` | |
| Validate runtime config | `php artisan app:check-config` | Runs at container start |
| Run locally (host) | `./start.sh` / `./stop.sh` | `fresh` / `reset` are destructive, human-owned |

No build and no type check exist: `N/A`, never evidence.

## High-risk paths

| Path pattern | Why |
|---|---|
| `app/Http/Requests/*`, `app/Http/Resources/*` | The request/response contract |
| `app/Http/Middleware/*`, `routes/api.php`, `config/auth.php` | Auth and route exposure |
| `database/migrations/*` | Schema |
| `app/Providers/AppServiceProvider.php`, `bootstrap/app.php` | Rate limits, middleware, proxies |
| `config/custom.php` | Non-table defaults and limits |
| `app/Models/BaseModel.php`, `app/Data/BaseData.php`, `app/Http/Requests/BaseRequest.php` | Every resource inherits them |

## Architecture

Every resource follows one pipeline. `AppVersion` is the cleanest CRUD
reference; `User` adds auth. No alternate patterns.

```
Route (routes/api.php)
  → Controller    thin; no business logic, no error branching
    → Request     validates, builds typed Data         extends BaseRequest
      → Service   all business rules; throws BadRequestException
        → Repository  all Eloquent/DB access (plain class, no base)
          → Model                                      extends BaseModel
  → Resource (App\Http\Resources\*) shapes the JSON
```

No `app/Http/Kernel.php` or `app/Console/Kernel.php`: middleware, exceptions
and routing live in `bootstrap/app.php`, providers in `bootstrap/providers.php`,
schedules in `routes/console.php`.

## Cross-cutting conventions

- **Controllers:** `$request->toData()` / `toFilterData()` → service →
  `ResponseUtil::resource(...)` or `ResponseUtil::success('...')`. Never
  branch on service results. List/get handlers take `GenericRequest`.
- **Requests:** read input via `BaseRequest` helpers, never raw
  `$request->input()`. Enums use `Rule::enum()`.
- **Data:** two per resource, `XData` and `XFilterData` (`extends BaseData`).
  `MetaData` is the universal query envelope.
- **Services** return the payload directly (`?Model`, paginator, `Collection`,
  `bool`, `void`).
- **Errors:** throw `BadRequestException('message')` for any failure → HTTP
  400 `{"success": false, "message": ...}`. Never add a result-object
  pattern. Re-throw it before a generic `catch (Throwable)`.
- **Authorization:** non-public routes under `auth:api`; Spatie roles and
  permissions. Ownership scoping lives in repository queries only; there is
  no global safety net. Constrain numeric ids with
  `config('custom.numeric_regex')`.
- **Models** extend `BaseModel` (soft deletes, audit stamps). `$fillable`
  stays empty; casts go in a `casts()` method; table names come from
  `DatabaseTableConstant`.
- **Money:** `Brick\Math\BigDecimal`, never float. `BigDecimalCast`,
  `BaseRequest::bigDecimal()`, `MathUtil::divide()`.
- **Placement:** lookups in `app/Constants`, pure transforms in
  `app/Utils/*Util.php`, integrations behind `app/Utils/<Domain>Util`.
  Never `env()` outside `config/`.
- **Enums** are string-backed and `use EnumTrait`.
- **Style:** camelCase methods including tests (`#[Test]`), no space after
  `!`, method braces on their own line, blank line after a class `{` and
  before `}`, type-hint everything, full PHPDoc. Run `app:format --check`
  after any `make:*` generator.
- **OpenAPI is generated** by Scramble from rules, Resources and route
  middleware. Fix the code, never an annotation.

## Non-obvious invariants

- **Format with `app:format`, never Pint.** Pint is deliberately not a
  dependency. CI runs `--check`, never the fixing form.
- **No default sort direction.** Adding one reorders every list response.
- **A column-modifying migration restates every existing attribute**
  (`nullable`, `default`, length, ...) or it is silently dropped.
- **No MySQL-only migration syntax**; use `->useCurrent()->useCurrentOnUpdate()`.
- **`LIKE` is case-sensitive on PostgreSQL.** Use
  `DatabaseUtil::caseInsensitiveLikeOperator()` / `containsPattern()`.
- **The image must not contain `bootstrap/cache/config.php`.** The
  `Dockerfile` caches views only; `docker/entrypoint.sh` caches config.
- **`api` mode disables Horizon on purpose**; `all` is the single-container
  exception.
- **Trusted proxies are split on purpose:** which proxies in
  `config/trustedproxy.php`, which headers in `bootstrap/app.php`.
- **Rate limits read `config('custom.rate_limits.*')`.** Defaults are the
  production floor; never raise them in a real environment.
- **Don't type-hint concrete Requests on list/get handlers** without a plan;
  it changes when validation runs.
- **`.dockerignore` excluding `storage/*.key` is load-bearing** (also
  `storage/app`, `.env*`, `database/*.sqlite`).
- **API docs are gated by `RestrictApiDocsAccess`**, not a gate: open in
  `local`, denied in `production`, Basic auth elsewhere.
- **Destructive commands are denied in `.claude/settings.json`** in both host
  and container forms. Add a new artisan rule in every form.

## Consumers

| Consumer | Repository / location | Audience | Owner |
|---|---|---|---|
| (none — internal only: starter template with no clients of its own) | | | |

A fork must replace this row with its real consumers before its first
contract change. A contract change is done only when every consumer is
updated or recorded as unaffected, with the deploy order stated.

## Deep references

| Task | Where |
|---|---|
| Reasoning behind every rule above | `docs/engineering-conventions.md` |
| Scaffold a CRUD resource | `resource-pattern` skill |
| Auth, Passport, verification, RBAC, rate limiting | `auth-security` skill |
| Feature/unit tests | `feature-testing` skill |
| Money and decimal arithmetic | `money-precision` skill |
| Queued jobs, console and scheduled commands | `background-work` skill |
| Third-party integrations | `external-integration` skill |
| Horizon / Passport / general Laravel | `configuring-horizon`, `passport-development`, `laravel-best-practices` skills |
| API contract worksheet | `docs/api-contract.md` |
| Database design worksheet | `docs/database-design.md` |
| CI and security workflows | `.github/workflows/test.yml`, `security.yml`, `security-dast.yml` |
| Deployment and runtime modes | `DEPLOYMENT.md` |
