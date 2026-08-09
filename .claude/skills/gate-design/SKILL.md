---
name: gate-design
description: Designs a material change for this Laravel, Eloquent, MySQL, Redis, and Horizon API by mapping repository reality, reconciling ticket claims, evaluating architecture alternatives, threat-modeling relevant surfaces, and producing an approval-gated plan. Use for features, bugs, refactors, migrations, integrations, authorization changes, background jobs, and unclear blast radius.
argument-hint: "<ticket key | requirement | bug report>"
disable-model-invocation: true
model: inherit
effort: high
---

# Design a change

Input:

```text
$ARGUMENTS
```

This skill is design-only. Do not edit application source, tests, migrations,
configuration, `composer.json`/`composer.lock`, or deployment artifacts.

**This gate writes nothing to the repository.** The design is a plan, presented
through Claude Code's plan flow and structured on `.claude/templates/plan.md`.
See *Where the design lives* in `CLAUDE.md`.

## No resume-by-path

A plan is not a file in the repository, so there is nothing to reopen by path. A
session that ends mid-design is re-designed in the next one — the mapping is
cheap to redo and the repository may have moved anyway.

This is a deliberate trade: resuming happens on roughly one run in ten, which
did not justify a document every other run had to keep in sync.

## Pipeline

Track these stages:

1. Establish requirement
2. Map repository reality
3. Reconcile ticket and code
4. Classify risk
5. Evaluate alternatives
6. Threat-model
7. Plan file and contract changes
8. Plan tests and verification
9. Write the plan
10. Stop at approval

## 1. Establish requirement

Extract:

- desired outcome and acceptance criteria;
- users, actors, roles, and permissions;
- explicit constraints and non-goals;
- factual claims;
- prescribed implementation method;
- ambiguous product behavior;
- externally visible contract expectations.

Separate the **WHAT** from the ticket's proposed **HOW**.

Do not silently decide unresolved product behavior.

## 2. Map repository reality

**`context-mapper` is always required.**

Launch `context-mapper` **first**, before any option is weighed. You cannot
judge a change you have not mapped. Never skip it. **State that you ran it** —
an unexplained skip is indistinguishable from an oversight to whoever reads the
plan.

For cross-cutting work, use additional project agents in parallel:

- `architect`
- `security`
- `api`
- `database`
- `performance`
- `tester`

Read the complete map before deciding.

The map must cover:

- routes, middleware, controllers, Form Requests, Data objects, services,
  repositories, models, Resources, jobs, commands, listeners, and
  `app/Utils` integration boundaries;
- `rules()` shapes, `MetaData` usage, Resource JSON, the `ResponseUtil` success
  envelope, the `BadRequestException` error envelope, and stable client
  contracts;
- Eloquent models, relations, `casts()`, `DatabaseTableConstant` entries,
  soft-delete classification, indexes, constraints, transactions, and migration
  implications;
- Passport authentication, Spatie roles and permissions, Gates, ownership
  predicates inside repository queries, and 404-versus-403 behavior;
- audit columns, the activity log, log redaction, and job correlation;
- queued-job versus scheduled-command choice, `$tries`, idempotency, duplicates,
  failure handling, and recovery;
- affected `tests/Feature` and `tests/Unit` classes and factories;
- frontend, mobile, or external consumer handoffs.

## 3. Reconcile ticket and code

State plainly:

- requested product outcome;
- authoritative current behavior;
- confirmed, stale, incorrect, ambiguous, or not-found ticket claims;
- constraints imposed by existing contracts;
- whether the prescribed method is sound;
- recommended technical divergence and trade-offs.

A faithful implementation of a wrong premise is not acceptable.

## 4. Classify risk

Classify the change against `CLAUDE.md` — *Risk classification*. Its High row is
the trigger list; take the higher tier whenever the change sits on a boundary.

**The tier decides how much design this change gets, and whether it produces a
plan document at all.**

| Tier | Design artifact |
|---|---|
| **Low** | **No plan document.** Say so, state the tier and the evidence, and hand back — the change goes straight to implementation, and `/gate-review` and `/gate-validate` still run. |
| **Medium** | Plan covering §§1–7 and §§9–13 of the template. Section 8 (threat model) and the alternatives comparison stay brief unless the change earns them. |
| **High** | Full plan, explicit threat model in §8, at least two credible alternatives compared, migration/rollback analysis. |
| **Critical** | Everything High requires, and the plan states plainly that automated review is not sufficient and names the human review still owed. |

This tiering exists because a ceremony applied uniformly is a ceremony that gets
skipped, and a skipped step reads exactly like a completed one. Refusing to write
a plan for a copy fix is what keeps the plan meaningful for a schema change.

Do not use a Low classification to avoid a plan the change actually needs. If you
are choosing between two tiers, you are in the higher one.

## 5. Evaluate alternatives

For Medium-or-higher work, evaluate at least two credible approaches unless the
repository leaves only one viable approach.

Compare:

- correctness and invariant enforcement;
- authorization and security;
- fit with the layered pipeline;
- compatibility with API consumers;
- Eloquent and MySQL correctness;
- migration and rollback safety;
- queue, retry, and partial-failure behavior;
- testability;
- operational complexity;
- maintainability.

Prefer the proper coherent end state within approved scope. Do not over-engineer.

## 6. Threat-model

Use `security` and `.claude/templates/threat-model.md`.

At minimum consider:

- authentication and route middleware coverage;
- function-level and object-level authorization;
- ownership scope inside the repository query;
- enumeration and 404/403 behavior;
- role, permission, and foreign-key escalation;
- mass assignment;
- client-controlled money, price, or entitlement fields;
- timing behavior where auth is involved;
- replay, duplicate delivery, and race conditions;
- rate limiting on public and dispatching routes;
- upload, URL, and parser risks;
- data leakage through logs, errors, and Resources;
- webhook authenticity;
- activity-log gaps.

## 7. Plan exact project changes

Produce a file-by-file plan: for each file, what changes and which contract it
has to satisfy.

**Name the contracts; do not restate them.** The authoritative text is
`CLAUDE.md` plus `.claude/standards/` and the domain skills, and `gate-implement`
carries the lens-to-source table. A plan that paraphrases a convention creates a
third copy that outlives the rule it was copied from; a plan that cites one stays
correct for free.

What the plan must decide, because the sources cannot decide it for you:

- which middleware, permission, or Gate each new or changed route declares, and
  why;
- where the ownership boundary sits for each repository query;
- which failure messages are new, and whether any existing one changes meaning;
- which response fields are sensitive, and what the Resource exposes;
- the exact model shape, including `casts()`, soft-delete classification, and
  `DatabaseTableConstant` entry;
- whether new background work is a queued job or a scheduled command;
- the single new migration, prepared but not run.

Two worksheets carry the per-field detail those bullets only name. Use
`.claude/templates/api-contract.md` when the change adds or alters a route,
event, Form Request, or Resource — it is where the *authoritative source* of
every request field and the *enumeration behaviour* of every failure path get
decided. Use `.claude/templates/database-design.md` when it adds or alters a
model — it carries the index plan, the soft-delete classification, and the
restate-every-attribute rule for a column-modifying migration.

**Both are thinking aids, not deliverables.** Fill one in to reach the decision,
fold the conclusion into the plan, and commit neither — a template that becomes
a maintained document is the model this workflow deliberately left behind.

## 8. Plan tests and verification

Map each requirement and each identified risk to a specific test. The catalogue
of scenarios is `.claude/standards/testing.md` — *Required scenarios*; the design
work is deciding **which of them this change makes reachable**, and naming the
test file that will cover each.

A risk with no test mapped to it is an accepted risk. Say so in §13 rather than
leaving the gap implicit.

Verification must include:

- `php artisan app:format --check`;
- the affected `./test.sh <Filter> <path>` run;
- actual runtime exercise of the endpoint when a meaningful surface exists.

**There is no build or type-check step in this stack — do not plan one.**

Do not plan to run migrations against the development database.

## 9. Write the plan

Structure it on `.claude/templates/plan.md`, at the depth the risk tier in §4
requires. Do not create a file in the repository.

Include repository evidence, ticket reconciliation, the recommendation, rejected
alternatives, threat model where the tier requires one, tests, verification,
migration, rollout, rollback, non-goals, consumer handoff, and blockers.

**Write it once.** Nothing after approval edits the plan: implementation
divergences, review findings and validation evidence go in the Stage 6 report and
the pull request. A plan that is kept in sync with the work is a second copy of
the work.

**If the session ends before the design is complete**, say so plainly and stop.
There is no partial artifact to hand over — see *No resume-by-path* above.

## 10. Approval gate

Present the plan through `ExitPlanMode` and stop, so the decision is a click
rather than typed prose.

**Do not approve it yourself and do not infer approval.** The decision is the
user's. Read back the recommendation, the trade-off accepted by rejecting the
alternatives, contract and data impact, residual risk, the rollback path, the
non-goals, and **every unresolved §13 row individually** — the contract in
`.claude/skills/gate-approve/SKILL.md`. Ambiguous praise is not an approval.

Approval is the plan-mode decision itself. There is no `Status:` line and no
file to write; `/gate-approve` exists for the standalone case where a human wants
the read-back performed as its own step.

## 11. Handoff

Follow `.claude/standards/gate-handoff.md`, starting with its §0 mode table.

Close with the recommendation and the risk tier.

**Standalone** — then offer:

- **Approve it now** — read the design back per
  `.claude/skills/gate-approve/SKILL.md` and take their explicit decision.
- **Keep refining** — stay in design; name what is still unresolved.
- **Stop here** — they decide later.

**Conductor** (`/work-item` Stage 2) — do not offer these. This is the pipeline's
first human stop and Stage 2 owns how it is presented: the read-back goes through
`ExitPlanMode`, and on approval the conductor continues into implementation in
the same turn.

If the design is incomplete, do not offer approval at all, in either mode. Say
what is unresolved and stop.
