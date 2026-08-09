# Claude Engineering Framework

Team-shared Claude Code assets for this Laravel + Passport starter.

**The tooling** is `.claude/`, `CLAUDE.md`, and
`app/Console/Commands/ValidateClaudeConfigCommand.php`. It ships inside the
starter: clone the repository and it is already here, ready to use or ignore.

This directory contains:

- an orchestrated **work-item-to-validated-diff** workflow;
- independently invocable engineering gates;
- project-aware specialist agents;
- plan, threat-model, API, and database templates;
- deeper architecture, coding, security, and testing standards;
- harness-enforced safety hooks.

This is developer tooling, not part of the API. The API runs and tests without
it. Developers who do not use Claude Code can ignore this directory.

`php artisan app:validate-claude-config` keeps it honest: it fails when a skill
cites a class, helper, constant, or path that does not exist in `app/`, so the
gates cannot end up reviewing your code against contracts it does not have.

## Recommended workflows

There are two supported ways to work.

### Continuous pipeline

Use this to drive work from repository research through a reviewed and validated
working-tree diff **in one session**, stopping only at the two human gates:

```text
/work-item IA-123
/work-item <pasted requirement>
/work-item add rate limiting to the password reset endpoint
```

**An issue key is not required.** The argument may be an issue key, an issue URL
(the key is extracted from it), or the requirement written out in plain words —
all three are first-class. A URL that yields no key stops with a message rather
than being mistaken for the requirement text.

`/work-item` is the top-level conductor. It maintains pipeline state in the main
conversation, delegates read-only research/review agents, stops for design
approval, implements only the approved plan, reviews and validates the diff,
presents the result, and optionally posts one issue comment.

### Individual engineering gates

Use these for non-ticket work, focused operation, recovery in a new session, or
when you deliberately want to control each stage yourself:

```text
/gate-design <requirement>
/gate-approve
/gate-implement
/gate-review [base ref]
/gate-validate [scope]
```

The phase skills are human-invoked. `/work-item` does not recursively invoke them
through the Skill tool. Instead, it reads their `SKILL.md` files as the
authoritative stage contracts and performs those stages in the main
conversation. This preserves human invocation controls while preventing the
orchestrator and the standalone gates from drifting apart.

Each standalone gate ends by naming the next command with its argument filled in
and offering to continue in the same session — the contract is
`standards/gate-handoff.md`, referenced by all five rather than restated in
each. Continuing carries authorisation for the **next gate only**. Running the
gates individually and answering "yes" at each handoff reaches the same place as
`/work-item`; the difference is that you decide at every boundary, and can drop
into a fresh session whenever independence matters more than momentum.

## Work-item-to-validated-diff pipeline

| Stage | What runs | Human boundary |
|---|---|---|
| 1. Understand | `context-mapper`; additional architect, security, API, database, performance, or test agents when relevant | |
| 2. Design | The `gate-design` skill contract, ticket-vs-code reconciliation, alternatives, threat model, and the plan | **GATE 1:** no source edit until explicit approval |
| 3. Implement | The `gate-implement` skill contract and the approved plan | |
| 4. Review | Project-aware architecture, correctness, security, test, API, database, and performance agents; bundled `/security-review` or `/simplify` when useful and available | No unresolved Critical/High finding |
| 5. Validate | The `gate-validate` skill contract, `app:format --check`, the affected `./test.sh` run, database/security evidence, and a real request against the running container | `PASS`, `FAIL`, or `BLOCKED` only |
| 6. Present | Diff summary, review findings, evidence, risks, and consumer handoff | **GATE 2:** human owns commit, push, PR, migration application, and deployment |
| 7. Report | One short issue comment when a real issue and tracker connection exist | Issue status and fields remain untouched |

The conductor creates a seven-stage task checklist before Stage 1 and keeps
exactly one stage in progress. The checklist is the session's durable workflow
memory across long design discussion and context compaction.

The plan is not a repository artifact, so there is nothing to resume by path. A
session lost mid-design is re-designed in the next one; a session lost after the
diff exists resumes from that diff with `/gate-review`, then `/gate-validate`.

## Work-item interpretation

A work item is a claim to validate, not a specification to transcribe.

The pipeline separates:

- **WHAT:** the product outcome and acceptance criteria;
- **HOW:** the method the ticket happened to suggest.

Stage 1 verifies factual claims against the source. Stage 2 recommends the
approach supported by repository evidence and records rejected alternatives.
Stale or unsafe technical prescriptions are not implemented merely because they
appear in the work item.

Genuine product choices go back to the human.

## Components

### Skills

- `skills/work-item/SKILL.md` — full conductor.
- `skills/gate-design/SKILL.md` — repository mapping, risk, alternatives, threat
  model, and the plan.
- `skills/gate-approve/SKILL.md` — reads the plan back to the human, takes an
  explicit decision, and records it.
- `skills/gate-implement/SKILL.md` — approved-plan implementation.
- `skills/gate-review/SKILL.md` — independent project-specific review and
  remediation.
- `skills/gate-validate/SKILL.md` — read-only evidence gate.

All six workflow skills use `disable-model-invocation: true`; only the human
starts them. That flag removes the skill from Claude's context entirely, so
Claude cannot invoke a gate even when instructed to — it must **stop and ask the
user** to run the next one. `CLAUDE.md` states this obligation explicitly so the
gate is never simulated from memory.

### Why the gates carry a `gate-` prefix

Every user-invocable project skill is namespaced `gate-*`. This is a structural
rule, not a stylistic one.

Claude Code ships built-in commands and adds more over time, and a project skill
that takes the same name does not cleanly win — it simply appears *beside* the
built-in in the `/` menu, disambiguated only by which row you land on. `review`
collides with the bundled diff-review skill, and `design` collides with
`/design`. Selecting the wrong row runs something entirely unrelated.

Maintaining a list of reserved built-in names does not fix this — such a list is
stale the moment Anthropic ships a new command. A prefix removes the whole
collision class instead: Anthropic will not ship a `/gate-*` command. It also
groups the workflow in the menu, so typing `/gate` shows exactly these five and
nothing else.

`php artisan app:validate-claude-config` enforces the prefix, so a new gate
cannot be added without it.

The domain playbook skills below need no prefix: they set
`user-invocable: false` and never appear in the `/` menu at all, so they have no
collision surface there.

`/work-item` is deliberately exempt — it is the conductor, not a gate, so a
`gate-` name would misdescribe it. The exemption is also safer than it looks:
Claude Code's built-ins are single words (`init`, `review`, `design`, `run`,
`debug`), so a hyphenated compound has a much smaller collision surface than a
bare noun like `ticket` did. The validator still records it as an explicit,
reviewed exemption rather than letting it pass unnoticed.

### Agents

- `context-mapper` — blast-radius and ticket-vs-code map.
- `architect` — layer boundaries, plan conformance, compatibility, and rollout.
- `security` — Passport, Spatie permissions, ownership scope, abuse, audit, and
  data exposure.
- `reviewer` — correctness and maintainability.
- `tester` — risk-based unit/feature coverage and evidence quality.
- `api` — routes, Form Requests, Resources, envelopes, and consumer contracts.
- `database` — Eloquent models, constraints, queries, and migrations.
- `performance` — HTTP/queue reliability, N+1, retries, and resource bounds.

Agents are read-only. The main conversation verifies findings and owns any
approved remediation.

### Domain playbook skills

These are automatically selected background skills and are hidden from the
slash-command menu:

- `resource-pattern` — the canonical layered CRUD pattern and the
  `app:generate-resource` scaffolder.
- `auth-security` — Passport, sign-in/sign-up, verification, RBAC,
  rate limiting, and the per-endpoint security checklist.
- `feature-testing` — the Docker/MySQL harness, `./test.sh`, auth helpers,
  factories, and assertion conventions.
- `money-precision` — the `BigDecimal` rules for any money or decimal.
- `background-work` — queued jobs, Horizon, and scheduled commands.
- `external-integration` — third-party integrations behind an `app/Utils`
  boundary.
- `laravel-best-practices`, `configuring-horizon`, `passport-development` —
  vendor-curated reference skills.

### Templates

- `templates/plan.md`
- `templates/threat-model.md`
- `templates/api-contract.md` — worksheet for route/Resource/event changes
- `templates/database-design.md` — worksheet for model and migration changes

### Standards

- `standards/architecture.md`
- `standards/coding.md`
- `standards/security.md`
- `standards/testing.md`
- `standards/gate-handoff.md` — how every gate closes and hands off.

### Hooks and validation

- `hooks/guard-dangerous-commands.sh` — resolves the effective verb of every
  subcommand and blocks human-owned operations a prefix rule cannot see.
- `hooks/guard-protected-paths.sh` — path-specific `ask` with the precondition
  that path requires.
- `hooks/format-php.sh` — runs `php artisan app:format` after every `.php` edit,
  deriving the container name from `SERVICE_NAME` so a forked project keeps
  working.
- `app/Console/Commands/ValidateClaudeConfigCommand.php` — the guardrail for
  this directory, run by `php artisan app:validate-claude-config` and in CI.

### Where the design lives

Nowhere in the repository. `/gate-design` presents a plan through Claude Code's
plan flow, structured on `templates/plan.md`; approval is the plan-mode decision.
When a decision must outlive the session, put it in a **code comment beside the
thing it protects**, where it will actually be found.

`CLAUDE.md` is the always-on project constitution. Repository-specific rules in
`CLAUDE.md` take precedence over generic guidance.

## Review engines

The project agents are the required review mechanism because they understand
this repository's exact contracts.

Bundled Claude Code skills may supplement them:

- `/security-review` for an additional read-only security pass;
- `/simplify` after correctness/security findings are resolved, with every
  proposed change verified against the plan and project standards;
- `/code-review` for correctness bugs on low-risk changes.

Do not depend on a bundled command that is unavailable on a developer's Claude
Code version or plan. The project review and validation skills remain complete
without it.

## Issue-tracker behavior

When `/work-item` receives a real issue key and the configured tracker MCP is
connected, it may:

- read the issue during Stage 1;
- post exactly one completion/blocker comment during Stage 7.

Invoking `/work-item <issue key or URL>` is standing authorization for that one
comment.

It must never (each is enforced by a `permissions.deny` rule, not just prose):

- transition the issue;
- edit fields, status, assignee, priority, or sprint;
- claim code is committed, pushed, merged, released, or deployed;
- create a related/blocker issue unless the user separately asks.

The Stage 7 comment is written for the reporter, QA, and standup — not for the
developer reviewing the diff. It answers:

1. what behavior changed;
2. what changed beyond the ticket;
3. what remains blocked.

No file paths, class names, or method names belong in the comment.

## Guardrails

`.claude/settings.json` is enforcement, not reminders — and it is committed, so
every developer inherits the same floor. Three mechanisms, each with a distinct
job:

**1. `permissions.deny` — the hard floor.** Declarative, evaluated before every
hook, and impossible to fail open. This is where `CLAUDE.md`'s *Human-owned
operations* list is made real, **for the exact command forms it names**:

- git history and publication writes (`commit`, `push`, `merge`, `rebase`,
  `tag`, `reset`, `clean`, `stash`, `checkout`, `cherry-pick`, `revert`, …);
- `gh pr create|merge|close|edit|ready|review`, `gh release`, `gh workflow run`;
- every migration application and data destruction (`php artisan migrate*`,
  `db:wipe`, `db:seed`, `./start.sh fresh`, `./start.sh reset`);
- volume destruction (`docker compose down`, `docker volume rm|prune`) — use
  `./stop.sh`, which is allowed and never passes `-v`;
- image push;
- **the Read and Edit tools** against real environment files, `auth.json`, and
  private keys. `.env.example` and `.env.testing` stay readable —
  `/gate-validate` needs them. A `Read(.env)` rule governs the Read *tool*; it
  says nothing about `cat .env`, which is layer 3's job;
- issue-tracker transition, field-edit, update, delete, and assignment tools.

Every Bash rule is mirrored as a `PowerShell(...)` rule. The PowerShell tool is
enabled by default on Windows without Git Bash, and `Bash(...)` rules do not
govern it — an unmirrored floor simply disappears on those machines, with no
warning anywhere. The validator fails when the two lists diverge.

MCP rules use a **glob server segment** — `mcp__*__transitionJiraIssue`, never
`mcp__atlassian__…`. A tool is named `mcp__<server>__<tool>`, and `<server>` is
whatever this project called it in `.mcp.json`; a hardcoded name matches
nothing in a project that renamed its server, so the tracker floor is absent
while this document still promises it. Deny and ask rules accept a glob there
(only *allow* rules require a literal server). The validator enforces it.
Tool *names* still need one check per tracker: run `/mcp`, list the server's
tools, and confirm the write verbs are covered.

**2. `permissions.ask` — dual-use commands needing human judgment.** Dependency
changes (`composer update|require|remove`), `php artisan tinker`, Passport key
regeneration, `mysql`/`psql`/`redis-cli`, `gh api` (read or write depending on
its method), `git branch|worktree`, and issue creation.

**3. Hooks — the layer that sees what a prefix rule cannot.** A `permissions`
rule matches a command *prefix*, so it is structurally blind to the same
operation written any other way:

```text
git -C /elsewhere commit -m x                             flag before the verb
docker exec -it laravel-api bash -c "php artisan migrate"  container indirection
sudo composer update                                       privilege wrapper
cat .env                                                   the shell, not the Read tool
```

**The container case is the one that matters most here.** Almost every artisan
and composer command in this repository runs as
`docker exec ${SERVICE_NAME}-api …`, usually wrapped again in `bash -c`. No
prefix rule can see through that. A blanket "`docker exec` needs approval" rule
would prompt on literally every command a developer runs, and a guard that is
always firing is indistinguishable from noise — it gets switched off within a
day, and then it protects nothing. So `guard-dangerous-commands.sh` **unwraps**
the container indirection and the shell wrapper, then classifies the command
actually being run inside.

Hooks also teach: a deny rule gives an anonymous refusal, a hook explains the
precondition. All three live in `.claude/hooks/` as real scripts — `set -euo
pipefail`, syntax-checked and behaviour-tested in CI, and the two guards **fail
closed**: an unavailable `jq` degrades to a prompt, never to silent approval.

- `guard-dangerous-commands.sh` — parses every subcommand of a Bash or
  PowerShell call, resolves the effective verb behind wrappers, containers and
  runners, and denies human-owned operations, credential reads, and
  unrecoverable removals. Its git verb table is kept in lockstep with the deny
  rules by the validator;
- `guard-protected-paths.sh` — `ask` before editing `database/migrations/**`,
  with the precondition that path requires;
- `format-php.sh` — the PostToolUse formatter. Deliberately does *not* fail
  closed: a stopped container is a normal state, and a formatter that blocks
  edits when Docker is down is worse than one that skips.

**None of this is a sandbox, and it must not be described as one.** A shell can
always express an operation the parser does not model — a verb built from a
variable, an operation inside a script file, a here-doc. Layer 1 cannot fail
open but only sees the forms it names; layer 3 sees far more forms but is
executable code that can fail. They are complementary, and neither is a
boundary. For a real boundary use OS sandboxing or a container.

Precedence is `deny` → `ask` → `allow`, first match wins, and a deny rule cannot
carry an exception. Deny rules in this file override any allow rule a developer
adds in their own `settings.local.json`. A `PreToolUse` hook cannot loosen them
either: Claude Code evaluates deny and ask rules regardless of what a hook
returns.

> **Note on file rules:** Claude Code checks file permissions against `Edit()`
> and `Read()` only. A `Write(...)`, `NotebookEdit(...)`, `MultiEdit(...)` or
> `Glob(...)` path rule is accepted, never consulted, and warns at startup —
> the worst failure shape available, because the file reads as protected. Use
> `Edit(...)` and `Read(...)`. The validator rejects the inert forms in all
> three tiers.

Per-developer conveniences (a broader Bash allowlist, WebFetch domains) belong
in `.claude/settings.local.json`, which is gitignored.

## Issue tracker setup

The repository commits `.mcp.json` with an Atlassian server declaration and the
Laravel Boost server. OAuth credentials remain per developer.

For Atlassian:

1. Open the repository in Claude Code.
2. Approve the configured MCP server.
3. Run `/mcp`.
4. Select the project-specific Atlassian server.
5. Authenticate in the browser.

If no tracker is connected, paste the ticket text. Stages 1–6 still run and
Stage 7 is skipped.

Use a project-specific MCP server name so OAuth state does not accidentally
cross projects — Claude Code keys the token by server name, so two projects both
calling their server `atlassian` share one account.

## Validating this directory

```text
php artisan app:validate-claude-config
```

`.claude/` is several thousand lines of frontmatter and cross-references that
fail *silently*: a mistyped key is ignored, a renamed class leaves a skill
quoting code that no longer exists, and a skill whose name collides with a
bundled command resolves unpredictably. `CLAUDE.md` requires every convention to
ship with a guardrail; this is the tooling's own.

The command checks frontmatter against the documented schema, skill/agent name
agreement, collisions with built-in commands, the 1,536-character skill-listing
cap, cross-reference resolution, and doc-vs-code symbol drift.

It also enforces the guardrails above:

- every agent is read-only, judged by its **effective tool pool** rather than by
  a phrase in its prose — a check that cannot catch an omission is not a check;
- the required deny floor is present, so deleting one line cannot quietly
  retract a promise that `CLAUDE.md` still makes;
- `Bash` and `PowerShell` deny/ask rules stay mirrored;
- MCP rules use a glob server segment, so the tracker floor survives being
  copied into a project that renamed its server;
- the guard hook's git verb table matches the deny rules;
- dead `Write()`/`Glob()`/`NotebookEdit()`/`MultiEdit()` path rules in any tier;
- hook scripts exist, parse, are executable, and are actually referenced by
  `settings.json` — an unwired script still reads as an active guard;
- **the guard hook decides correctly**, against a fixture table of about fifty
  commands. `bash -n` and `chmod +x` prove a hook runs, not that it is right,
  and a hook that crashes exits non-zero, which Claude Code treats as a
  non-blocking error — so a broken guard fails *open*. The table covers the
  container, wrapper and flag-bearing forms, the parser regressions found while
  writing it, and ordinary commands such as `./test.sh` and
  `docker exec laravel-api php artisan app:format` that must never prompt.

It runs in CI as part of `./test-pipeline.sh`.

Restart Claude Code after adding or replacing skills and agents.

## Deferred

An isolated worktree implementation fan-out is intentionally deferred.

Add it only when a change can be decomposed into independent edits with clear
ownership and merge boundaries. The main conductor must coordinate the
subagents; subagents do not recursively spawn other subagents.
