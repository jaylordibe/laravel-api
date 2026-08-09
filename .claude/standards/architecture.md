# Architecture standard — project edition

`CLAUDE.md` is authoritative. This file provides deeper
interpretive guidance and must not weaken repository-specific rules.

## Priorities

1. correctness and data integrity;
2. security and privacy;
3. compatibility and operability;
4. maintainability and testability;
5. performance and cost;
6. delivery speed.

## Boundaries

The layered pipeline is the boundary contract. Each arrow is one direction only:

```text
Route → Controller → Request → Data/FilterData → Service → Repository → Model
                                                                     → Resource
```

- Controllers hold transport and authorization metadata, never business rules,
  and never branch on a service result.
- Requests own validation and build typed Data objects; `BaseRequest` helpers
  replace raw `$request->input()`.
- Data objects are transport between layers, not persistence models.
- Services own every business rule and throw `BadRequestException` on failure.
- **Repositories are the only layer that touches the query builder.** A service
  reaching for Eloquent directly is an architecture violation, not a shortcut.
- Models carry casts, relations, and audit stamping; `$fillable` stays empty.
- Resources shape the JSON a client sees; a raw model is never returned.
- `app/Utils/*Util.php` holds pure reusable transforms; `app/Constants` holds
  static tables and registries. Neither belongs inline above a class.
- Table names have one source of truth: `DatabaseTableConstant`.
- Non-table defaults have one source of truth: `config/custom.php`, read through
  `config()` — never `env()` outside a config file.

## Coherent scope

Prefer the smallest coherent complete change.

Do not:

- leave half-migrated in-scope call sites;
- create parallel legacy/new paths;
- add speculative abstractions;
- hide unrelated refactors inside a feature;
- introduce a second response or error shape beside the established one.

## Contracts

Treat as externally observable:

- Form Request `rules()` — required/optional/nullable, types, limits;
- Data and FilterData shapes;
- API Resource JSON, including field presence and nesting;
- the success envelope from `ResponseUtil` and the error envelope from
  `BadRequestException` / `ResponseUtil::error()`;
- HTTP status behaviour;
- enum values exposed through `ConstantController`;
- pagination, filtering, sorting, and ordering semantics;
- queued-job and webhook payloads;
- idempotency and retry behaviour.

Every change identifies consumers, deployment order, mixed-version behaviour,
deprecation, and rollback/roll-forward.

A contract change is not done when PHP parses. It is done when every consumer in
the *Consumers* table in `CLAUDE.md` has been updated or explicitly recorded as
unaffected, with the deployment order stated.

## State and distributed behaviour

Explicitly model:

- lifecycle transitions;
- invalid transitions;
- transaction boundaries (`DB::transaction`) and what they actually cover;
- concurrency and lost-update prevention;
- queue delivery semantics under Horizon;
- idempotency;
- duplicate and poison-message behaviour, `$tries` and `failed()`;
- retryable versus terminal errors;
- timeouts and cancellation;
- correlation and reconciliation.

Never claim exactly-once or gapless business sequencing without a proven bounded
mechanism.

## Data evolution

Follow repository migration policy: one new migration per coherent schema
change, created with `php artisan make:migration`, and never an edit to a
migration that has already run anywhere.

When a migration modifies an existing column, restate every attribute the column
already had — Laravel rewrites the column from the definition given, so an
omitted `nullable`, `default`, length, or `unsigned` is silently dropped.

For deployed systems follow expand/migrate/contract. Address existing data,
indexes, locks, backfills, mixed versions, abort thresholds, and recovery.

## Design records

A material decision records context, ticket-vs-code reconciliation, alternatives,
security, file plan, tests, verification, migration, rollout, non-goals, and
human approval.
