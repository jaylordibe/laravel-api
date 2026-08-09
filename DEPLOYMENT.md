# Deployment Guide

## Overview

**This template ships no configured deployment target.** Your project chooses its
own, and the workflows below are the skeleton to wire up.

---

## What the application still expects

None of this changed — it is a property of the image, not of the deployment
target.

- **Runtime image:** built from the custom base image `jaylordibe/laravel-php:8.5`
  via the repository `Dockerfile`, which already runs, under Supervisor:
    - `php-fpm` — the API;
    - `php artisan horizon` — the queue worker.
- **Database:** MySQL.
- **Cache/queue:** Redis (`QUEUE_CONNECTION=redis`).
- **Required environment variables** at minimum:
    - `APP_ENV=production`, `APP_KEY` (from `php artisan key:generate`),
      `APP_URL`
    - `SERVICE_NAME`
    - `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`,
      `DB_PASSWORD`
    - `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`
    - `QUEUE_CONNECTION=redis`
  See `.env.example` for the full list; it is the authoritative shape.
- **Passport keys** must exist in the deployed environment
  (`php artisan passport:keys`). Regenerating them invalidates every issued
  token, so generate once per environment and treat them as secrets.

---

## Wiring up a real deployment

`.github/workflows/deploy-to-production.yml` and `deploy-to-staging.yml` are
ready except for the one step that actually ships. Replace the
`Deploy to …` step, then set the repository variable `DEPLOY_ENABLED=true`.

**Keep the surrounding shape** — each property is load-bearing:

| Property | Why it is there |
|---|---|
| `uses: ./.github/workflows/test.yml` | The deploy gates on the *same* test workflow a PR runs. Restating the job is how a check ends up enforced on the PR but not on the deploy that follows it. |
| `if: vars.DEPLOY_ENABLED == 'true'` | A fresh fork runs CI and skips the deploy, instead of failing on secrets it does not have yet. |
| `concurrency` with `cancel-in-progress: false` | Two pushes in quick succession queue instead of racing; an in-flight deploy is never killed half-done. |
| `environment:` | Scopes secrets to the environment and lets you require reviewer approval before the job runs — the human gate on production. |
| `timeout-minutes` | A runaway-job guard. If it trips, find out what got stuck rather than raising the number. |
| Smoke test | Fails the run when the app does not answer, so a broken build cannot sit silently in production. It probes `GET /api/app-versions/latest`, the one public route that exercises the whole stack without minting a token. |

### Migration ordering matters

Run migrations **before** the new build starts serving. That means the *old*
build runs against the *new* schema for the length of the swap, so every
migration must be backwards-compatible with the currently-deployed code
(expand → migrate → contract, across two releases).

This is not left to discipline: the `migration-safety` job in
`.github/workflows/test.yml` applies the proposed migrations to a **seeded**
database and then boots the **released** build against the migrated schema. A
migration that drops or tightens something the running code still reads fails
there, in CI, instead of mid-deploy.

---

## Key files

- **`Dockerfile`** — builds the application on the `jaylordibe/laravel-php:8.5`
  base image.
- **`.dockerignore`** — trims the Docker build context.
- **`.github/workflows/test.yml`** — the reusable gate: format check + parallel
  test suite, and migration safety. Runs on every PR into
  `main`/`staging`/`develop` and is called by both deploy workflows.
- **`.github/workflows/security.yml`** — Composer advisory gate (with a
  documented, dated allowlist in `.github/scripts/audit-gate.php`) and a Trivy
  filesystem scan. Runs on PRs, weekly, and on demand.
- **`.github/workflows/security-dast.yml`** — OWASP ZAP scan of an ephemeral
  app, driven by the generated OpenAPI document. Weekly and on demand; never on
  the deploy path.
- **`.github/workflows/deploy-to-production.yml`** /
  **`deploy-to-staging.yml`** — the opt-in deploy skeletons described above.
