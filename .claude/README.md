# Claude Code tooling

Team-shared Claude Code assets for this Laravel starter: an agentic **ticket-to-diff pipeline** and the guardrails that keep it inside the house rules. Everything here is committed, so a project scaffolded from this template inherits the workflow — the only per-machine step is a one-time issue-tracker login (below).

This is developer *tooling*, not part of the API. The API runs and tests with none of it. If you don't use Claude Code, ignore this directory.

## The ticket-to-diff pipeline

`/ticket <TICKET-KEY | pasted ticket text>` drives a ticket through six stages and **stops at two human gates** — it never implements without an approved plan and never commits or pushes.

| Stage | What runs | Gate |
| --- | --- | --- |
| 1. Understand | `context-mapper` agent produces a read-only impact map (touched layers, contract surface, security surface, downstream consumers, tests implied) | |
| 2. Plan | An ADR-style plan in Plan mode | 🛑 **GATE 1** — waits for your approval before any edit |
| 3. Implement | Scaffolds via `php artisan app:generate-resource`, then fills the layered pipeline to the `CLAUDE.md` engineering bar | |
| 4. Review | `/code-review` + `/security-review` + `/simplify`, findings folded back in | |
| 5. Verify | `php artisan app:format --check` + the affected `./test.sh` feature test (no build/type-check step in this stack) | |
| 6. Present | Diff summary + review/verify results + downstream-consumer handoff | 🛑 **GATE 2** — you commit; the pipeline stops here |

The conductor keeps a durable six-stage todo checklist so the pipeline survives a long plan discussion (or a context summarization) and resumes at the right stage on approval, rather than treating "looks good" as a fresh request.

### Components

- **`commands/ticket.md`** — the conductor (the `/ticket` command). A prompt, not a state machine; the checklist is its durable memory.
- **`agents/context-mapper.md`** — a read-only (`Read`/`Grep`/`Glob`/`Bash`) specialist that scopes a ticket's full blast radius across the Route → Controller → Request → Data → Service → Repository → Model → Resource layers. Never edits.

### Design principle

Buy the engines, build only the specialists and the conductor. Review (`/code-review`, `/security-review`, `/simplify`), search (`Explore`), and the plan gate (Plan mode) are all built-ins — we never reimplement them. The only custom assets are the two files above plus the guardrails below.

## Guardrails (`settings.json` hooks)

Enforced by the harness, not by an agent remembering:

- **git write block** — a `PreToolUse` hook denies any `git commit` / `git push`. All git writes are the human's.
- **migration-edit guard** — a `PreToolUse` hook asks before editing anything under `database/migrations/` (never edit an applied migration; create a new one via `php artisan make:migration`).
- **auto-format** — a `PostToolUse` hook runs `php artisan app:format` after every `.php` edit.

## One-time setup: connect your issue tracker (MCP)

The repo commits **`.mcp.json`** declaring an Atlassian (Jira / Confluence) MCP server. No secret lives in it — the connection is per-developer OAuth, so nothing sensitive is ever committed. To enable it once, per machine:

1. Open the repo in Claude Code and approve the `atlassian` server when prompted.
2. Run `/mcp` → select **atlassian** → **Authenticate** → approve in the browser.

Once done, Stage 1 of `/ticket TICKET-123` auto-fetches the issue. Without it, paste the ticket text into `/ticket` instead — the rest of the pipeline is identical. **Not using Jira?** Delete `.mcp.json` (or swap in your tracker's MCP server); the pipeline still works with pasted text.

## Deferred

- An `implementer` agent + git-worktree fan-out for parallel implementation (only worth building when a ticket genuinely needs concurrent, isolated edits).
