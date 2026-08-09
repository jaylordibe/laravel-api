# Gate handoff contract

Every engineering gate ends the same way. A developer must never finish a gate
wondering what happens next, and must never have to reconstruct the path from
memory or from `CLAUDE.md`.

This file is the single source of that behaviour. Each gate references it rather
than restating it, so the sequence can only be changed in one place.

## 0. Establish the mode first

A gate ends differently depending on **how it was entered**, and getting this
wrong is the difference between a pipeline and an interrogation. Decide before
writing anything:

| Mode | You are in it when | How the gate ends |
|---|---|---|
| **Standalone** | The human typed `/gate-design`, `/gate-approve`, `/gate-implement`, `/gate-review`, or `/gate-validate` directly | §1, §2, §3 — close, name the next command, ask whether to continue |
| **Conductor** | You are executing a `/work-item` stage, reading this gate's `SKILL.md` as that stage's playbook | §1 and §4 only. Emit the stage marker in §5 and **continue immediately** |

**In conductor mode you must not ask whether to continue.** `/work-item` already
carries the human's authorisation for the whole pipeline, and its two real stops
— plan approval and the Stage 6 present/push boundary — are gates in their own
right, enforced elsewhere. Re-asking at every stage boundary does not add a
safety property; it converts one authorisation into four confirmations of a
decision already made.

The rigor is identical in both modes. §1 still runs, every subagent panel still
fans out, every check still executes, and every stop condition in §4 still stops.
The only thing conductor mode removes is the prompt.

## 1. Close the gate

State, in this order and nothing more:

1. **Outcome** — one line. What this gate concluded.
2. **What changed on disk** — the files touched, or explicitly "nothing changed"
   for a read-only gate. The design itself is a plan, not a file.
3. **Evidence** — only checks that actually ran, with their real result. A gate
   that could not run something says `BLOCKED`, never silence.
4. **Anything that blocks the next gate** — unresolved blockers, failing checks,
   Critical/High findings, missing prerequisites.

## 2. Name the next step concretely — standalone only

Print the next command **with its argument already filled in**, so it can be run
without the developer looking anything up:

```text
/gate-review
```

Never write `/gate-review <placeholder>` in a handoff. The placeholder is for
documentation; the handoff is for doing.

The sequence:

| Just finished | Next | Skip only when |
|---|---|---|
| `/gate-design` | `/gate-approve` | the design is incomplete — say so and stop; or the change is **Low risk and has no plan document**, in which case the next step is implementation and there is nothing to approve |
| `/gate-approve` | `/gate-implement` | decision was Rejected |
| `/gate-implement` | `/gate-review` | never |
| `/gate-review` | `/gate-validate` | a Critical/High finding is unresolved |
| `/gate-validate` | human commit / PR | verdict is `FAIL` or `BLOCKED` |

## 3. Offer to continue — standalone only

Ask with `AskUserQuestion` — a real choice, not a rhetorical one. Keep it to one
question, and make the options honest about what actually happens:

- **Yes, continue now** — proceed in this session, following the next gate's
  `SKILL.md` as its authoritative contract. This is the same mechanism
  `/work-item` uses for stages 2–5; it is a continuation of work the user just
  authorised, not Claude deciding to run a gate on its own.
- **Stop here** — the user runs the next gate themselves, in a fresh session.
- A third option only when the state warrants one: *Revise the design*,
  *Reject the design*, *Fix findings first*.

If the user declines, stop cleanly. Do not re-ask, and do not drift into the next
gate's work anyway.

### When to recommend a fresh session instead

Say so plainly, and make **Stop here** the recommended option, when:

- the change is **High or Critical** risk, and the next gate is `/gate-review` —
  a review carries more weight from a context that did not just write the code;
- the conversation is long enough that compaction has already occurred, so the
  next gate would start from a summary rather than the real diff;
- the previous gate ended `FAIL` or `BLOCKED`.

Independence is a property of the review, not a formality. Continuing is a
convenience for ordinary work, not the default for risky work.

**None of this applies in conductor mode**, where independence is supplied by
the review's subagents instead of by a human checkpoint — see §5.

## 4. What continuing never authorises

Continuing carries the user's authorisation for the **next gate only**. It never
authorises:

- skipping a gate in the sequence;
- committing, pushing, opening a PR, tagging, or deploying;
- running a migration against the development database, or resetting/reseeding
  it;
- approving a design the user has not explicitly decided on;
- reporting a check as passed when it did not run.

In standalone mode each gate re-asks at its own end: answering "yes" once does
not run the rest of the pipeline. If the user wants the whole sequence in one
pass, that is `/work-item <requirement>`, which needs no issue key.

This list binds **both** modes. Conductor mode removes the prompt between
stages; it removes nothing from this section. A `/work-item` run still may not
commit, push, migrate a real database, or approve its own design.

## 5. Closing a gate in conductor mode

Close with §1, then emit a single stage marker and **keep working**:

```text
Stage 3 — Implement: complete. 7 files, 2 feature tests. app:format --check and affected tests pass.
Stage 4 — Review: in progress.
```

No next-command line, no `AskUserQuestion`, no fresh-session recommendation.

### Preserving review independence without a human stop

The fresh-session rule in §3 protects a real property: whoever reviews the diff
should not be the context that just wrote it. Conductor mode keeps that property
by structure rather than by pausing. On **High or Critical** work, Stage 4 must:

- fan out to the risk tier's full panel of **independent read-only subagents**,
  each of which starts from a clean context and reads the diff from disk — never
  from the conductor's recollection of what it intended to write;
- run the **adversarial refutation pass** on every Critical and High finding, per
  `.claude/skills/gate-review/SKILL.md`.

A conductor that reviews High/Critical work by itself has skipped the gate, not
accelerated it.

### The only stops in a conductor run

Two are planned, and both are boundaries the pipeline cannot cross on its own:

1. **Plan approval.** Present via Plan mode (`ExitPlanMode`) so the decision is a
   button, not typed prose. `gate-approve` sets `disable-model-invocation: true`
   precisely so a design cannot approve itself; an affirmative click is the human
   decision that control exists to require. Ambiguous praise still is not one.
2. **Stage 6 — present.** Git writes are denied. The human reviews the diff and
   pushes.

Everything else runs through. Stop mid-pipeline **only** for:

- a material divergence from the approved plan — needs renewed approval;
- an unresolved product decision that no amount of reading the repository can
  settle;
- two complete remediation cycles exhausted with findings still unresolved;
- a Stage 5 verdict of `FAIL` or `BLOCKED` that Stage 3/4 cannot fix within the
  approved plan;
- a missing prerequisite — a stopped container, an absent credential, or a check
  that cannot run.

When one of these fires, say which, say what it blocks, and stop. Do not ask the
human to confirm a stage that had no such problem.
