# Engineering conventions — laravel-api

Long-form reference moved verbatim from `AGENTS.md`. `AGENTS.md` keeps each
rule in a line or two; this file keeps the mechanics and the reasons.

## Runtime

**Runtime:** the whole stack runs in Docker. Almost every command below runs *inside* the `${SERVICE_NAME}-api` container — `SERVICE_NAME=laravel` by default, so the container is `laravel-api`; a fork renames this in `.env`.

**Local dev and production run DIFFERENT images.** `docker-compose.yml` uses the base image directly (nginx + php-fpm + Horizon in one container, serving on `:80` → host `8000`). The production image is built from the `Dockerfile`, which installs `docker/entrypoint.sh` and selects one runtime per container via `APP_RUNTIME_MODE` (`api` | `worker` | `scheduler` | `migrate` | `artisan` | `all`). `DEPLOYMENT.md` is authoritative for the runtime contract.

## Why the high-risk paths are high risk

This repository is the starter template every new API project is forked from, so a defect here propagates into every fork rather than affecting one system. Authorization is enforced per-endpoint through Passport plus Spatie permissions, and ownership isolation lives inside repository query methods rather than at the connection level — a repository method that forgets its scope leaks across accounts with no other layer to catch it. Money and decimal values are Brick\Math\BigDecimal end to end; introducing a float anywhere in that path is a correctness defect, not a style choice. Almost every command runs inside the laravel-api container, so a guard that only classifies host commands sees very little of what actually happens here.

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
- **Requests** (`extends BaseRequest`) — `rules()`, `messages()`, `toData()`, `toFilterData()`. Read input through helpers, **never raw `$request->input()`**: `bigDecimal()`, `arrayIds()`, `getRelations()`, `getSortField()`, `getMetaData()`, `getAuthUserData()` are defined on `BaseRequest`; `enum()` and `boolean()` are Laravel's own (`Illuminate\Support\Traits\InteractsWithData`), inherited — don't go looking for them in `BaseRequest`.
- **Data objects** (`app/Data`, Spatie Laravel Data, `extends BaseData`) — two per resource: `XData` (full record) and `XFilterData` (list/query params). `BaseData` carries `id`, audit timestamps/users, `authUser`, and `meta`. **`MetaData` is the universal query envelope**: `relations`, `columns`, `search`, `sortField`/`sortDirection`, `page`/`perPage`/`offset`, `groupBy`, `filters`.
- **Includes and sorts are allowlisted**, like spatie/laravel-query-builder's `allowedIncludes`/`allowedSorts`: on list and get requests a client may load only the relations in the Request's `ALLOWED_RELATIONS` (default none) and sort only by its `SORTABLE_FIELDS` (default `created_at`); anything else is a 400. Writes ignore these parameters. Resources render relations explicitly (`whenLoaded`), never implicitly.
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

- **Formatting is `php artisan app:format` — never Pint.** `laravel/pint` is deliberately **not** a dependency. Its stock preset actively fights these conventions (adds a space after `!`, collapses the constructor brace to `) {}`, strips `new` parens) and structurally cannot express the blank-line-after-`{`/before-`}` rules. Adding it also makes Laravel Boost generate "you must run Pint" guidance that contradicts this file.
- **CI runs `app:format --check`, never the fixing form.** `app:format` exits 0 *after* rewriting what it repaired, so a fixing formatter in CI silently patches the runner's copy, passes, and throws the fixes away with the container — enforcing the style nowhere.
- **The default list order is fixed at `created_at desc`** (`BaseRequest`, applied whenever the client names no sort). Changing it silently reorders every existing list response.
- **A migration that modifies a column must restate every attribute it already had** (`nullable`, `default`, length, `unsigned`, …). Laravel rewrites the column from the definition given, so any omitted attribute is silently dropped.
- **The image must NOT contain `bootstrap/cache/config.php`.** `config:cache` freezes the value of every `env()` call and then Laravel stops reading `.env` and `config/*.php` entirely. Run at build time — where `.dockerignore` has excluded `.env`, so only defaults exist — it bakes `DB_HOST=127.0.0.1` into the image and silently ignores every value the platform injects at runtime. The `Dockerfile` caches **views only**; `docker/entrypoint.sh` caches config and routes at container start. The `docker` job in `test.yml` asserts the cache is absent from the image *and* that a `docker run -e DB_HOST=…` value is what the app resolves. Do not "optimise" this back into the build.
- **`api` mode disables the base image's Horizon supervisor program, deliberately.** The base image starts nginx, php-fpm and `php artisan horizon` from one supervisord config. Leaving that on means every horizontally scaled API replica also runs a Horizon master, so queue concurrency tracks web traffic and `horizon:terminate` races across replicas — with no error anywhere. `all` mode is the single-container exception.
- **Trusted proxies are split across two files, deliberately.** WHICH PROXIES lives in `config/trustedproxy.php` — that filename and the `proxies` key are what Laravel's `TrustProxies` middleware reads as its own fallback, and the framework does the comma-splitting, trimming and `*` handling itself. WHICH HEADERS lives in `bootstrap/app.php`. The split exists because the `withMiddleware()` closure runs via `afterResolving(HttpKernel::class)`, *before* the bootstrappers load configuration, so `config()` returns nothing there — but headers are integer constants and need no lookup. Do not "tidy" this by re-parsing `TRUSTED_PROXIES` into an array yourself and pushing it in from a provider — that reimplements parsing the framework already does, in a place where the value is read too late to be trusted. `X-Forwarded-Host` and `X-Forwarded-Prefix` are excluded from the trusted set on purpose (host-header and path poisoning of generated URLs).
- **`LIKE` is case-SENSITIVE on PostgreSQL.** MySQL's `_ci` collation made it case-insensitive; the move to PostgreSQL silently turned every search into an exact-case match, with no error and no failing query. Use `App\Utils\DatabaseUtil::caseInsensitiveLikeOperator()` and `containsPattern()` for any user-facing search.
- **A migration must not use MySQL-only syntax.** `DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP')` fails on PostgreSQL; the portable form is `->useCurrent()->useCurrentOnUpdate()`, which the PostgreSQL grammar ignores (Eloquent maintains `updated_at` anyway).
- **Rate limits are config, not magic numbers.** The named limiters (`public`, `sensitive`, `sign-in`, `api`, `heavy`) are defined in `AppServiceProvider::boot()` but read `config('custom.rate_limits.*')`. The defaults are the **production floor**; the env overrides exist for one legitimate case — an ephemeral throwaway environment being probed by a scanner (see the DAST workflow). Never raise them in a real environment to make a client or load test look better.
- **List/get handlers type-hint `GenericRequest`**, so Scramble cannot see their query parameters and documents none. Do **not** "fix" this by type-hinting the concrete Request on those handlers without a plan — that changes when validation runs.
- **`.dockerignore` excludes `storage/*.key`, and that exclusion is load-bearing.** `.gitignore` has no say in what `COPY . .` copies, so before this line a `docker build` on any machine that had run `php artisan passport:keys` baked the developer's **private signing key** into a distributable image layer — invisibly, because a fresh CI checkout has no key files and the layer was clean there. Excluding them is also what keeps the failure loud: with no key files in the image, a deployment that forgot to inject `PASSPORT_PRIVATE_KEY`/`PASSPORT_PUBLIC_KEY` is refused at startup by `app:check-config` instead of quietly signing tokens with a laptop's key. The same applies to `storage/app`, `.env*` and `database/*.sqlite`; the `docker` job in `test.yml` asserts none of them ship.
- **Docs routes are gated by `App\Http\Middleware\RestrictApiDocsAccess`, not by a gate.** `GET /docs/api` and `/docs/api.json` are open in `local`, denied unconditionally in `production`, and elsewhere require `API_DOCS_ENABLED` plus a shared HTTP Basic credential (`API_DOCS_USERNAME`/`API_DOCS_PASSWORD`, `config/custom.php` → `api_docs`) — every one of which fails closed. Scramble's own `RestrictedDocsAccess` was replaced because its `viewApiDocs` gate can never pass here: these routes carry the `web` group and this API has no session login, so the caller is always a guest. That is the opposite of the Horizon dashboard, whose `viewHorizon` gate works because Horizon authenticates first. `api.json` is generated output and gitignored; `scramble:export` writes it from the console, so the DAST workflow never depends on the route being reachable.

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

**Who can read it is a separate decision from how it is generated.** The two routes are gated by `App\Http\Middleware\RestrictApiDocsAccess` (see the bullet in *Non-obvious invariants*), not by Scramble's `viewApiDocs` gate — open in `local`, denied outright in `production`, and behind a shared HTTP Basic credential everywhere else. `scramble:export` is a console command and bypasses all of it, which is why the DAST workflow uses it instead of fetching the route.

**A new resource is documented for free** if it follows the pipeline. If an endpoint documents badly the usual cause is a real defect — a `rules()` that does not describe what the endpoint accepts, or a Resource that does not describe what it returns. Fix the code, not the annotation.

## Settings and framework integration

- `.claude/settings.json` (committed) — the permissions floor plus the `app:format` hook, and the only layer here that actually blocks anything. The himoa plugin is methodology only: it ships no permission rules, so a destructive command is merely *reserved* by the session charter — which stops and hands off — rather than denied. A deny rule here is what makes the reservation unbypassable. So the destructive `./start.sh` forms and migrations are denied, and Passport key rotation is `ask`. **Every rule is written in both the host and the containerised form** (`php artisan …` *and* `docker exec*artisan …`, `docker compose exec*artisan …`): a rule written only as `php artisan db:wipe*` matches nothing, because almost nothing here is run from the host. Add a new artisan rule in every form or it does not apply.
- **Canonical commands** and **High-risk paths** above are read directly by the gates — those two headings are an interface, not documentation. There is no separate policy file; changing what the gates run or how they classify a change means editing those sections.
- `.claude/settings.local.json` (per-developer, gitignored) — personal allowlist and enabled MCP servers.
- `/himoa:framework-doctor` — audits this repository against the framework contract (the `himoa-doctor` binary the plugin puts on `PATH`). Not to be confused with `php artisan app:check-config`, which validates *runtime* configuration.
