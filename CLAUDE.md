# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A **Laravel 12 / PHP 8.5 API starter template** (the base every new API project is forked from). It ships a small set of framework resources — `User` (+ auth via Laravel Passport), `AppVersion`, `DeviceToken`, `ActivityLog`, `JobStatus`, `Constant` — and a strict layered architecture that every new resource must follow. Stack: MySQL, Redis queues via Laravel Horizon, Passport (OAuth2), Spatie permission/activitylog/laravel-data, `brick/math` for decimals, Snappy (wkhtmltopdf) + PhpSpreadsheet for exports.

The whole stack runs in Docker. Almost every command below runs *inside* the `${SERVICE_NAME}-api` container (`SERVICE_NAME=laravel` by default, so the container is `laravel-api`; a forked project renames this in `.env`).

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

**Formatting: do NOT run `./vendor/bin/pint` (or any auto-formatter).** Pint is an unused transitive dev dependency; the codebase does not follow Pint's preset and existing files fail `pint --test`. Running it reformats files away from the project's actual style. Match the surrounding hand-written style by hand (see "Code style" below).

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

Settings:
- `.claude/settings.json` (committed) — hooks: Claude must never run `git commit`/`git push`; editing anything under `database/migrations/` prompts for confirmation.
- `.claude/settings.local.json` (per-developer, gitignored) — Bash/WebFetch permission allowlist.

## Code style (match existing files, not Pint)

One blank line after a class's opening `{` and before its closing `}`; constructor/method opening brace on its own line (never `) {}`); `new Foo()` with parentheses; `!empty(...)` with no space after `!`; full `@param`/`@return`/`@throws` PHPDoc on every method.
