---
name: auth-security
user-invocable: false
description: Use when working on auth, sign-in/sign-up, Passport tokens, email verification, password/username/email updates, RBAC/permissions (Spatie roles + Gates), rate limiting, or doing a security review of any endpoint/Request/Resource (enumeration, mass-assignment, FK/role escalation, permission checks, over-exposed Resource fields, unbounded list reads, token revocation). The defaults here are the hardening floor — know them before relaxing any.
---

# Auth & security model

This template ships a deliberately small, uniform auth surface. The rules below are the **floor** — understand them before relaxing any, and keep new auth-adjacent endpoints consistent with them. Trace `AuthController`, `UserController`, `UserService`, and `AppServiceProvider::boot()` before changing auth behavior.

## Auth stack (Laravel Passport, OAuth2)

- Guards (`config/auth.php`): `api` → `passport` driver (every API route), `web` → `session`. Non-public routes sit under `auth:api`.
- Token lifetimes (`AppServiceProvider::boot()`): access **8h**, refresh **30d**, personal-access **90d**. Passport keys/clients are created by `./start.sh fresh` — never commit private keys.
- **Sign-in** `POST auth/sign-in` (`AuthController`, no service layer — the one auth exception to the pipeline): `identifier` is email **or** username (`AppUtil::isValidEmail` picks the column), `Auth::attempt(...)` then an email-verified gate, returns `{ "token": <accessToken> }`. Failure is always the generic `Invalid username or password` (`ResponseUtil::error`, 400) — wrong password and unknown user are indistinguishable, so there is **no user enumeration**. The controller is `guest`-middleware'd except `signOut`.
- **Sign-out** `POST auth/sign-out`: `Auth::user()->token()->delete()` — revokes only the **current** access token, not other sessions.
- **Password change revokes every session.** `UserService::updatePassword` calls `revokeAllUserTokens($user)` after a successful change (revokes access **and** refresh tokens in a transaction), so a self-change or an admin reset forces re-authentication everywhere — including the caller's current token. Reuse this helper on any new password-mutating path.
- **No account lockout / failed-attempt counter.** The brute-force floor is purely the `sensitive` rate limiter (5/min/IP, below).

## Sign-up + email verification

- **Sign-up** `POST users/sign-up` (public, `throttle:sensitive`): creates the user with `email_verified_at = null`, auto-generates a unique username from the email, `Hash::make`es the password (model `password` cast is `hashed`), then `sendEmailVerificationNotification()`. Returns a generic success message — no "email already taken" oracle in the response.
  - `SignUpUserRequest` enforces `password min:8` + `passwordConfirmation same:password`, but does **not** validate `unique:users,email`; a duplicate email currently hits the DB unique constraint → generic 500. Add `Rule::unique(...)` (or a service `isEmailExists` check throwing `BadRequestException`) if you want a clean 400 without leaking existence — decide per fork.
- **Verify** `GET email/verify/{id}` (named `verification.verify`): `EmailVerificationRequest` + `hasValidSignature()` (Laravel signed URL). Re-verifying throws `Email already verified.`
- **Login gate**: an unverified user who supplies the *correct* password gets `Email not yet verified...`; a wrong password stays generic. Acceptable post-auth signal for this template.

## RBAC / permissions (spatie/laravel-permission)

- `User` uses `HasRoles`. Roles → `UserRole` enum (`SYSTEM_ADMIN`, `APP_ADMIN`). Permissions → `UserPermission` enum (`CREATE/READ/UPDATE/DELETE_USER`).
- Every `UserPermission` case is registered as a **Gate** in `AppServiceProvider::boot()`, checked against the **`api` guard** (`hasPermissionTo($permission, UserPermission::getApiGuardName())`). The loop auto-picks-up new cases.
- **Enforce in the controller** with `Gate::authorize(UserPermission::X)` — it throws `AuthorizationException` → 403 via the `bootstrap/app.php` handler (no controller branching). Admin user endpoints (`create`/`getPaginated`/`getById`/`update`/`delete`) already do this; the `users/auth/*` self-service endpoints are any-authenticated-user by design.
- **Add a permission**: add the case to `UserPermission`, grant it to roles via `UserPermission::fromUserRole()` + the permission seeder, then `Gate::authorize(...)` at the call site. No provider change needed — the Gate wiring loop covers it.

## Uniform response & error shapes (no leakage)

- Business/validation failure → `{"success":false,"message":...}` at **400** (`BadRequestException::render` and `ResponseUtil::error` share this shape — same as request-validation failures, so no controller branching). `401` → `{"message":...}` (`ResponseUtil::unauthorized`), `403` → same (`ResponseUtil::forbidden`). Mapped in `bootstrap/app.php`: `AuthenticationException`→401, `AuthorizationException`/`AccessDeniedHttpException`→403.
- `User` never serializes `password`/`remember_token` (`$hidden`). All user output flows through `UserResource`; roles/permissions are exposed **only** when `?includeAccessControl=true`. `$fillable = []` — no mass assignment; repositories assign columns explicitly. On soft-delete, `User::boot()` scrambles `username`/`email` (`_deleted_<ts>` suffix) so they free up for reuse.

## Rate-limiter floor (`AppServiceProvider::boot()`)

| Limiter | Budget | Use for |
|---|---|---|
| `public` | 60/min/IP | unauthenticated reads |
| `sensitive` | 5/min/IP | `auth/sign-in`, `users/sign-up`, email verify — the brute-force / enumeration floor |
| `api` | 60/min/token **+** 120/min/user **+** 300/min/IP | authenticated endpoints (layered: kills a stolen token hard, caps a user across tokens, IP backstop tolerant of NAT) |
| `heavy` | 10/min/token | resource-intensive endpoints (exports/imports) |

**Any new public or auth-input endpoint (login variants, OTP, password reset, resend) must sit under `sensitive` or a stricter named limiter — never bare.**

## Hardening opportunities — wire these when a fork's threat model needs them

The base template keeps these minimal on purpose; a fork handling sensitive data should close them:

- **Admin password reset is unconstrained.** `updatePassword` lets any `SYSTEM_ADMIN`/`APP_ADMIN` set any user's password (`PUT users/{userId}/password`) with no current-password proof and no self-target refusal. Fine for trusted-admin; for a hostile-admin model require current-password on self-service and block admin→self hijack.
- **Email change does not re-verify.** `updateEmail` swaps the address without clearing `email_verified_at`, so a verified account stays "verified" on an unconfirmed address. If the verified flag gates anything, reset it to `null` + resend verification on change.
- **No lockout.** Only the `sensitive` limiter stands against guessing. Add a failed-attempt lockout if 5/min/IP is insufficient (e.g. distributed attempts).

## Security review checklist (run on every new endpoint / Request / Resource)

- **AuthZ**: under `auth:api`? Mutating/admin action calls `Gate::authorize(...)`? Ownership check for "my own record" endpoints (mirror `updatePassword`'s self-vs-admin guard)?
- **Enumeration**: auth-path errors stay generic — never distinguish "no such user" from "wrong password".
- **Mass assignment**: `$fillable` stays empty; assign columns explicitly in the repository. Never `Model::create($request->all())`.
- **FK / role escalation**: a body-supplied id (`role`, owner, FK) must not let a caller grant themselves access — validate/authorize server-side, don't trust client ids.
- **Resource exposure**: the `Resource` must not leak secrets/PII/internal columns; gate sensitive fields (as `UserResource` gates `includeAccessControl`).
- **Unbounded reads**: lists go through `getPaginated` (`meta->perPage`) — never return a full table.
- **Rate limit**: public/auth-input endpoints under `sensitive` or stricter.
- **Route ids**: numeric ids constrained with `->where('<x>Id', config('custom.numeric_regex'))`.
- **Error leakage**: a service `catch (Throwable)` must not surface a raw internal message (DB/SQL, file paths) to the client. Re-throw `BadRequestException`/`ProcessingException` first to preserve the specific user-facing message, then log the real cause server-side and throw a safe generic one (see `UserService::create` and `SpreadsheetService::readRawFileAsWorkSheet`).

These are the "Security is non-negotiable" clause of the CLAUDE.md **Engineering bar**, made concrete.

## Supply-chain scanning (CI)

`.github/workflows/security.yml` runs on every PR into `main`/`staging`/`develop`, weekly (Monday 03:00 UTC), and on-demand — kept separate from the fast test gate (`.github/workflows/test.yml` → `./test-pipeline.sh`) so a slow/noisy scan never blocks the merge signal. Two jobs:
- **`composer-audit`** — `composer validate` + `composer audit` (informational at any severity, **blocks on HIGH/CRITICAL** through `.github/scripts/audit-gate.php`). The script exists because `composer audit` cannot accept a *single* advisory: an all-or-nothing gate meets one unfixable finding and gets deleted. It allows a **documented, dated** exception per advisory, nags when one goes stale or passes its review date, and refuses to read an unparseable report as a clean result.
- **`trivy-fs`** — Trivy filesystem scan (`vuln,secret,misconfig`): vulnerable `composer.lock` deps, `Dockerfile` misconfig, committed secrets. Blocks on HIGH/CRITICAL, `ignore-unfixed`.

## Dynamic scanning (DAST)

`.github/workflows/security-dast.yml` runs OWASP ZAP weekly (Monday 03:00 UTC) and on demand, against a **fully ephemeral** app with its own throwaway MySQL and Redis — never a shared or real environment. It is separate from the merge gate because active rules send real `POST`/`PUT`/`DELETE` and the scan is slow and stateful.

The scan is driven by the generated OpenAPI document (`php artisan scramble:export`), so it exercises every declared operation rather than spidering a headless JSON API and finding only the 401 wall. Three details are load-bearing:

- It signs in as the seeded system admin through the real `POST /api/auth/sign-in` and injects `Authorization: Bearer …` on every request via a ZAP replacer rule, so **33 of the 37 operations behind `auth:api` are actually reached**. Deliberately not a bespoke token-minting artisan command: that would be a permanent privileged surface in every forked project, to save one HTTP call.
- It raises `RATE_LIMIT_*` to effectively unlimited. `sensitive` is 5/min in production and would otherwise stop the scan at the auth wall. This is the one legitimate reason those limits are config-driven.
- `APP_DEBUG` stays `false`, so the scan sees production-shaped error bodies. A debug stack trace would both mask real information disclosure and manufacture findings that do not exist in production.

**The scan is informational until you tune it.** ZAP runs with `-I`, so only a rule explicitly set to `FAIL` in `.zap/rules.tsv` can fail the build. That file is the single tuning seam: triage the report, `IGNORE` what is structurally inapplicable *with a stated reason*, and promote to `FAIL` what must never regress. A scan with no `FAIL` rule is a report, not a gate — and a job that red-lights on untriaged findings gets disabled within a week, at which point it protects nothing.
