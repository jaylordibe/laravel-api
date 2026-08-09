# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository. **This file is the always-on core** — it is loaded into context on every request, so it holds only what applies to *almost every* change. Situational, deep playbooks live in **skills** (`.claude/skills/`) and general engineering standards live in `.claude/standards/`; load them when the task calls for them rather than duplicating their content here. See **Deep references** at the bottom.

**Precedence.** This file supersedes any parent-workspace `CLAUDE.md` for work in this repository. Where a parent file prescribes a different workflow, the mapping is: plans are presented through Claude Code's plan flow, not written to `tasks/todo.md`; the gate sequence below replaces any generic plan/verify loop; and this repository has no `tasks/` directory — do not create one. Corrections worth keeping become an edit to this file, not a lessons log. Where a generic standard in `.claude/standards/` conflicts with an established project contract stated here, this file wins.

## What this is

A **Laravel 13 / PHP 8.5 API starter template** (the base every new API project is forked from). It ships a small set of framework resources — `User` (+ auth via Laravel Passport), `AppVersion`, `DeviceToken`, `ActivityLog`, `JobStatus`, `Constant` — and a strict layered architecture that every new resource must follow. Stack: MySQL, Redis queues via Laravel Horizon, Passport (OAuth2), Spatie permission/activitylog/laravel-data, `brick/math` for decimals, Snappy (wkhtmltopdf) + PhpSpreadsheet for exports.

The whole stack runs in Docker. Almost every command below runs *inside* the `${SERVICE_NAME}-api` container (`SERVICE_NAME=laravel` by default, so the container is `laravel-api`; a forked project renames this in `.env`).

## Engineering bar

You are **always** writing as a **senior software architect / senior software engineer** — every change, every file, every line, with no exceptions and without being asked. Code must be **standard, recommended, secure, and maintainable**. Never ship clutter, dead weight, copy-paste, or lazy shortcuts; if a change would lower the bar, stop and do it properly. Apply this default automatically:

- **Design for the proper end state, not the minimum change.** If 4 call sites share a pattern, migrate all 4. Don't leave the codebase half-migrated with a "TODO: do the rest later" — the rest is part of the work.
- **Own the approach; the instruction owns the goal, not the method.** Any instruction that prescribes a solution — a ticket, an issue, a review comment, a recalled memory, or a terse "just do X" — is input to weigh, not a mandate to execute. Authors are usually end-goal focused and not deeply technical, so separate the **WHAT** (the outcome they want) from the **HOW** (the approach they happened to name), and treat the named approach as one candidate among alternatives. Verify factual claims against the source before acting — tickets and notes go stale and misdescribe what exists. If the code contradicts the instruction, or the prescribed approach is inapplicable, misleading, or bad practice, recommend the better path *with pros/cons* before building; when the change is genuinely a product decision, route it to the human rather than overriding it silently. A faithful implementation of the wrong thing is still wrong.
- **Name like a senior engineer — everywhere, including loops.** Variables, parameters, functions, methods, classes, enums, and Data objects all read as full, intention-revealing domain words. No single-letter or throwaway locals (`$b`, `$r`, `$d`, `$x`), no cryptic abbreviations (`$errMsg`, `$cfg`, `$tmp`, `$usr`, `$req`, `$res`), no vague placeholders (`$data`, `$item`, `$obj`, `$val`, `$thing`), and no bare index counters — iterate with `foreach ($dueBookings as $booking)` over a named element, never `foreach ($due as $b)`; name any index (`$rowIndex`, `$pageIndex`). The ONLY abbreviations allowed are repo-wide domain idioms already established here (`id`, `url`, `db`, `ttl`) and the idiomatic `catch (Throwable $e)` exception variable.
  - **The exact same standard binds *declared* names — functions, methods, classes, enums, Data objects, files.** These are read far more often than locals, so a shortcut here is worse, not more acceptable. Spell the whole domain word: no truncated morphemes *anywhere* in an identifier — `Svc`→`Service`, `Repo`→`Repository`, `Ctrl`→`Controller`, `Mgr`→`Manager`, `Calc`→`Calculate`, `Gen`→`Generate`, `Addr`→`Address`, `Num`→`Number`, `Val`→`Value`, `Msg`→`Message`. So `AppVersionService`, never `AppVersionSvc`; `formatServiceDateCompact`, never `fmtSvcDate`. A class/method/file name is API surface for every future reader — hold it to the *highest* bar, not the lowest.
- **Reach for established patterns over invention.** Laravel idioms, RFC standards, well-known API conventions, OWASP guidance — and, above all, the layered pipeline this template already enforces (below). Trace an existing resource before adding a new one; do not introduce alternate patterns. Name the reference when justifying a choice.
- **Make conventions self-enforcing.** New conventions ship with a guardrail — a base class (`BaseModel`, `BaseRequest`, `BaseData`), a typed Data object, an enum cast, a central constant (`DatabaseTableConstant`), a `config/custom.php` value, an exhaustive `match`, or a hook — so the next contributor can't drift. Documentation alone is not enough.
- **Single source of truth.** One query envelope (`MetaData`), one error shape (`BadRequestException` / `ResponseUtil::error()`), one table-name constant, one util. Two files doing the same thing is a smell — consolidate.
- **Separate data, behavior, and pure helpers — no clutter.** Each file has one clear responsibility. A service/controller holds **behavior**, never large static lookup tables, registries, or config arrays — those move to `app/Constants`. Pure, reusable functions (string/date/enum/number/money transforms) live in `app/Utils/*Util.php`, never inline at the top of a service. Repositories are the only place that touches the query builder; services never do. If a reader must scroll past static data or a helper to reach the class body, it is misfiled — extract it.
- **Security is non-negotiable.** Every endpoint, Request, and Data object needs a thought about attack surface (enumeration leaks, mass-assignment, FK escalation, role/permission abuse, over-exposed Resource fields, unbounded list reads). `$fillable` stays empty; assignment is explicit in repositories. Non-public routes stay under `auth:api`; guard ids with `config('custom.numeric_regex')`.
- **Delete what you replace.** Old services, old throws, old code paths — gone. No `// removed` comments, no commented-out blocks, no parallel implementations left "just in case".
- **Plans recommend, they don't transcribe.** Plan-mode output should read like a decision record: Context → Approach (with rationale + rejected alternatives) → File-by-file changes → Tests → Verification → What this deliberately does NOT do. Not a bare checklist. The plan proposes the approach *you* judge best; when it departs from a method the instruction prescribed (a ticket's approach, a "do it like X" aside), lead with the recommendation and put the prescribed approach under rejected alternatives with the trade-off. Structure it on `.claude/templates/plan.md`, at the depth the risk tier below requires.
- **"What do you think / what do you recommend" means PLAN, not execute.** When the user asks for your thoughts, opinion, or a recommendation, respond with senior-level planning — the analysis, the options with trade-offs, and your recommended approach — then **stop and wait**. Do NOT start editing files, writing migrations, or otherwise implementing. Implementation begins only when the user explicitly says to go ahead (e.g. "implement it", "do it", "go"). A plan or recommendation is never itself a green light.
- **Tests are part of the change.** A resource without a feature test on its contract isn't done (see the `feature-testing` skill). Update the existing assertions when the contract changes — don't add a duplicate test alongside the stale one.
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

**Formatting is `php artisan app:format` — never Pint.** The project style is enforced by a first-class artisan command (`app/Console/Commands/FormatCommand.php`), NOT by Pint. Run `php artisan app:format` to apply it and `php artisan app:format --check` to verify (non-zero exit + a list of offending files). Do **not** run Pint (or any other formatter): its stock preset actively fights these conventions (adds a space after `!`, collapses the constructor to `) {}`, strips `new` parens) and structurally *cannot* express the blank-line-after-`{`/before-`}` rules. `laravel/pint` is deliberately **not** a dependency — it was removed so Laravel Boost stops generating "you must run Pint" guidance that contradicts this rule. Re-adding it would resurface that conflict. After any `make:*` generator (which emits Laravel defaults), run `app:format` to bring the output to the project standard. See "Code style" below for the rules the command encodes and the ones you must still apply by hand.

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

- **Repositories** (`app/Repositories`): **plain classes, no shared base class** — each implements its own `save()`, `findById()`, `exists()`, `getPaginated()`, `getAll()`, `delete()`, plus domain finders. `save(XData $data, ?Model $model = null)` does create-or-update (instantiate when `$model` is null) and returns `$model->refresh()`. `getPaginated()`/`getAll()` apply `meta->relations` (`->with`), `meta->columns` (`->select`), explicit filter fields, `meta->search`, and `meta->sortField` + `meta->sortDirection` (applied only when `sortField` is set — there is deliberately no default sort direction). All query building lives here.

## Models & data precision

- All models extend `App\Models\BaseModel` → `SoftDeletes` + `HasFactory`, and auto-stamp `created_by`/`updated_by`/`deleted_by` from `Auth::id()` in boot hooks. Document columns/relations in the class-level `@property` PHPDoc block.
- **Casts go in a `protected function casts(): array` method, never a `$casts` property.** This is the Laravel 11+ form and what `app:generate-resource` emits.
- `$fillable` is intentionally empty — assignment is explicit in repositories, not mass-assignment.
- Table names come from `App\Constants\DatabaseTableConstant` (never hardcode a table string).
- **When a migration modifies an existing column, restate every attribute it already had** (`nullable`, `default`, length, `unsigned`, …). Laravel rewrites the column from the definition given, so any attribute you omit is silently dropped.
- **Money / decimals use `Brick\Math\BigDecimal`, never float.** Cast such columns with `App\Casts\BigDecimalCast`; parse input via `BaseRequest::bigDecimal()`; divide with `App\Utils\MathUtil::divide()` (20-decimal scale, `RoundingMode::DOWN`, divide-by-zero safe).

## Enums, constants, routing

- Enums (`app/Enums`) are string-backed and `use App\Traits\EnumTrait` (`names()`/`values()`/`toArray()`). Validate enum inputs with `Rule::enum(EnumClass::class)`. Expose enum sets to the frontend through `ConstantController` + the `constants` route group.
- Constants in `app/Constants` — currently just `DatabaseTableConstant` (table names). Non-table defaults live in `config/custom.php` and are read with `config()`.
- All routes in `routes/api.php`. Public routes sit in a small block at top; everything else is under `Route::middleware('auth:api')` (Passport). Constrain numeric ids with `->where('xId', config('custom.numeric_regex'))` — this is what `app:generate-resource` emits, so it is the pattern to match.
- **Laravel 11+ streamlined structure — there is no `app/Http/Kernel.php` or `app/Console/Kernel.php`.** Middleware, exception rendering, and routing are configured declaratively in `bootstrap/app.php`; service providers are listed in `bootstrap/providers.php`; console commands in `app/Console/Commands/` register themselves, and scheduled tasks go in `routes/console.php`.
- **Rate limits are config, not magic numbers.** The named limiters (`public`, `sensitive`, `api`, `heavy`) are defined in `AppServiceProvider::boot()` but read their values from `config('custom.rate_limits.*')`. The defaults are the production floor; the env overrides exist for **one** legitimate case — an ephemeral throwaway environment being probed by a scanner (see the DAST workflow). Never raise them in a real environment to make a client or a load test look better.

## API documentation (OpenAPI)

The OpenAPI 3.1 document is **generated, never hand-written** — `dedoc/scramble` derives it from the code that already exists:

- **request shape** from the Form Request's `rules()`;
- **response shape** from the API Resource;
- **path/query parameters** from the route definition;
- **security** from route middleware — `auth:api` routes are marked bearer-secured, and anything without it is marked `security: []`, i.e. **explicitly public**.

That last point is the load-bearing one: the document is the machine-readable statement of this API's public surface. A route accidentally left outside `auth:api` shows up as public in `api.json`, which is where you want to notice it.

- Docs UI: `GET /docs/api`. Document: `GET /docs/api.json`. Both are wrapped in Scramble's `RestrictedDocsAccess`, so they are **local-only** unless a `viewApiDocs` gate is defined — do not expose them in production without deciding that deliberately.
- `php artisan scramble:export` writes `api.json` (gitignored — it is generated output, not a source file).
- Configuration lives in `config/scramble.php`. `api_path` is `api`; `security_strategy` is enabled deliberately, with the reasoning in a comment beside it.
- **A new resource is documented for free**, provided it follows the layered pipeline. If an endpoint documents badly, the usual cause is a real defect: a `rules()` that does not describe what the endpoint accepts, or a Resource that does not describe what it returns. Fix the code, do not paper over it with an annotation.
- **Known gap:** list/get handlers type-hint `GenericRequest` and re-hydrate via `XRequest::createFrom($request)->toFilterData()`, so Scramble cannot see their query parameters and documents none. Filter/sort/pagination parameters are therefore missing from those operations. Do not "fix" this by type-hinting the concrete Request on those handlers without a plan — that changes when validation runs.

## Consumers

**FILL THIS IN after cloning this starter.** Every client that programs against this API goes in the table, and it is load-bearing: `gate-design`, `gate-implement`, and `gate-review` all ask "which consumers does this change force a matching change in?", and with an empty table the honest answer is always "none". A contract change that silently skips a consumer is how a broken client reaches a real user, and it is the single most common way a multi-repo change goes wrong.

| Consumer | Repo / location | Audience | Owner |
|---|---|---|---|
| _(none declared yet)_ | | | |

A **contract change** — any Form Request rule, Resource field, error message, HTTP status, required/optional/nullable change, enum value, pagination or ordering change, or queued-job payload — is not done when this API parses. It is done when every consumer in this table has either been updated or been explicitly recorded as unaffected, with the deployment order stated. Cross-repo work is a **handoff note plus a blocker**, never a sentence buried in a summary; if the other repo is owned by someone else, say who and what must ship first.

If this API genuinely has no external consumers, replace the row with `_(none — internal only)_` and say why. `php artisan app:validate-claude-config` fails while the placeholder is still there, because an unfilled table and a deliberately empty one are indistinguishable to every later reader.

## Engineering workflow and gates

Use the engineering workflow for any material feature, bug, refactor, contract change, schema change, authorization change, background job, integration, or change whose blast radius is unclear.

**These five gates are human-invoked and Claude cannot start them.** Each sets `disable-model-invocation: true`, which removes it from Claude's context entirely — a Skill call to one of them is not possible, by design. When work is proceeding gate-by-gate, Claude's obligation is therefore to **stop and ask the user to run the next gate**, never to claim a gate ran, and never to simulate one from memory. `/work-item <key | URL | requirement>` (`.claude/skills/work-item/SKILL.md`) is the top-level conductor that walks all five in one session; the individual gates below are for non-work-item work, focused operation, or recovery in a fresh session.

1. **`/gate-design <requirement>` — understand and decide.**
   - Start with the `context-mapper` agent when impact is unclear or cross-cutting.
   - Reconcile the ticket's WHAT/HOW against repository reality.
   - Classify risk first — **the tier decides the artifact.** Low risk gets no plan document at all (say so and hand back; the later gates still run); Medium and above get a plan whose depth matches the tier.
   - Evaluate alternatives and threat-model relevant surfaces in proportion to that tier.
   - Stop at the approval gate. A presented plan is not permission to implement.

2. **`/gate-approve` — take the human's decision.**
   - Read the recommendation, rejected alternatives, residual risk, non-goals, and **every unresolved blocker** back to the user first. An approval nobody re-read is a rubber stamp.
   - Take an explicit decision — never infer one from praise — then record what was approved and every condition attached to it.
   - Claude cannot invoke this gate. That, not the typing, is what stops a design from approving itself.

3. **`/gate-implement` — build only the approved design.**
   - There must be a plan the human explicitly approved in this session.
   - Preserve unrelated worktree changes.
   - Implement the approved behavior, security controls, tests, and observability.
   - A material divergence in behavior, architecture, contract, migration, or risk requires renewed approval.

4. **`/gate-review` — independently challenge the diff.**
   - Review architecture, correctness, security, tests, API contracts, database behavior, concurrency, performance, and reliability as relevant.
   - Verify every finding against the source before acting.
   - Fix confirmed findings within approved scope, add regression coverage, then re-review.
   - No unresolved Critical or High finding may pass this gate.

5. **`/gate-validate` — prove it with evidence.**
   - Validation is read-only: do not modify source, tests, migrations, config, or `composer.lock` to manufacture a pass, and never run the fixing form of the formatter.
   - Run the canonical checks appropriate to the change and risk.
   - Report exactly `PASS`, `FAIL`, or `BLOCKED`; skipped, partial, unavailable, or flaky checks are never `PASS`.

Manual/ad-hoc work does not bypass the final gates. After implementation, **stop and tell the user to run `/gate-review`, then `/gate-validate`** — summarise what changed, name the affected contracts and risk tier, and wait. An ad-hoc self-check is not a substitute for either gate and must never be reported as one.

**A `/work-item` run is the exception, and runs to completion on its own.** Invoking it authorises the whole pipeline, so it interrupts the human exactly twice: at **plan approval** (presented via `ExitPlanMode`, so the decision is a click) and at **Stage 6**, where they review the diff and push. Every panel, check, and stop condition still runs at full strength; only the prompting is removed, and on High/Critical work the review's independent subagent fan-out becomes mandatory to preserve the independence a human checkpoint used to supply. The exhaustive list of what still stops a run mid-pipeline is in `.claude/standards/gate-handoff.md` §5.

### Ending a gate

How a gate closes depends on how it was entered — `.claude/standards/gate-handoff.md` §0 holds the mode table, and every gate reads it first.

**Standalone** (a human typed the command): state the outcome, what changed on disk, the evidence that actually ran, anything blocking the next gate, then **name the next command with its argument already filled in** and offer to continue.

**Conductor** (a `/work-item` stage): the same close, then a one-line stage marker and straight on to the next stage. No next-command line, no offer.

In standalone mode, answering "yes" authorises the **next gate only** — never a skip, never the rest of the pipeline, never a Git or deployment write.

### Skill naming

Every **user-invocable** project skill is namespaced `gate-*`. A project skill sharing a name with a Claude Code built-in does not win — it appears *beside* it in the `/` menu, and the user picks by row. A reserved-name denylist cannot prevent this because a new built-in can ship at any time. `php artisan app:validate-claude-config` enforces the prefix. The domain playbook skills need none: they set `user-invocable: false` and never reach the menu. `/work-item` is the one reviewed exemption — it is the conductor, not a gate.

### Where the design lives

**The design is a plan, not a committed file.** `/gate-design` writes it through Claude Code's native plan flow and presents it with `ExitPlanMode`. Nothing is written to the repository, and approval is the plan-mode decision.

The *decision* is the user's and Claude never infers it. `/gate-approve` sets `disable-model-invocation: true`, so **Claude cannot invoke it** — only a human can. Git writes stay denied, so the user's commit remains the act of record.

What this trades away, stated plainly: the plan is local and unshared, CI cannot enforce anything about it, and a commit no longer links to the reasoning behind it. When a decision genuinely needs to outlive the session — a non-obvious invariant, a line that looks deletable but is not — put that explanation in a **code comment next to the thing it protects**, which is where it will actually be found. The pull-request body carries the rest.

### Risk classification

| Risk | Typical examples | Minimum expectation |
|---|---|---|
| **Low** | Copy/docs, isolated internal rename, test-only cleanup with no contract effect | **No plan document.** Focused design reasoning, affected tests, self-review + `/code-review`, validation |
| **Medium** | Ordinary business logic, endpoint behavior, resource-level job or refactor | Plan (brief threat model), correctness review + the one domain lens touched, feature-test coverage |
| **High** | Authentication, authorization, permissions, ownership isolation, PII, money/pricing, uploads, webhooks, external integrations, migrations, public contracts, concurrency | Full plan, explicit threat model, negative + authorization tests, migration/rollback analysis, multi-lens review with adversarial verification of Critical/High findings |
| **Critical** | Identity infrastructure, cryptography, broad privileged access, destructive data work, production repair, release infrastructure | All High gates plus qualified human security and operational review; never give unconditional automated approval |

**Take the higher tier whenever the change sits on a boundary.** The tiering is what keeps the process honest in both directions: a uniform ceremony gets skipped, and a skipped step reads exactly like a completed one — so refusing to write a plan for a copy fix is what keeps the plan meaningful for a schema change.

### Evidence language

- **`PASS`** means the required command/check actually ran successfully for the stated scope.
- **`FAIL`** means it ran and failed.
- **`BLOCKED`** means it could not run or required evidence is unavailable — a stopped container is `BLOCKED`, never a silently skipped check.
- **`NOT RUN`**, skipped, filtered, partial, or flaky is not `PASS`.
- A passing test suite does not prove its assertions are sufficient; static review does not prove the absence of vulnerabilities. **There is no build or type-check step in this stack — never report one as evidence.**
- Never claim "secure," "battle-tested," "production-ready," "works," or "done" more broadly than the evidence supports.

### Human-owned operations

Unless the user explicitly requests the exact operation, Claude must not:

- commit, amend, push, force-push, merge, rebase, publish, tag, or open/merge a pull request;
- transition a ticket, change assignee/status/fields, or claim an issue is complete;
- deploy, release, publish images, alter infrastructure, rotate secrets, or modify production configuration;
- run migrations against the development or any shared database, reset/wipe/re-seed data (`php artisan migrate*`, `db:wipe`, `db:seed`, `./start.sh fresh`, `./start.sh reset`), or perform production data repair;
- change the application's dependencies (`composer require|update|remove`);
- accept product, security, privacy, migration, operational, or residual risk on the human's behalf.

Claude may prepare the diff, plan, tests, evidence, migration files, and handoff. The human owns approval, Git writes, risk acceptance, migration application, deployment, and production access.

This list is enforced, not merely stated: `.claude/settings.json` denies the named command forms (Bash **and** PowerShell), and `.claude/hooks/guard-dangerous-commands.sh` catches the forms a prefix rule structurally cannot see — `git -C <path> commit`, `docker exec laravel-api php artisan migrate`, `sudo composer update`, `cat .env`. Neither layer is a sandbox; see `.claude/README.md` for what each one does and does not guarantee. Treat a refusal from either as the rule working, never as an obstacle to route around: if an operation genuinely needs to happen, say so and let the human run it.

## Deep references

Load these on demand — they hold the long-form playbooks so this core stays lean:

| Task | Where |
|---|---|
| Design a material change, reconcile ticket vs code, classify risk, compare alternatives, and produce an approval-gated plan | `gate-design` skill + `.claude/templates/plan.md` |
| Implement an approved plan without unrelated scope or Git/deployment writes | `gate-implement` skill |
| Drive a ticket end-to-end: map → plan → implement → review → validate → present → report | `work-item` skill (`/work-item <key \| URL \| requirement>`) |
| Independently review the current diff across architecture, correctness, AppSec, tests, API, DB, and performance | `gate-review` skill + `.claude/agents/` |
| Run read-only evidence gates and return `PASS` / `FAIL` / `BLOCKED` | `gate-validate` skill |
| General architecture, coding, security, and testing rules | `.claude/standards/architecture.md`, `coding.md`, `security.md`, `testing.md` |
| Scaffold a CRUD resource (layered pipeline, migration, routes, Resource, tests) | `resource-pattern` skill |
| Auth, Passport tokens, verification, Spatie RBAC/Gates, rate limiting, security review | `auth-security` skill |
| Write feature/unit tests (harness, `./test.sh`, auth helpers, factories) | `feature-testing` skill |
| Money, rates, any decimal arithmetic | `money-precision` skill |
| Queued jobs (Redis/Horizon), console and scheduled commands | `background-work` skill |
| Third-party integrations behind an `app/Utils/<Domain>Util` boundary | `external-integration` skill |
| Horizon supervisors, metrics, dashboards | `configuring-horizon` skill |
| Passport OAuth2 grants, clients, scopes | `passport-development` skill |
| General Laravel best practices by topic | `laravel-best-practices` skill |
| OpenAPI generation, the docs routes, and the known `GenericRequest` gap | *API documentation (OpenAPI)* above + `config/scramble.php` |
| CI: the merge gate, config validation, and migration safety | `.github/workflows/test.yml` |
| CI: dependency advisories, Trivy, and the OWASP ZAP scan | `.github/workflows/security.yml`, `security-dast.yml`, `.zap/rules.tsv` |
| Deployment: what the app expects and how to wire a real target | `DEPLOYMENT.md` |
| Threat model, API contract, and database design worksheets | `.claude/templates/` |
| What the Claude tooling is, how the guardrails work, and how to validate it | `.claude/README.md` |

Settings:
- `.claude/settings.json` (committed) — the deny/ask/allow floor plus the guard and formatter hooks. Every developer inherits it.
- `.claude/settings.local.json` (per-developer, gitignored) — personal Bash/WebFetch allowlist and enabled MCP servers.
- `php artisan app:validate-claude-config` — validates this directory; runs in CI via `./test-pipeline.sh`.

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
- **Indent with four spaces, never hard tabs.** `app:format` expands leading tabs automatically (code generators and editors are the usual source).
- **`new` parentheses:** named classes `new Foo()`; argument-less anonymous classes keep none (`return new class extends Migration`, matching Laravel's migration convention).
- **Method and function names are camelCase — always, including test methods:** `public function testCreate()`, never `test_create` (use the `#[Test]` attribute). snake_case is only ever a DB column, an array key, or an enum value — never a PHP method/function identifier.
- **Type-hint every parameter and return type** — methods, closures, and arrow functions alike: `fn (User $user): string => ...` / `function (Builder $query): void { ... }`, never bare `fn ($x) => ...`. Applies in app code and tests.
- **Full PHPDoc:** a class-level `@property` block on models/DTOs; `@var` on `$table`/`$fillable`; a summary + `@param`/`@return`/`@throws` block on every method (including the `casts()` method, which carries `@return array<string, string>`).

`app:format` scans `app/`, `database/`, `routes/`, and `tests/`, and encodes the indentation/whitespace/brace/`!` rules mechanically; the naming, type-hint, `new()`, and PHPDoc rules are yours to apply — run `--check` after `make:*` generators, which emit Laravel defaults that violate several of these.

===

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
- laravel/sail (SAIL) - v1
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

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `yarn run build`, `yarn run dev`, or `composer run dev`. Ask them.

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

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `yarn run build` or ask the user to run `yarn run dev` or `composer run dev`.

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
