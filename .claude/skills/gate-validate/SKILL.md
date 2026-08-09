---
name: gate-validate
description: Performs read-only evidence validation for a reviewed change in this Laravel, Eloquent, MySQL, Redis, and Horizon API using its actual formatter, Docker-backed test harness, database, security, runtime, and operational contracts; returns PASS, FAIL, or BLOCKED.
argument-hint: "[scope | risk focus]"
disable-model-invocation: true
disallowed-tools: Edit, Write, NotebookEdit
model: inherit
effort: high
---

# Validate the reviewed change

Input:

```text
$ARGUMENTS
```

Validation is read-only. Never modify source, tests, snapshots, `composer.lock`,
migrations, configuration, or documentation to manufacture a pass.

**`php artisan app:format` rewrites files, so validation runs `--check` only.**
A formatter run during validation is a source edit, and it converts a real
failure into a silent pass.

## 1. Establish scope

Read:

- `CLAUDE.md`;
- the approved plan;
- review report/outcome;
- current diff;
- `composer.json` scripts and `phpunit.xml`;
- `test.sh` and `test-pipeline.sh`;
- `.env.testing` and `docker-compose.yml`;
- the affected migrations;
- the relevant domain skill.

State:

- exact worktree/commit;
- changed files and contracts;
- risk;
- required checks;
- unavailable prerequisites.

## 2. Preflight safety

Verify:

- the containers are running (`docker ps`) — a stopped `${SERVICE_NAME}-api` or
  `${SERVICE_NAME}-db-test` is `BLOCKED`, never a skipped check;
- no validation step targets the development database;
- the test run uses `--env=testing` and the separate test database;
- no production credentials, endpoints, or data are used;
- the filtered test command is supported by `test.sh`.

## 3. Canonical static gates

Run:

```text
docker exec laravel-api php artisan app:format --check
```

`--check` exits non-zero and lists offending files. **Never run the fixing form
here** — it exits 0 after rewriting whatever it repaired, so it cannot fail and
proves nothing.

**There is no build or type-check step in this stack.** Do not invent one, and do
not report its absence as a gap.

Check the diff for:

- focused or skipped tests introduced by the change;
- debug output — `dd()`, `dump()`, `var_dump()`, `ray()`, stray `Log::debug`;
- unintended `composer.lock` changes;
- a service touching the query builder directly;
- a controller branching on a service result;
- a raw model or query result returned instead of a Resource;
- `env()` called outside `config/`;
- a hardcoded table name instead of `DatabaseTableConstant`;
- a non-public route missing `auth:api`;
- a widened `$fillable`;
- float arithmetic on a monetary value;
- an edit to an already-applied migration.

## 4. Test gates

Run the affected test:

```text
./test.sh <FilterName> tests/Feature/<Resource>Test.php
```

Run the full `./test.sh` only when the resource is complete or the user
explicitly requests it — it does `migrate:fresh --seed --env=testing` first.

Label every filtered or partial run as partial.

Ask `tester` whether the executed evidence covers the plan, the review fixes, and
the risk.

## 5. Database and migration gates

When schema or data changed, inspect and validate:

- exactly one new migration for the change, created rather than edited;
- SQL correctness of the generated schema;
- **every attribute restated** on a column-modifying migration;
- constraints, indexes, and foreign keys;
- index support for the queries the repository actually issues;
- soft-delete behavior;
- existing-data and backfill implications;
- deployment ordering and mixed-version safety;
- `down()` correctness or the roll-forward plan replacing it.

Do not run migrations against the development database. Migration execution
belongs to the test harness and CI.

Ask `database` to assess the evidence.

## 6. Security and contract gates

Validate relevant:

- route middleware and permission coverage on every changed route;
- ownership predicate inside the repository query;
- negative tests for wrong permission and another actor's record;
- 404 for an invisible record versus a forbidden action on a visible one;
- the uniform error envelope and status;
- Resource fields, and the absence of secrets and internal columns;
- rate limiting on public and dispatching routes;
- audit columns and activity-log entries;
- sensitive-value redaction in logs;
- dependency advisories via `composer audit --locked`;
- consumer contract behavior;
- the generated OpenAPI document, when routes, Form Requests, or Resources
  changed: run `php artisan scramble:export` and confirm the changed operations
  appear, that every operation marked `security: []` is genuinely meant to be
  public, and that request/response fields match the Form Request and Resource.
  A field missing there is a defect in `rules()` or the Resource, not in the
  documentation.

Ask `security` and `api` to assess whether the evidence covers the threat model
and the contract.

## 7. Runtime/operational gates

When a runnable surface exists, exercise the actual endpoint against the running
container — a real request, not only the test.

Verify:

- external call timeout behavior;
- retry, idempotency, and duplicate handling;
- queued-job dispatch, `$tries`, and `failed()` behavior on Horizon;
- scheduled-command registration in `routes/console.php`;
- errors expose no SQL, driver, or stack detail;
- logs are useful and redacted;
- rollout and rollback prerequisites.

Ask `performance` for high-risk, asynchronous, integration, or load-sensitive
changes.

## 8. Verdict

Return exactly one:

- **PASS** — all required gates ran and passed; no release blocker remains.
- **FAIL** — at least one required gate ran and failed.
- **BLOCKED** — required evidence could not be obtained.

Never turn skipped, unavailable, partial, flaky, or unrelated failures into
PASS.

## 9. Evidence report

Use:

| Gate | Command/check | Exact scope | Result | Evidence/notes |
|---|---|---|---|---|

State:

- overall verdict;
- plan and acceptance-criteria coverage;
- failures and likely ownership;
- blockers and prerequisites;
- security evidence;
- migration and rollback readiness;
- consumer handoff status;
- residual risk;
- release recommendation.

## 10. Where the evidence goes

**Not into a document in the repository.** There is no validation-record section
to fill in. It could not be written until after validation had run, which made
it a guaranteed source of post-approval churn.

The evidence table from §9 belongs in the Stage 6 presentation and the pull
request — read once, by the person deciding whether to merge, which is the only
moment it changes anyone's behaviour.

Report the verdict exactly as produced. `FAIL` and `BLOCKED` are results, not
omissions to tidy away, and a run that only ever reports `PASS` records nothing.

Human approval, Git writes, migration application, deployment, and final risk
acceptance remain outside validation.

## 11. Handoff

Follow `.claude/standards/gate-handoff.md`, starting with its §0 mode table.

There is no next gate. Close with the verdict, the evidence table, and the
residual risk. In conductor mode, continue into `/work-item` Stage 6 — the
presentation the human actually acts on — rather than closing here.

On `PASS`, state plainly that the work exists only in the working tree and that
the commit, PR, migration application, and deployment are the user's — never
offer to perform them.

On `FAIL` or `BLOCKED`, name what failed or could not run, and point back to
`/gate-review` or the missing prerequisite.
