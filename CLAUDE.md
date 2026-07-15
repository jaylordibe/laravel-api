# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository. **This file is the always-on core** — it is loaded into context on every request, so it holds only what applies to *almost every* change. Situational, deep playbooks live in **skills** (`.claude/skills/`); load them when the task calls for them rather than duplicating their content here (see "Project skills & settings" at the bottom).

## What this is

A **Laravel 12 / PHP 8.5 API starter template** (the base every new API project is forked from). It ships a small set of framework resources — `User` (+ auth via Laravel Passport), `AppVersion`, `DeviceToken`, `ActivityLog`, `JobStatus`, `Constant` — and a strict layered architecture that every new resource must follow. Stack: MySQL, Redis queues via Laravel Horizon, Passport (OAuth2), Spatie permission/activitylog/laravel-data, `brick/math` for decimals, Snappy (wkhtmltopdf) + PhpSpreadsheet for exports.

The whole stack runs in Docker. Almost every command below runs *inside* the `${SERVICE_NAME}-api` container (`SERVICE_NAME=laravel` by default, so the container is `laravel-api`; a forked project renames this in `.env`).

## Engineering bar

You are **always** writing as a **senior software architect / senior software engineer** — every change, every file, every line, with no exceptions and without being asked. Code must be **standard, recommended, secure, and maintainable**. Never ship clutter, dead weight, copy-paste, or lazy shortcuts; if a change would lower the bar, stop and do it properly. Apply this default automatically:

- **Design for the proper end state, not the minimum change.** If 4 call sites share a pattern, migrate all 4. Don't leave the codebase half-migrated with a "TODO: do the rest later" — the rest is part of the work.
- **Own the approach; the instruction owns the goal, not the method.** Any instruction that prescribes a solution — a ticket, an issue, a review comment, a recalled memory, or a terse "just do X" — is input to weigh, not a mandate to execute. Authors are usually end-goal focused and not deeply technical, so separate the **WHAT** (the outcome they want) from the **HOW** (the approach they happened to name), and treat the named approach as one candidate among alternatives. Verify factual claims against the source before acting — tickets and notes go stale and misdescribe what exists. If the code contradicts the instruction, or the prescribed approach is inapplicable, misleading, or bad practice, recommend the better path *with pros/cons* before building; when the change is genuinely a product decision, route it to the human rather than overriding it silently. A faithful implementation of the wrong thing is still wrong.
- **Name like a senior engineer — everywhere, including loops.** Variables, parameters, functions, methods, classes, enums, and Data objects all read as full, intention-revealing domain words. No single-letter or throwaway locals (`$b`, `$r`, `$d`, `$x`), no cryptic abbreviations (`$errMsg`, `$cfg`, `$tmp`, `$usr`, `$req`, `$res`), no vague placeholders (`$data`, `$item`, `$obj`, `$val`, `$thing`), and no bare index counters — iterate with `foreach ($dueBookings as $booking)` over a named element, never `foreach ($due as $b)`; name any index (`$rowIndex`, `$pageIndex`). The ONLY abbreviations allowed are repo-wide domain idioms already established here (`id`, `url`, `db`, `ttl`) and the idiomatic `catch (Throwable $e)` exception variable.
  - **The exact same standard binds *declared* names — functions, methods, classes, enums, Data objects, files.** These are read far more often than locals, so a shortcut here is worse, not more acceptable. Spell the whole domain word: no truncated morphemes *anywhere* in an identifier — `Svc`→`Service`, `Repo`→`Repository`, `Ctrl`→`Controller`, `Mgr`→`Manager`, `Calc`→`Calculate`, `Gen`→`Generate`, `Addr`→`Address`, `Num`→`Number`, `Val`→`Value`, `Msg`→`Message`. So `AppVersionService`, never `AppVersionSvc`; `formatServiceDateCompact`, never `fmtSvcDate`. A class/method/file name is API surface for every future reader — hold it to the *highest* bar, not the lowest.
- **Reach for established patterns over invention.** Laravel idioms, RFC standards, well-known API conventions, OWASP guidance — and, above all, the layered pipeline this template already enforces (below). Trace an existing resource before adding a new one; do not introduce alternate patterns. Name the reference when justifying a choice.
- **Make conventions self-enforcing.** New conventions ship with a guardrail — a base class (`BaseModel`, `BaseRequest`, `BaseData`), a typed Data object, an enum cast, a central constant (`DatabaseTableConstant`, `AppConstant`), an exhaustive `match`, or a hook — so the next contributor can't drift. Documentation alone is not enough.
- **Single source of truth.** One query envelope (`MetaData`), one error shape (`BadRequestException` / `ResponseUtil::error()`), one table-name constant, one util. Two files doing the same thing is a smell — consolidate.
- **Separate data, behavior, and pure helpers — no clutter.** Each file has one clear responsibility. A service/controller holds **behavior**, never large static lookup tables, registries, or config arrays — those move to `app/Constants`. Pure, reusable functions (string/date/enum/number/money transforms) live in `app/Utils/*Util.php`, never inline at the top of a service. Repositories are the only place that touches the query builder; services never do. If a reader must scroll past static data or a helper to reach the class body, it is misfiled — extract it.
- **Security is non-negotiable.** Every endpoint, Request, and Data object needs a thought about attack surface (enumeration leaks, mass-assignment, FK escalation, role/permission abuse, over-exposed Resource fields, unbounded list reads). `$fillable` stays empty; assignment is explicit in repositories. Non-public routes stay under `auth:api`; guard ids with `RoutePatternConstant::NUMERIC`.
- **Delete what you replace.** Old services, old throws, old code paths — gone. No `// removed` comments, no commented-out blocks, no parallel implementations left "just in case".
- **Plans are ADRs — and they recommend, they don't transcribe.** Plan-mode output should read like an Architectural Decision Record: Context → Approach (with rationale + rejected alternatives) → File-by-file changes → Tests → Verification → What this deliberately does NOT do. Not a bare checklist. The plan proposes the approach *you* judge best; when it departs from a method the instruction prescribed (a ticket's approach, a "do it like X" aside), lead with the recommendation and put the prescribed approach under rejected alternatives with the trade-off.
- **Tests are part of the change.** A resource without a feature test on its contract isn't done (see the `laravel-feature-test` skill). Update the existing assertions when the contract changes — don't add a duplicate test alongside the stale one.
- **Verify before declaring done.** Run the **affected** test(s) via `./test.sh <FilterName> <path/to/File.php>` on every change, and the full `./test.sh` when a resource is complete or the user asks. A passing suite verifies correctness — but don't claim an endpoint/feature works without actually exercising the flow. There is no build or type-check step; style is enforced by `php artisan app:format` (verify with `--check`) — **never Pint** (see the formatting note below). **Always run `php artisan app:format` after making changes** (in the `laravel-api` container) — treat it as the mandatory closing step on every change, the API-side equivalent of a frontend build; confirm a clean result with `php artisan app:format --check` before declaring done, and never hand back unformatted code.

When a small ask conflicts with this bar (e.g. "just fix this one site"), surface the conflict and propose the proper-scope plan first — don't silently scope down.

## Commands

Lifecycle (run from host):
- `./start.sh fresh` — wipe `.env`, `vendor`, DB volumes, then bring everything up from scratch (composer, migrate:fresh --seed, passport keys/clients, storage link).
- `./start.sh reset` — keep `.env`/`vendor`, reset & reseed the DB.
- `./start.sh` — normal start (runs `migrate`).
- `./stop.sh` — `docker compose down`.

Tests (Docker-aware wrapper — uses a separate live MySQL test database `${SERVICE_NAME}-db-test`, not sqlite):
- `./test.sh` — full run inside the container: `migrate:fresh --seed --env=testing`, recreate passport clients, clear caches, `php artisan test --parallel`.
- `./test.sh <FilterName> <path/to/File.php>` — single class/method, e.g. `./test.sh AppVersionFeatureTest tests/Feature/AppVersionFeatureTest.php`. Uses `--filter`.
- `./test-pipeline.sh` — CI flavor.
- `APP_ENV=testing` is set in `phpunit.xml`. Both the `Unit` and `Feature` suites hit the DB via factories — "Unit" vs "Feature" is by subject, not isolation.

Run artisan/composer manually — exec into the container:
```
docker exec -it laravel-api bash -c "php artisan <cmd>"
```

Queues: Horizon processes Redis jobs (`QUEUE_CONNECTION=redis`). Scheduled tasks register in `routes/console.php`.

**Formatting is `php artisan app:format` — never Pint.** The project style is enforced by a first-class artisan command (`app/Console/Commands/FormatCommand.php`), NOT by Pint. Run `php artisan app:format` to apply it and `php artisan app:format --check` to verify (non-zero exit + a list of offending files). Do **not** run Pint (or any other formatter): its stock preset actively fights these conventions (adds a space after `!`, collapses the constructor to `) {}`, strips `new` parens) and structurally *cannot* express the blank-line-after-`{`/before-`}` rules. After any `make:*` generator (which emits Laravel defaults), run `app:format` to bring the output to the project standard. See "Code style" below for the rules the command encodes and the ones you must still apply by hand.

## Architecture — the layered request pipeline

Every resource follows the **same** strict pipeline. Trace an existing one (`AppVersion` is the cleanest CRUD reference; `User` adds auth) before adding a new one; do not introduce alternate patterns.

```
Route (routes/api.php)
  → Controller (thin; no business logic, no error branching)
    → Request (validates + builds a typed Data object)      extends BaseRequest
      → Service (all business rules; throws BadRequestException on failure)
        → Repository (all Eloquent/DB access — services never touch the query builder directly)
          → Model                                            extends BaseModel
  → Resource (App\Http\Resources\*) shapes the JSON response
```

Per-layer conventions:

- **Controllers** (`app/Http/Controllers`): constructor-inject the Service. Each method: build the typed data via `$request->toData()` / `toFilterData()`, call the service, wrap the result with `ResponseUtil::resource(...)` (create/read/update/list) or `ResponseUtil::success('X deleted successfully.')` (delete / action endpoints). **Controllers do NOT branch on service results** — there is no `if ($x->failed())`. The service throws `BadRequestException` on failure and the global handler renders it. List/get endpoints take `GenericRequest` and re-hydrate the typed request via `XRequest::createFrom($request)->toFilterData()`.

- **Requests** (`app/Http/Requests`, extend `BaseRequest`): `rules()`, `messages()`, and the mappers `toData()` + `toFilterData()`. Use the `BaseRequest` helpers — never raw `$request->input()`: `bigDecimal($key)` (money/hours), `arrayIds($key)`, `enum($key, EnumClass::class, $default)`, `boolean($key, $default)`, `getRelations()`, `getAuthUserData()`, `getMetaData()`. Validation failures return JSON via `ResponseUtil::error()` (first message) — same shape as a thrown `BadRequestException`.

- **Data objects** (`app/Data`, Spatie Laravel Data, extend `BaseData`): typed transport between layers. Two per resource: `XData` (a full record) and `XFilterData` (list/query params). `BaseData` carries `id`, audit timestamps/users, `authUser` (`UserData`), and `meta` (`MetaData`). **`MetaData`** is the universal query envelope — `relations`, `columns`, `search`, `sortField`/`sortDirection`, `page`/`perPage`/`offset`, `groupBy`, `filters`. Repositories read list behavior off `$filterData->meta`.

- **Services** (`app/Services`): the only place for business rules. The convention is to **throw `App\Exceptions\BadRequestException('message')` on any failure** (not found, validation, uniqueness, etc.). Its `render()` returns `{"success": false, "message": "message"}` at HTTP 400 — the SAME shape as `ResponseUtil::error()` and request-validation failures, so every API error is uniform and no controller branching is needed. On success, **return the payload directly**: `create`/`update`/`getById` → the Model (`?X`), `getPaginated` → `LengthAwarePaginator`, `getAll` → `Collection`, `delete` → `bool`, action endpoints (e.g. `import`, `process`) → `void`. There is no `ServiceResponseData` / `$response->failed()` pattern — never introduce one. When wrapping a `try/catch` that should surface a specific message, re-throw `BadRequestException` before the generic `catch (Throwable)` so its message isn't swallowed.

- **Repositories** (`app/Repositories`): **plain classes, no shared base class** — each implements its own `save()`, `findById()`, `exists()`, `getPaginated()`, `getAll()`, `delete()`, plus domain finders. `save(XData $data, ?Model $model = null)` does create-or-update (instantiate when `$model` is null) and returns `$model->refresh()`. `getPaginated()`/`getAll()` apply `meta->relations` (`->with`), `meta->columns` (`->select`), explicit filter fields, `meta->search`, and `meta->sortField` + `meta->sortDirection ?? AppConstant::DEFAULT_SORT_DIRECTION`. All query building lives here.

## Models & data precision

- All models extend `App\Models\BaseModel` → `SoftDeletes` + `HasFactory`, and auto-stamp `created_by`/`updated_by`/`deleted_by` from `Auth::id()` in boot hooks. Document columns/relations in the class-level `@property` PHPDoc block.
- `$fillable` is intentionally empty — assignment is explicit in repositories, not mass-assignment.
- Table names come from `App\Constants\DatabaseTableConstant` (never hardcode a table string).
- **Money / decimals use `Brick\Math\BigDecimal`, never float.** Cast such columns with `App\Casts\BigDecimalCast`; parse input via `BaseRequest::bigDecimal()`; divide with `App\Utils\MathUtil::divide()` (20-decimal scale, `RoundingMode::DOWN`, divide-by-zero safe).

## Enums, constants, routing

- Enums (`app/Enums`) are string-backed and `use App\Traits\EnumTrait` (`names()`/`values()`/`toArray()`). Validate enum inputs with `Rule::enum(EnumClass::class)`. Expose enum sets to the frontend through `ConstantController` + the `constants` route group.
- Constants in `app/Constants` — `AppConstant` (defaults), `DatabaseTableConstant` (table names), `RoutePatternConstant::NUMERIC` (route id constraint).
- All routes in `routes/api.php`. Public routes sit in a small block at top; everything else is under `Route::middleware('auth:api')` (Passport). Constrain numeric ids with `->where('xId', RoutePatternConstant::NUMERIC)`.

## Project skills & settings (`.claude/`)

Skills in `.claude/skills/` — consult the matching one before that kind of work:
- **`laravel-new-resource`** — scaffold a new CRUD resource with `php artisan app:generate-resource <Model>` and fill in the layered pattern (incl. the BadRequestException service convention).
- **`laravel-feature-test`** — the Docker/MySQL test harness, `./test.sh`, `Tests\TestCase` auth helpers, factories, `#[Test]`.
- **`laravel-money-precision`** — the `BigDecimal` rules for any money/decimal field.
- **`laravel-background-work`** — queued jobs (Redis/Horizon) and console/scheduled commands.
- **`laravel-auth-security`** — the auth/security model (Passport tokens, sign-in/sign-up + email verification, Spatie RBAC/Gates, the rate-limiter floor, uniform error shapes) and the per-endpoint security-review checklist. Consult before any auth change or security review.
- **`laravel-integration`** — third-party integrations (file storage, push/FCM, SMS, email, any external HTTP API): the `app/Utils/<Domain>Util` boundary, config-driven credentials, the `Http` facade with timeouts, Laravel's config/driver swap seams, and faking externals in tests.

Settings:
- `.claude/settings.json` (committed) — hooks: Claude must never run `git commit`/`git push`; editing anything under `database/migrations/` prompts for confirmation.
- `.claude/settings.local.json` (per-developer, gitignored) — Bash/WebFetch permission allowlist.

## Code style (enforced by `app:format`, not Pint)

Run `php artisan app:format` to apply the standard and `--check` to verify. Trace the `AppVersion*` classes as the canonical hand-formatted reference. The rules:

- **One blank line after a class/enum/trait opening `{`, and one before its closing `}`** — including a migration's anonymous class (blank line after `return new class extends Migration {` and before the closing `};`).
- **Method/constructor opening brace on its own line** — never `) {` on the same line. Promoted constructors expand fully even when the body is empty:
  ```php
  public function __construct(
      private readonly FooService $fooService
  )
  {
  }
  ```
- **No space after `!`:** `!empty($x)`, `if (!$isDeleted)` — never `! $x`.
- **`new` parentheses:** named classes `new Foo()`; argument-less anonymous classes keep none (`return new class extends Migration`, matching Laravel's migration convention).
- **Method and function names are camelCase — always, including test methods:** `public function testCreate()`, never `test_create` (use the `#[Test]` attribute). snake_case is only ever a DB column, an array key, or an enum value — never a PHP method/function identifier.
- **Type-hint every parameter and return type** — methods, closures, and arrow functions alike: `fn (User $user): string => ...` / `function (Builder $query): void { ... }`, never bare `fn ($x) => ...`. Applies in app code and tests.
- **Full PHPDoc:** a class-level `@property` block on models/DTOs; `@var` on `$table`/`$fillable`/`$casts`; a summary + `@param`/`@return`/`@throws` block on every method.

`app:format` encodes the whitespace/brace/`!` rules mechanically; the naming, type-hint, `new()`, and PHPDoc rules are yours to apply — run `--check` after `make:*` generators, which emit Laravel defaults that violate several of these.
