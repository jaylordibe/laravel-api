# Testing standard — project edition

## Repository topology

- Tests run **inside the `${SERVICE_NAME}-api` container** against a **real
  MySQL test database** (`${SERVICE_NAME}-db-test`) — not sqlite, not in-memory.
- `phpunit.xml` sets `APP_ENV=testing`.
- `./test.sh` is the wrapper: a bare run does
  `migrate:fresh --seed --env=testing`, recreates Passport clients, clears
  caches, then `php artisan test --parallel`.
- `./test.sh <FilterName> <path/to/File.php>` runs one class or method.
- `./test-pipeline.sh` is the CI flavour and additionally enforces
  `php artisan app:format --check`.
- `tests/Unit` and `tests/Feature` split by **subject, not isolation** — both hit
  the database through factories.
- Tests run in parallel, so a spec must not depend on another spec's rows,
  on a fixed auto-increment id, or on any globally exclusive state.
- The development database is never used or reset by tests.

## Layers

Use:

- unit specs for pure rules, Utils, casts, and enum behaviour;
- feature specs for the full route → controller → request → service →
  repository → resource path, including validation, authorization, and the
  response envelope;
- contract assertions for Resource JSON, Request `rules()`, and error shape
  compatibility;
- deterministic mechanisms for races and duplicates — never a sleep.

## Required scenarios

As relevant:

- success;
- validation failure and its message;
- the uniform error envelope and HTTP status;
- unauthenticated, wrong role/permission, and another actor's record;
- 404 for an invisible record versus a forbidden action on a visible one;
- secret and internal-field exclusion from the Resource;
- audit columns (`created_by`/`updated_by`/`deleted_by`) and activity log;
- soft deletion visibility;
- concurrent updates;
- duplicate, replay, and idempotency behaviour;
- queued-job retry exhaustion and `failed()` handling;
- external provider timeout and failure, with the provider faked;
- pagination, sorting, and ordering;
- date, time, and timezone boundaries;
- money and rounding through `BigDecimal`;
- migration compatibility against existing data.

## Quality

Use factories and their custom states rather than hand-built models.

Do not over-mock the framework, the database, or authorization when those
contracts are what the test exists to prove.

Control time, randomness, network, queue, and external providers.

Do not use arbitrary sleeps, weak truthiness assertions, stale duplicate tests
beside updated ones, focused or skipped tests, or a coverage percentage
presented as proof.

Every test method is camelCase and carries `#[Test]` where the name does not
begin with `test`.

## Evidence

Every run reports the exact command, the filter or scope, the result, the
environment, and the relevant output.

`php artisan app:format --check` plus the affected `./test.sh <Filter> <path>`
run are the normal minimum. A full `./test.sh` follows the cadence in
`CLAUDE.md`. **This stack has no build or type-check step — do not invent one.**

Skipped, partial, blocked, or flaky is not PASS.
