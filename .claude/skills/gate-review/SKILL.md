---
name: gate-review
description: Independently reviews the current Laravel and Eloquent change for approved-plan conformance, correctness, application security, authorization and ownership isolation, API and error contracts, database and migration safety, queue reliability, tests, and performance; fixes verified findings and re-reviews.
argument-hint: "[base ref | review focus]"
disable-model-invocation: true
model: inherit
effort: high
---

# Review the current change

Input:

```text
$ARGUMENTS
```

The main agent is the conductor and remediation owner. Specialist agents are
read-only and independent.

## 1. Establish exact target and risk

State the exact target:

- the approved plan;
- staged/unstaged worktree diff;
- explicit base reference;
- changed files and contracts.

Exclude unrelated user changes.

Then state the **risk tier** from `CLAUDE.md` — *Risk classification*. It decides
how much review this change gets. Name the tier and the evidence for it before
launching anything; an unstated tier defaults to the panel it happens to get,
which is how every change ends up costing the same regardless of what it is.

Take the higher tier whenever the change is on a boundary.

## 2. Select the review lenses by risk

Reviewing a copy fix with seven agents and reviewing an authorization change with
one are the same mistake in opposite directions. Fan out to what the tier and the
touched surface justify:

| Tier | Lenses |
|---|---|
| **Low** | No subagents. Review it yourself and run `/code-review` if available. |
| **Medium** | `reviewer`, plus the one domain lens the change actually touches. |
| **High** | `reviewer`, `security`, `tester`, plus every domain lens touched. `/security-review` as well, when available. |
| **Critical** | The full panel including `architect`, plus everything High requires. Automated review is never sufficient on its own here — say so explicitly in the report. |

Domain lenses: `api` (routes, Form Requests, Resources, envelopes, consumer
contracts) · `database` (Eloquent, constraints, queries, migrations) ·
`performance` (queues, N+1, retries, resource bounds) · `security` · `tester` ·
`architect`.

Launch the selected agents **in parallel**, and give each: the approved plan, the
exact diff, the changed-file scope, and the standards document or domain skill
for its lens. Do not paste contract text into the prompt — name the file and let
the agent read it.

Bundled skills supplement the panel; they never replace it. Run `/simplify` only
after correctness and security findings are resolved, and verify each of its
proposals against the approved plan before accepting it.

## 3. What to review against

**The contracts live in `CLAUDE.md` and in `.claude/standards/`. This skill does
not restate them**, for the same reason `gate-implement` does not: a paraphrased
checklist drifts from the rule it paraphrases, silently, and no tooling can
detect it.

Each lens reviews against its authoritative source:

| Lens | Reviews against |
|---|---|
| `reviewer` | `CLAUDE.md`; `.claude/standards/coding.md` — correctness, state transitions, naming, layer placement, dead code, half-migrated call sites |
| `architect` | `.claude/standards/architecture.md`; the approved plan — pipeline boundaries, repository-only queries, deliberate non-goals, no parallel implementations |
| `security` | `.claude/standards/security.md`; `auth-security` skill |
| `api` | `CLAUDE.md`; `.claude/standards/architecture.md` — *Contracts* |
| `database` | `CLAUDE.md`; `resource-pattern` skill — soft delete, indexes, transactions, one new migration, restate-every-attribute, no development-database run |
| `performance` | `background-work` skill; `.claude/standards/architecture.md` — *State and distributed behaviour* |
| `tester` | `.claude/standards/testing.md`; `feature-testing` skill |

Three checks belong to the conductor rather than to any lens, because they are
about the change as a whole:

- the diff does what the **approved plan** says, and nothing it excluded;
- no unrelated scope, speculative abstraction, or opportunistic refactor;
- every in-scope call site of a changed pattern was migrated, not just the
  first one.

## 4. Verify every finding

For each candidate:

1. inspect surrounding source;
2. cite exact `path:line`;
3. state trigger and expected/actual behavior;
4. state impact;
5. assign Critical/High/Medium/Low/Note;
6. prescribe the smallest correct fix;
7. specify regression coverage;
8. state confidence.

Reject speculative, duplicate, irrelevant, or style-only findings — anything
`php artisan app:format` fixes mechanically is not a review finding.

### Adversarial verification — Critical and High only

You commissioned the review, so you are the worst available judge of whether its
findings are real: the same context that produced a finding will tend to confirm
it. For each **Critical or High** finding on a **High or Critical risk** change,
launch one independent agent — the specialist whose lens owns the finding — with
the single instruction to **refute** it:

> Here is a claimed defect at `path:line`: <claim>. Read the surrounding source
> and try to prove it is NOT a defect: that the trigger is unreachable, the
> invariant is enforced elsewhere, the behavior is intended, or the cited line
> does not say what the claim says. Default to `refuted: true` when the evidence
> is ambiguous. Return `refuted` plus the evidence that settles it.

A refuted finding is dropped and recorded in the rejected-findings summary with
the refutation. A surviving finding proceeds to remediation with its refutation
attempt on record — that record is what makes the severity credible later.

Do not run this for Medium and below, and do not run it on Low or Medium risk
changes; the cycle costs more than the precision it buys there.

## 5. Remediate

- Fix verified Critical/High findings within accepted scope.
- Fix Medium findings unless they require product/architecture approval.
- Fix Low findings only when safe and local.
- Add regression tests.
- Return to the approval gate for material divergence; never edit the plan to
  match the diff.
- After fixes, run the affected `./test.sh <Filter> <path>` and
  `php artisan app:format --check`.

Perform at most two full remediation/re-review cycles.

## 6. Report

Report:

- exact target and risk tier;
- lenses selected, and why that set;
- findings by severity;
- fixes and tests;
- rejected findings summary;
- command evidence;
- unresolved blockers/risks;
- consumer handoff;
- readiness for `/gate-validate`.

Do not commit, push, deploy, run migrations, transition tickets, or claim
production readiness.

## 7. Handoff

Follow `.claude/standards/gate-handoff.md`, starting with its §0 mode table.

Close with findings by severity, what was fixed, what was rejected and why, and
the commands that actually ran.

**Standalone** — then offer to continue into `/gate-validate`.

**Conductor** (`/work-item` Stage 4) — emit the stage marker and go straight into
validation.

**An unresolved Critical or High finding stops the work in both modes.** Name
what remains and stop. This is not the inter-stage prompt that conductor mode
removes — it is the gate itself doing its only job, and a pipeline that runs
past it has not saved anyone time.
