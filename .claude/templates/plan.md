# Plan: [Decision title]

- **Risk:** Low | Medium | High | Critical
- **Source:** [Ticket/request/incident]

> Structure, not a form. Use the sections the change earns and say
> "not applicable" for the rest in one line — an empty heading reads exactly
> like a completed one. Depth scales with the risk tier in `CLAUDE.md`.
>
> This is a **plan**, presented through Claude Code's plan flow. It is not
> written into the repository, and nothing downstream edits it: implementation
> divergences, review findings, and validation evidence belong in the Stage 6
> report and the pull request, not here. See *Where the design lives* in
> `CLAUDE.md`.
>
> **Low risk gets no plan document at all** — state the tier and the evidence,
> present the approach, and go.

## 1. Recommendation

The outcome, the approach you judge best, and the primary trade-off accepted by
choosing it. Lead with what you recommend, not with what was asked.

## 2. Ticket versus repository reality

What was requested (**WHAT**) versus any method it prescribed (**HOW**) — these
are different things, and the second is input to weigh, not a mandate.

| Claim | Grade | Repository reality | Evidence |
|---|---|---|---|
| | Confirmed/Stale/Incorrect/Ambiguous/Not found | | `path:line` |

Then the current authoritative flow — route through middleware, Form Request,
Data object, service, repository, model, and Resource.

## 3. Constraints and invariants

Layer boundaries · error envelope · authorization and actor scope · data and
soft-delete behaviour · consumer compatibility · operational · migration.

## 4. Options

At least two credible approaches for Medium and above, unless the repository
leaves only one. For each: approach, benefits, risks, compatibility, security,
migration/operations.

State plainly why the rejected ones lost. An option list where one choice is
obviously correct is decoration.

## 5. Decision

The recommendation and its rationale, and what is given up by rejecting the
alternatives.

## 6. API and contract impact

Route and method · middleware (`auth:api` / public) · permission or Gate ·
Form Request `rules()` · Data and FilterData shape · API Resource JSON ·
`ResponseUtil` success envelope · `BadRequestException` messages and status ·
pagination and ordering · enum values exposed through `ConstantController` ·
queued-job or webhook payloads · mixed-version behaviour.

**Name every affected consumer from the *Consumers* table in `CLAUDE.md`, or
record explicitly that none are affected.** A contract change is not done when
this API parses.

## 7. Data design

Models and relations · soft versus hard delete · ownership and actor scope ·
constraints and indexes · uniqueness · transactions and concurrency ·
`DatabaseTableConstant` entry · `casts()` additions · the single new migration
file · existing data and backfill · deployment ordering · rollback.

State explicitly which attributes a column-modifying migration must restate.

## 8. Security and privacy

Assets · actors · trust boundaries · authentication · object and function
authorization · enumeration and 404-versus-403 behaviour · client tampering ·
mass assignment · replay and races · rate limiting · secrets and log redaction ·
activity log · residual risk.

Required for High and Critical. For **Critical**, also state plainly that
automated review is not sufficient and name the human review still owed.

## 9. Test plan

| Requirement/risk | Test file/layer | Scenario | Expected evidence |
|---|---|---|---|
| | | | |

Cover negative authorization (unauthenticated, wrong permission, another actor's
record), the uniform error envelope, Resource field exposure, soft deletion,
concurrency, and retry behaviour where reachable. A guardrail ships with proof it
catches the omission, not only the happy path.

## 10. Verification

Affected `./test.sh <Filter> <path>` run · `php artisan app:format --check` ·
runtime exercise of the actual endpoint · security checks · migration evidence
from the test database only.

**No build or type-check step exists in this stack — do not invent one.**

## 11. Rollout and recovery

Deployment order · consumer dependencies · migration and backfill · success
signals · abort thresholds · rollback or roll-forward · repair.

## 12. Deliberate non-goals

What this explicitly does not do, and why. Scope disappointment surfaces here
more often than technical objection, which is why it is read back at approval.

## 13. Open decisions and blockers

| Type | Question/blocker | Why it matters | Owner |
|---|---|---|---|
| | | | |

Every unresolved row is read back individually at the approval gate. The human
must resolve each one or accept it as a stated condition; conditions are
recorded verbatim in the approval, never paraphrased.
