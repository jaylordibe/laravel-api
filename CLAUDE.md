# CLAUDE.md

Guidance for Claude Code working in this repository. This file is the always-on core: it is loaded on every request, so it holds only what applies to *almost every* change. Situational playbooks live in `.claude/skills/`; deeper worksheets live in `docs/`. See **Deep references** at the bottom.

Engineering methodology — the gate sequence, risk tiers, evidence language, review lenses and the human-owned operations list — comes from the **`engineering-framework`** plugin and is deliberately **not restated here**. A second copy drifts and nothing can detect that it has.

**Precedence.** Where a generic framework standard conflicts with a rule in this file, **this file wins**: the framework describes how to work, this file describes what is true. This file also supersedes any parent-workspace `CLAUDE.md` for work in this repository — in particular, plans are presented through Claude Code's plan flow, and this repository has no `tasks/` directory; do not create one.

## Project

A **Laravel 13 / PHP 8.5 API starter template** — the base every new API project is forked from. It ships a small set of framework resources (`User` with Passport auth, `AppVersion`, `DeviceToken`, `ActivityLog`, `JobStatus`, `Constant`) and a strict layered architecture every new resource must follow.

**Stack:** MySQL; Redis queues via Laravel Horizon; Laravel Passport (OAuth2 bearer tokens) for authentication; Spatie `laravel-permission` for roles/permissions, `laravel-activitylog` for audit, `laravel-data` for typed DTOs; `brick/math` for decimals; `dedoc/scramble` for generated OpenAPI; Snappy (wkhtmltopdf) + PhpSpreadsheet for exports; `spatie/laravel-google-cloud-storage` for object storage; Mailgun via `symfony/mailgun-mailer`; `imtigger/laravel-job-status` for job tracking.

**Package manager:** Composer. `composer.lock` is committed.

**Runtime:** the whole stack runs in Docker. Almost every command below runs *inside* the `${SERVICE_NAME}-api` container — `SERVICE_NAME=laravel` by default, so the container is `laravel-api`; a fork renames this in `.env`.

## Canonical commands

Run inside the container unless the row says otherwise:

```
docker exec -it laravel-api bash -c "php artisan <cmd>"
```

| Purpose | Command | Notes |
|---|---|---|
| Install | `composer install` | |
| Lint / format check | `php artisan app:format --check` | Non-zero exit + list of offending files |
| Format (apply) | `php artisan app:format` | **Mandatory closing step on every change** |
| Unit + feature tests | `php artisan test --parallel` | |
| Full test run (host) | `./test.sh` | Wrapper: `migrate:fresh --seed --env=testing`, recreates Passport clients, clears caches, then `--parallel` |
| Single test (host) | `./test.sh <FilterName> <path/to/File.php>` | e.g. `./test.sh AppVersionFeatureTest tests/Feature/AppVersionFeatureTest.php` |
| CI flavour (host) | `./test-pipeline.sh` | What `.github/workflows/test.yml` runs |
| Migration status | `php artisan migrate:status` | Read-only; applying migrations is human-owned |
| Dependency advisories | `composer audit` | |
| Start / stop (host) | `./start.sh` / `./stop.sh` | `./start.sh fresh` and `reset` are **destructive and human-owned** |

**Build: none. Type check: none.** This stack has neither — `N/A`, not a gap. Never report either as evidence.

## Architecture — the layered request pipeline

Every resource follows the **same** strict pipeline. Trace an existing one before adding a new one — `AppVersion` is the cleanest CRUD reference; `User` adds auth. Do not introduce alternate patterns.

```
Route (routes/api.php)
  → Controller (thin; no business logic, no error branching)
    → Request (validates + builds a typed Data object)      extends BaseRequest
      → Service (all business rules; throws BadRequestException on failure)
        → Repository (all Eloquent/DB access — services never touch the query builder)
          → Model                                            extends BaseModel
  → Resource (App\Http\Resources\*) shapes the JSON response
```

- **Controllers** — constructor-inject the Service. Build typed data via `$request->toData()` / `toFilterData()`, call the service, wrap with `ResponseUtil::resource(...)` (create/read/update/list) or `ResponseUtil::success('X deleted successfully.')` (delete/action). Controllers **never branch on service results** — there is no `if ($x->failed())`. List/get endpoints take `GenericRequest` and re-hydrate via `XRequest::createFrom($request)->toFilterData()`.
- **Requests** (`extends BaseRequest`) — `rules()`, `messages()`, `toData()`, `toFilterData()`. Use the `BaseRequest` helpers, **never raw `$request->input()`**: `bigDecimal()`, `arrayIds()`, `enum()`, `boolean()`, `getRelations()`, `getAuthUserData()`, `getMetaData()`.
- **Data objects** (`app/Data`, Spatie Laravel Data, `extends BaseData`) — two per resource: `XData` (full record) and `XFilterData` (list/query params). `BaseData` carries `id`, audit timestamps/users, `authUser`, and `meta`. **`MetaData` is the universal query envelope**: `relations`, `columns`, `search`, `sortField`/`sortDirection`, `page`/`perPage`/`offset`, `groupBy`, `filters`.
- **Services** — the only place for business rules. On success **return the payload directly**: `create`/`update`/`getById` → `?Model`, `getPaginated` → `LengthAwarePaginator`, `getAll` → `Collection`, `delete` → `bool`, actions → `void`.
- **Repositories** — **plain classes, no shared base class.** Each implements `save()`, `findById()`, `exists()`, `getPaginated()`, `getAll()`, `delete()`, plus domain finders. `save(XData $data, ?Model $model = null)` does create-or-update and returns `$model->refresh()`. All query building lives here.

Laravel 11+ streamlined structure: **there is no `app/Http/Kernel.php` or `app/Console/Kernel.php`.** Middleware, exception rendering and routing are configured in `bootstrap/app.php`; providers in `bootstrap/providers.php`; commands in `app/Console/Commands/` self-register; scheduled tasks in `routes/console.php`.

## Cross-cutting conventions

- **Error contract — one shape, everywhere.** Services throw `App\Exceptions\BadRequestException('message')` for *any* failure (not found, validation, uniqueness). Its `render()` returns `{"success": false, "message": "..."}` at **HTTP 400** — identical to `ResponseUtil::error()` and to request-validation failures. That uniformity is why controllers need no error branching. There is no `ServiceResponseData` / `$response->failed()` pattern; never introduce one. When wrapping a `try/catch`, **re-throw `BadRequestException` before the generic `catch (Throwable)`** or its message is swallowed.
- **Validation contract.** Every input is validated in a Form Request, never in a controller or service. Validate enums with `Rule::enum(EnumClass::class)`.
- **Authorization contract.** Non-public routes stay under `auth:api` (Passport). Roles/permissions via Spatie; expose permission sets through `ConstantController`. Constrain numeric route ids with `->where('xId', config('custom.numeric_regex'))`. **Ownership isolation lives inside repository query methods** — there is no connection-level or global-scope safety net, so a repository method that forgets its scope leaks across accounts silently.
- **Persistence contract.** All models extend `App\Models\BaseModel` → `SoftDeletes` + `HasFactory`, auto-stamping `created_by`/`updated_by`/`deleted_by` from `Auth::id()`. **`$fillable` stays empty** — assignment is explicit in repositories, which structurally removes mass-assignment as a class of bug. Casts go in a **`protected function casts(): array` method**, never a `$casts` property. Table names come from `App\Constants\DatabaseTableConstant` — never a literal.
- **Money and decimals.** `Brick\Math\BigDecimal`, **never float**. Cast with `App\Casts\BigDecimalCast`, parse via `BaseRequest::bigDecimal()`, divide with `App\Utils\MathUtil::divide()` (20-decimal scale, `RoundingMode::DOWN`, divide-by-zero safe).
- **Separation.** Static lookup tables/registries live in `app/Constants`, not in services. Pure reusable transforms live in `app/Utils/*Util.php`. Third-party integrations sit behind an `app/Utils/<Domain>Util` boundary. Non-table defaults live in `config/custom.php`, read with `config()` — **never `env()` outside `config/`**.
- **Enums** (`app/Enums`) are string-backed and `use App\Traits\EnumTrait` (`names()`/`values()`/`toArray()`).

## Non-obvious invariants

These look wrong, look deletable, or look like they could be simplified. They must not be.

- **Formatting is `php artisan app:format` — never Pint.** `laravel/pint` is deliberately **not** a dependency. Its stock preset actively fights these conventions (adds a space after `!`, collapses the constructor brace to `) {}`, strips `new` parens) and structurally cannot express the blank-line-after-`{`/before-`}` rules. It was removed so Laravel Boost stops generating "you must run Pint" guidance that contradicts this. **Re-adding it resurfaces that conflict.**
- **CI runs `app:format --check`, never the fixing form.** `app:format` exits 0 *after* rewriting what it repaired, so a fixing formatter in CI silently patches the runner's copy, passes, and throws the fixes away with the container — enforcing the style nowhere.
- **There is deliberately no default sort direction.** `getPaginated()`/`getAll()` apply `sortField` + `sortDirection` only when `sortField` is set. Adding a default silently reorders every existing list response.
- **A migration that modifies a column must restate every attribute it already had** (`nullable`, `default`, length, `unsigned`, …). Laravel rewrites the column from the definition given, so any omitted attribute is silently dropped.
- **Rate limits are config, not magic numbers.** The named limiters (`public`, `sensitive`, `api`, `heavy`) are defined in `AppServiceProvider::boot()` but read `config('custom.rate_limits.*')`. The defaults are the **production floor**; the env overrides exist for one legitimate case — an ephemeral throwaway environment being probed by a scanner (see the DAST workflow). Never raise them in a real environment to make a client or load test look better.
- **List/get handlers type-hint `GenericRequest`**, so Scramble cannot see their query parameters and documents none. Do **not** "fix" this by type-hinting the concrete Request on those handlers without a plan — that changes when validation runs.
- **Docs routes are local-only.** `GET /docs/api` and `/docs/api.json` are wrapped in Scramble's `RestrictedDocsAccess` unless a `viewApiDocs` gate is defined. `api.json` is generated output and gitignored.

## Code style

`php artisan app:format` applies the standard; `--check` verifies it. It encodes the whitespace/brace/`!`/indentation rules mechanically. The rest is yours to apply by hand — run `--check` after any `make:*` generator, which emits Laravel defaults that violate several:

- One blank line after a class/enum/trait opening `{` and one before its closing `}` — including a migration's anonymous class.
- Method/constructor opening brace **on its own line**; promoted constructors expand fully even with an empty body.
- **No space after `!`:** `!empty($x)`, `if (!$isDeleted)`.
- Four spaces, never hard tabs. `new Foo()` for named classes; argument-less anonymous classes keep no parens (`return new class extends Migration`).
- **Method and function names are camelCase — always, including tests:** `public function testCreate()` with the `#[Test]` attribute, never `test_create`. snake_case is only ever a DB column, array key, or enum value.
- **Type-hint every parameter and return type**, including closures and arrow functions: `fn (User $user): string => ...`.
- Full PHPDoc: class-level `@property` on models/DTOs, `@var` on `$table`, and summary + `@param`/`@return`/`@throws` on every method (including `casts()`, which carries `@return array<string, string>`).

The only permitted abbreviations are idioms already established repo-wide (`id`, `url`, `db`, `ttl`) and the idiomatic `catch (Throwable $e)`.

## API documentation (OpenAPI)

The OpenAPI 3.1 document is **generated, never hand-written** — `dedoc/scramble` derives request shape from the Form Request's `rules()`, response shape from the API Resource, parameters from the route, and **security from route middleware**: `auth:api` routes are marked bearer-secured, anything else is marked `security: []`, i.e. explicitly public. That last point is load-bearing — a route accidentally left outside `auth:api` shows up as public in `api.json`, which is where you want to notice it. `php artisan scramble:export` writes `api.json`. Config in `config/scramble.php`.

**A new resource is documented for free** if it follows the pipeline. If an endpoint documents badly the usual cause is a real defect — a `rules()` that does not describe what the endpoint accepts, or a Resource that does not describe what it returns. Fix the code, not the annotation.

## Consumers

_(none — this repository is the starter template every API project is forked from, so it has no clients of its own. A fork must replace this row with its real consumers before its first contract change.)_

A **contract change** — any Form Request rule, Resource field, error message, HTTP status, required/optional/nullable change, enum value, pagination or ordering change, or queued-job payload — is not done when this API parses. It is done when every consumer above has either been updated or explicitly recorded as unaffected, with the deployment order stated. **In a fork, an unfilled table makes every contract change report "no consumers" and cross-repository breakage ship silently.**

## Deep references

| Task | Where |
|---|---|
| Scaffold a CRUD resource (pipeline, migration, routes, Resource, tests) | `resource-pattern` skill |
| Auth, Passport tokens, verification, Spatie RBAC/Gates, rate limiting | `auth-security` skill |
| Write feature/unit tests (harness, `./test.sh`, auth helpers, factories) | `feature-testing` skill |
| Money, rates, any decimal arithmetic | `money-precision` skill |
| Queued jobs (Redis/Horizon), console and scheduled commands | `background-work` skill |
| Third-party integrations behind an `app/Utils/<Domain>Util` boundary | `external-integration` skill |
| Horizon supervisors, metrics, dashboards | `configuring-horizon` skill |
| Passport OAuth2 grants, clients, scopes | `passport-development` skill |
| General Laravel best practices by topic | `laravel-best-practices` skill |
| API contract worksheet (envelope, pagination, consumer handoff) | `docs/api-contract.md` |
| Database design worksheet (audit columns, soft deletes, indexes) | `docs/database-design.md` |
| CI: merge gate and migration safety | `.github/workflows/test.yml` |
| CI: dependency advisories, Trivy, OWASP ZAP | `.github/workflows/security.yml`, `security-dast.yml`, `.zap/rules.tsv` |
| Deployment: what the app expects and how to wire a real target | `DEPLOYMENT.md` |

**Settings and framework integration:**

- `.claude/settings.json` (committed) — the permissions floor plus the `app:format` hook. A plugin cannot ship permission rules, so this layer is not redundant with the plugin's guard: a hook is executable code and can fail open, a deny rule cannot. They are complementary.
- `.claude/engineering-framework.json` (committed) — this repository's policy: canonical commands, repository-specific `protectedCommands` (the destructive `./start.sh` forms, in-container migrations, Passport key rotation), and `risk.highRiskPaths`.
- `.claude/settings.local.json` (per-developer, gitignored) — personal allowlist and enabled MCP servers.
- `/engineering-framework:framework-doctor` — audits this repository against the framework contract. Replaces the former `app:validate-claude-config` command.
