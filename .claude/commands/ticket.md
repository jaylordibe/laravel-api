---
description: Drive a ticket from context-gathering to a verified diff, stopping at the plan-approval and commit gates.
argument-hint: <TICKET-KEY | pasted ticket text>
---

You are the **conductor** for the ticket-to-diff pipeline. Follow these stages in order. Two gates are non-negotiable: **never implement before the plan is approved, and never commit or push** (the human owns all git writes — a project hook enforces this).

## Track pipeline state (do this first)

Before Stage 1, create a persistent todo checklist with all six stages as items:
`1. Understand` · `2. Plan [GATE 1]` · `3. Implement` · `4. Review` · `5. Verify` · `6. Present [GATE 2]`.
Mark a stage `in_progress` when you enter it and `completed` when you leave it; keep exactly one stage `in_progress` at a time.

This checklist is the pipeline's **durable memory**. The plan gate (Stage 2) usually takes several rounds of back-and-forth, and the conversation may be summarized in between — so the original instructions can drift out of view. The checklist survives that. Therefore:

- Keep Stage 2 `in_progress` through the entire plan discussion; do not mark it completed until the user **approves** the plan.
- **When approval arrives, re-read the checklist and resume at the next pending stage (Stage 3 — Implement), then continue through Review → Verify → Present.** Do not treat a post-approval message ("looks good", "go ahead") as a standalone query — if a checklist with pending stages exists, you are mid-pipeline.
- Never skip Stages 4–6 just because approval came after a long discussion.

## Input

Ticket: $ARGUMENTS

If `$ARGUMENTS` is an issue key and an issue-tracker MCP (e.g. Atlassian/Jira) is connected, fetch the issue (summary, description, acceptance criteria). Otherwise treat `$ARGUMENTS` as the ticket content. If neither gives you enough to act on, ask for the ticket details before proceeding.

## Stage 1 — Understand (parallel, read-only)

Launch the `context-mapper` agent on the ticket to produce the impact map (touched layers, contract surface, security surface, downstream consumers, tests implied). For a large or cross-cutting ticket, launch several `context-mapper` agents scoped to different subsystems in parallel. Read the map fully before designing anything.

## Stage 2 — Plan  [GATE 1]

Enter **Plan mode**. Produce an ADR per CLAUDE.md: Context → Approach (rationale + rejected alternatives) → file-by-file changes → tests → verification → what this deliberately does NOT do. Then **stop and present it via ExitPlanMode**. Do not edit any file until the user approves. A plan is not a green light.

## Stage 3 — Implement

Only after approval. Implement exactly the approved plan to the CLAUDE.md engineering bar. For a brand-new resource, **scaffold first** with `docker exec laravel-api php artisan app:generate-resource <ModelName>` (per the `laravel-new-resource` skill), then fill the layered pipeline: thin **Controller** (inject the Service, wrap with `ResponseUtil`, no result-branching) → **Form Request** (`BaseRequest`; never raw `$request->input()`) → **Data / FilterData** → **Service** (throw `BadRequestException` on any failure — controllers never branch on results) → **Repository** (the only layer that touches the query builder) → **Model** (`$table` via `DatabaseTableConstant`) → **API Resource**. Consolidate schema work into ONE new migration via `php artisan make:migration` — never edit an already-applied migration. Delete what you replace.

## Stage 4 — Review (built-in engines, three lenses)

Run each and fold the findings back into the diff:
- `/code-review` — correctness bugs.
- `/security-review` — attack surface (mandatory for anything touching auth or money).
- `/simplify` — reuse / simplification cleanup.

## Stage 5 — Verify

This stack has **no build or type-check step** — do not invent one. Instead:
- **Code style:** `docker exec laravel-api php artisan app:format --check` must pass (never Pint). A `PostToolUse` hook already auto-runs `app:format` on every `.php` edit, so this should be clean.
- **Affected tests:** run the touched feature test only — `./test.sh <TestMethodName> tests/Feature/<Resource>Test.php`. Run the full `./test.sh` (which does `migrate:fresh --seed --env=testing` then `php artisan test --parallel`) only when the module is complete or the user asks.
- Where there is a runtime surface, exercise the actual endpoint against the running `laravel-api` container (a real request), not just the tests.

## Stage 6 — Present  [GATE 2]

Present: the diff summary, the review and verify results (honestly — include any failures), and any downstream-consumer handoff note. Then **stop.** The user commits and pushes; do not run any git write command.
