---
name: auth-security
user-invocable: false
description: Use when working on auth, sign-in/sign-up, Passport tokens, email verification, password/username/email updates, RBAC/permissions (Spatie roles + Gates), rate limiting, or doing a security review of any endpoint/Request/Resource (enumeration, mass-assignment, FK/role escalation, permission checks, over-exposed Resource fields, unbounded list reads, token revocation). The defaults here are the hardening floor — know them before relaxing any.
---

# Auth & security model

This template ships a deliberately small, uniform auth surface. The rules below are the **floor** — understand them before relaxing any, and keep new auth-adjacent endpoints consistent with them. Trace `AuthController`, `UserController`, `UserService`, and `AppServiceProvider::boot()` before changing auth behavior.

## Auth stack (Laravel Passport, OAuth2)

- Guards (`config/auth.php`): `api` → `passport` driver (every API route), `web` → `session`. Non-public routes sit under `auth:api`.
- **Passport's `/oauth/*` routes are not registered** (`Passport::ignoreRoutes()` in `AppServiceProvider::register()`). Tokens come only from sign-in's in-process `createToken()`, and no OAuth grant is reachable over HTTP.
- **Sign-in session lifetime** is `config('custom.auth.token_ttl_minutes')` (env `AUTH_TOKEN_TTL_MINUTES`, default 30 days in `config/custom.php`). `createToken()` issues a personal access token, so this is the session. There is no refresh flow: when it ends the user signs in again, and sign-in reports `expiresIn` (seconds) so a client can do that before the 401. Expiry is the JWT `exp`, fixed at issue — changing the lifetime affects new tokens only. The 8h access / 30d refresh lifetimes in `boot()` apply only to `/oauth/token` grants and are inert; they stay so a fork re-enabling a grant does not inherit Passport's one-year default. Passport keys/clients are created by `./start.sh fresh` — never commit private keys.
- **Sign-in** `POST auth/sign-in` (`AuthController`, no service layer — the one auth exception to the pipeline): `identifier` is email **or** username (`AppUtil::isValidEmail` picks the column), `Auth::attempt(...)` then an email-verified gate, returns `{ "token": <accessToken>, "expiresIn": <seconds> }`. Failure is always the generic `Invalid username or password` (`ResponseUtil::error`, 400) — wrong password and unknown user are indistinguishable, so there is **no user enumeration**. The controller is `guest`-middleware'd except `signOut` and `signOutAll`.
- **Sign-out** `POST auth/sign-out`: `Auth::user()->token()->delete()` — revokes only the **current** access token, not other sessions.
- **Sign-out everywhere** `POST auth/sign-out-all` (`auth:api`): `UserService::revokeAllUserTokens` for the caller — every session, the current one included; nobody else's.
- **Password change signs out other sessions.** `UserService::updatePassword` calls `revokeAllUserTokens()` (access **and** refresh tokens, in a transaction). Changing your own password keeps the session you did it from, like Laravel's `logoutOtherDevices()`; an admin reset signs the user out everywhere. Reuse this helper on any new password-mutating path.
- **Changing your OWN password needs `currentPassword`** — on `PUT users/auth/password` and on `PUT users/{userId}/password` when `userId` is the caller, admins included. Enforced once, in `UserService::updatePassword` (the admin route sets the target after validation, so the request cannot know). Without it a stolen token could set a new password, sign the owner out everywhere and keep the account however short the token lives. Only an admin resetting **someone else's** password skips it.
- **No account lockout / failed-attempt counter.** The brute-force floor is the `sign-in` rate limiter: 5/min per identifier + IP, as in Laravel's starter kits.

## Sign-up + email verification

- **Sign-up** `POST users/sign-up` (public, `throttle:sensitive`): creates the user with `email_verified_at = null`, auto-generates a unique username from the email, `Hash::make`es the password (model `password` cast is `hashed`), then `sendEmailVerificationNotification()`. A taken email is a 400 validation error (`The email has already been taken.`), as in Laravel's starter kits.
- **Verify** `GET email/verify/{id}` (named `verification.verify`, `throttle:sensitive`, **no auth** — it is opened from an email): the signed URL is the only credential. `UserController::verifyEmail` takes a `GenericRequest` — **not** `EmailVerificationRequest`, which authorizes against a signed-in user and 500s here. Order: `hasValidSignature()` first (400 `Invalid verification link.` before any lookup), then `UserService::verifyEmail` checks `hash_equals(sha1(current email), ?hash)` so a link issued for an old address cannot verify a new one; a missing user answers identically. Opening a link again returns 200 (mail scanners prefetch links).
- **Resend** `POST email/verification-notification` (`throttle:sensitive`, no auth, `{email}`): sends a new link to an unverified account; the answer is the same for any address. Admin-created users are sent a link on creation. Verification mail is queued (`VerifyEmailNotification`), so a worker must run.
- **Password reset** (Laravel's `Password` broker): `POST forgot-password {email}` always answers the same 200 and queues `ResetPasswordNotification`; its link opens `APP_FRONTEND_URL/reset-password?token=…&email=…` (`ResetPassword::createUrlUsing` in `AppServiceProvider`), and the client posts `POST reset-password {token, email, password, passwordConfirmation}`. A successful reset signs out every session. Tokens expire after 60 minutes (`config/auth.php`).
- **Email change** (`PUT users/auth/email`) clears `email_verified_at` and sends a link to the new address, like Fortify; the current session keeps working.
- **Login gate**: an unverified user who supplies the *correct* password gets `Email not yet verified...`; a wrong password stays generic. Acceptable post-auth signal for this template.

## RBAC / permissions (spatie/laravel-permission)

- `User` uses `HasRoles`. Roles → `UserRole` enum (`SYSTEM_ADMIN`, `APP_ADMIN`). Permissions → `UserPermission` enum (users CRUD, `CREATE/UPDATE/DELETE_APP_VERSION`, `READ_ACTIVITY_LOG`). The Gate loop uses `checkPermissionTo`, so a permission an environment has not seeded yet **denies (403)** instead of throwing `PermissionDoesNotExist` (500) — after adding a case, sync permissions as the deploy order in `DEPLOYMENT.md` §14 describes (the non-deleting seeder, **not** `app:reset-role-permissions`, which deletes roles/permissions missing from the enums).
- Every `UserPermission` case is registered as a **Gate** in `AppServiceProvider::boot()`, checked against the **`api` guard** (`checkPermissionTo(...)` — see the bullet above for why). The loop auto-picks-up new cases.
- **Enforce in the controller** with `Gate::authorize(UserPermission::X)` — it throws `AuthorizationException` → 403 via the `bootstrap/app.php` handler (no controller branching). Admin user endpoints (`create`/`getPaginated`/`getById`/`update`/`delete`) already do this; the `users/auth/*` self-service endpoints are any-authenticated-user by design. A check that depends on the data — your own record needs nothing, someone else's needs a permission — is a branch, so it goes in the **service** (`ActivityLogService::getPaginated`), never the controller.
- **Add a permission**: add the case to `UserPermission`, grant it to roles via `UserPermission::fromUserRole()` + the permission seeder, then `Gate::authorize(...)` at the call site. No provider change needed — the Gate wiring loop covers it.

## Uniform response & error shapes (no leakage)

- Business/validation failure → `{"success":false,"message":...}` at **400** (`BadRequestException::render` and `ResponseUtil::error` share this shape — same as request-validation failures, so no controller branching). `401` → `{"message":...}` (`ResponseUtil::unauthorized`), `403` → same (`ResponseUtil::forbidden`). Mapped in `bootstrap/app.php`: `AuthenticationException`→401, `AuthorizationException`/`AccessDeniedHttpException`→403.
- `User` never serializes `password`/`remember_token` (`$hidden`). All user output flows through `UserResource`; roles/permissions are exposed **only** when `?includeAccessControl=true`. `$fillable = []` — no mass assignment; repositories assign columns explicitly. On soft-delete, `User::boot()` scrambles `username`/`email` (`_deleted_<ts>` suffix) so they free up for reuse.

## Rate-limiter floor (`AppServiceProvider::boot()`)

| Limiter | Budget | Use for |
|---|---|---|
| `public` | 60/min/IP | unauthenticated reads |
| `sign-in` | 5/min per identifier + IP | `auth/sign-in` — capped per account without locking out a shared IP |
| `sensitive` | 5/min/IP | `users/sign-up`, email verify |
| `api` | 60/min/token **+** 120/min/user **+** 300/min/IP | authenticated endpoints (layered: kills a stolen token hard, caps a user across tokens, IP backstop tolerant of NAT) |
| `heavy` | 10/min/token | resource-intensive endpoints (exports/imports) |

**Any new public or auth-input endpoint (login variants, OTP, password reset, resend) must sit under `sensitive` (or a purpose-keyed limiter like `sign-in`) — never bare.**

## Hardening opportunities — wire these when a fork's threat model needs them

The base template keeps these minimal on purpose; a fork handling sensitive data should close them:

- **Admin reset of another user needs no proof.** Any `SYSTEM_ADMIN`/`APP_ADMIN` can set another user's password (`PUT users/{userId}/password`), including the other admin role's. So a **stolen admin token** can still take over another account — including a second admin, which then resets the first — however short the token lives; closing that needs step-up re-authentication on privileged actions. Also, a fork that makes the two roles unequal should check the target's role.
- **No lockout.** Only the `sign-in` limiter stands against guessing. Add a failed-attempt lockout if that is insufficient (e.g. distributed attempts).

## Security review checklist (run on every new endpoint / Request / Resource)

- **AuthZ**: under `auth:api`? Mutating/admin action calls `Gate::authorize(...)`? Ownership check for "my own record" endpoints (mirror `updatePassword`'s self-vs-admin guard)? Owned records (device tokens, activity logs) are scoped **in every repository query, unconditionally** — never behind `if (!empty($userId))`, and never with an owner id taken from input; another user's record answers exactly like a missing one (same 400).
- **Enumeration**: auth-path errors stay generic — never distinguish "no such user" from "wrong password".
- **Mass assignment**: `$fillable` stays empty; assign columns explicitly in the repository. Never `Model::create($request->all())`.
- **FK / role escalation**: a body-supplied id (`role`, owner, FK) must not let a caller grant themselves access — validate/authorize server-side, don't trust client ids.
- **Resource exposure**: the `Resource` must not leak secrets/PII/internal columns; gate sensitive fields (as `UserResource` gates `includeAccessControl`).
- **Query envelope**: a new relation or sort field is opted in on the Request's `ALLOWED_RELATIONS` / `SORTABLE_FIELDS` — never a hidden column, never a relation its Resource does not render (see `docs/engineering-conventions.md`).
- **Unbounded reads**: lists go through `getPaginated` (`meta->perPage`) — never return a full table. `BaseRequest::getPerPage` is the single bound: `-1` means the max, anything else outside `[1, max]` is the default — a negative size must never reach the builder, which drops a negative LIMIT.
- **Rate limit**: public/auth-input endpoints under `sensitive` or stricter.
- **Route ids**: numeric ids constrained with `->where('<x>Id', config('custom.numeric_regex'))`.
- **Error leakage**: a service `catch (Throwable)` must not surface a raw internal message (DB/SQL, file paths) to the client. Re-throw `BadRequestException`/`ProcessingException` first to preserve the specific user-facing message, then log the real cause server-side and throw a safe generic one (see `UserService::create` and `SpreadsheetService::readRawFileAsWorkSheet`).

These are the "Security is non-negotiable" clause of the CLAUDE.md **Engineering bar**, made concrete.

## Supply-chain scanning (CI)

`.github/workflows/security.yml` runs on every PR into `main`/`staging`/`develop`, weekly (Monday 03:00 UTC), and on-demand — kept separate from the fast test gate (`.github/workflows/test.yml` → `./test-pipeline.sh`) so a slow/noisy scan never blocks the merge signal. Two jobs:
- **`composer-audit`** — `composer validate` + `composer audit` (informational at any severity, **blocks on HIGH/CRITICAL** through `.github/scripts/audit-gate.php`). The script exists because `composer audit` cannot accept a *single* advisory: an all-or-nothing gate meets one unfixable finding and gets deleted. It allows a **documented, dated** exception per advisory, nags when one goes stale or passes its review date, and refuses to read an unparseable report as a clean result.
- **`trivy-fs`** — Trivy filesystem scan (`vuln,secret,misconfig`): vulnerable `composer.lock` deps, `Dockerfile` misconfig, committed secrets. Blocks on HIGH/CRITICAL, `ignore-unfixed`.

## Dynamic scanning (DAST)

`.github/workflows/security-dast.yml` runs OWASP ZAP weekly (Monday 03:00 UTC) and on demand, against a **fully ephemeral** app with its own throwaway PostgreSQL and Redis — never a shared or real environment. It is separate from the merge gate because active rules send real `POST`/`PUT`/`DELETE` and the scan is slow and stateful.

The scan is driven by the generated OpenAPI document (`php artisan scramble:export`), so it exercises every declared operation rather than spidering a headless JSON API and finding only the 401 wall. Three details are load-bearing:

- It signs in as the seeded system admin through the real `POST /api/auth/sign-in` and injects `Authorization: Bearer …` on every request via a ZAP replacer rule, so **33 of the 37 operations behind `auth:api` are actually reached**. Deliberately not a bespoke token-minting artisan command: that would be a permanent privileged surface in every forked project, to save one HTTP call.
- It raises `RATE_LIMIT_*` to effectively unlimited. `sensitive` is 5/min in production and would otherwise stop the scan at the auth wall. This is the one legitimate reason those limits are config-driven.
- `APP_DEBUG` stays `false`, so the scan sees production-shaped error bodies. A debug stack trace would both mask real information disclosure and manufacture findings that do not exist in production.

**The scan is informational until you tune it.** ZAP runs with `-I`, so only a rule explicitly set to `FAIL` in `.zap/rules.tsv` can fail the build. That file is the single tuning seam: triage the report, `IGNORE` what is structurally inapplicable *with a stated reason*, and promote to `FAIL` what must never regress. A scan with no `FAIL` rule is a report, not a gate — and a job that red-lights on untriaged findings gets disabled within a week, at which point it protects nothing.
