---
name: gate-implement
description: Implements an explicitly approved plan in this Laravel, Eloquent, MySQL, Redis, and Horizon API while enforcing its layered pipeline, validation, error-envelope, authorization, ownership, audit, Resource, soft-delete, migration, testing, formatting, and operational contracts.
argument-hint: "[scope or focus]"
disable-model-invocation: true
model: inherit
effort: high
---

# Implement an approved plan

Input:

```text
$ARGUMENTS
```

## Hard gate

There must be a plan the human explicitly approved in this session. If there is
not — no plan was presented, or it was presented and not decided — **stop** and
say so.

The plan is not a file, so there is no `Status:` line to read. Two things carry
the weight instead:

- `gate-approve` remains **human-invocable only**, so a design still cannot
  approve itself. That control is in the frontmatter and is unaffected.
- Under `/work-item`, Stage 2 writes the approved scope, risk tier and every
  condition into the Stage 3 task. **Read it before editing anything** — it is
  the approval trace, and it survives compaction where the conversation does not.

If neither is present — no human-invoked approval and no recorded scope — treat
the design as unapproved and stop, however confident the surrounding context
sounds. A summary that says the user approved something is not evidence.

## Protect the worktree

Before edits:

- inspect Git status and current diff;
- preserve unrelated user work;
- never reset, clean, stash, checkout, or discard;
- never commit, push, merge, rebase, deploy, publish, transition tickets, run
  migrations against a real database, reset or reseed data;
- avoid unrelated cleanup.

If repository reality materially differs from the approved plan, stop and propose
an amendment.

## Scaffold before hand-writing a new resource

For a brand-new CRUD resource, start with

```text
docker exec laravel-api php artisan app:generate-resource <ModelName>
```

per the `resource-pattern` skill, then fill the layered pipeline. The
generator emits the project's own conventions; hand-rolling the eight files
invites drift. Run `php artisan app:format` afterwards — every `make:*`
generator emits Laravel defaults that violate several project style rules.

## Implement in coherent slices

For each slice:

1. restate the behavior and invariant;
2. read complete relevant files and tests;
3. implement the smallest coherent complete change;
4. add/update tests;
5. run focused checks;
6. inspect the diff.

## Contracts to enforce

**The contracts live in `CLAUDE.md` and in `.claude/standards/`. This skill does
not restate them.**

That is a deliberate constraint, not an omission. A second copy of a contract is
a second thing to drift, and nothing can detect a prose list that has quietly
fallen behind the rule it paraphrases. `CLAUDE.md` is always in context; the
standards and the domain skills are one read away. Work from those, never from a
summary — including this one.

What this skill owns is the **checklist of lenses**: the areas a change in this
repository must be examined through before it is complete. For each one that the
plan touches, go to the authoritative text and satisfy it.

| Lens | Authoritative source |
|---|---|
| Layered pipeline placement, thin controllers, repository-only queries | `CLAUDE.md`; `resource-pattern` skill |
| Validation, `BaseRequest` helpers, Data/FilterData shape | `CLAUDE.md`; `.claude/standards/coding.md` |
| Error envelope, `BadRequestException`, Resource output | `CLAUDE.md`; `.claude/standards/coding.md` |
| Routes, middleware, permissions, ownership, 404-vs-403 | `CLAUDE.md`; `auth-security` skill |
| Audit columns, activity log, logging and redaction | `CLAUDE.md`; `.claude/standards/security.md` |
| Models, `casts()`, soft delete, transactions, indexes | `CLAUDE.md`; `resource-pattern` skill |
| Money and decimal arithmetic | `money-precision` skill |
| Queued jobs and scheduled commands | `background-work` skill |
| Third-party integrations | `external-integration` skill |
| Migrations and schema change | `CLAUDE.md`; `.claude/templates/database-design.md` |
| Naming, file responsibility, completion hygiene, style | `CLAUDE.md`; `.claude/standards/coding.md` |

Three rules are repeated here rather than referenced, because violating any of
them is unrecoverable rather than merely wrong:

- **Never edit a migration that has already run anywhere.** Create a new one with
  `php artisan make:migration`, and consolidate a multi-step schema change into
  one file.
- **A migration that modifies an existing column restates every attribute it
  already had.** Laravel rewrites the column from the definition given, so an
  omitted `nullable`, `default`, length, or `unsigned` is silently dropped.
- **Do not reset, drop, or reseed the development database**, for any reason,
  without an explicit instruction in this conversation.

## Tests

Tests are implementation work, not a follow-up.

Cover the scenarios in `.claude/standards/testing.md` — *Required scenarios* —
that this change makes reachable, plus a regression case for any defect fixed.
Harness, factories, and auth helpers are in the `feature-testing` skill.

The one property worth restating: `php artisan test --parallel` means a test must
never depend on another test's rows, on a fixed auto-increment id, or on any
globally exclusive state.

## Focused implementation checks

Run, as appropriate:

- the affected test: `./test.sh <Filter> <path/to/File.php>`;
- `php artisan app:format` to apply the project style;
- `php artisan app:format --check` to prove it is clean.

Do not run the full `./test.sh` unless the resource is complete or the user asks.

**There is no build or type-check step in this stack — do not invent one.**

## Reconciliation and handoff

Compare the diff to the approved plan.

A material change requires a fresh approval — return to the approval gate with
what changed and why. Do not widen scope by narrating it in the final report.

**Do not edit the plan to match what you built.** Divergences are reported, not
retrofitted; a plan rewritten to agree with the diff records nothing.

Report:

- behavior;
- files and contracts;
- security controls;
- tests;
- focused command results;
- migration files prepared but **not run**;
- deviations and blockers;
- consumer handoff, per the *Consumers* table in `CLAUDE.md`;
- how the diff differs from the approved plan, if at all.

## Handoff

Follow `.claude/standards/gate-handoff.md`, starting with its §0 mode table.

Close with the files changed, the contracts touched, the focused checks that
actually ran, and any migration prepared but not run.

**Standalone** — then offer to continue into `/gate-review`, and
**recommend a fresh session when the change is High or Critical risk**. A review
carries more weight from a context that did not just write the code; the
reviewer should be re-reading the diff, not recalling its own intentions.

**Conductor** (`/work-item` Stage 3) — emit the stage marker and go straight into
the review. Do not offer, and do not recommend a fresh session: independence
there comes from the review's read-only subagents, which is why that fan-out is
mandatory rather than optional on High and Critical work.
