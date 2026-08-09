# Application security standard — project edition

`CLAUDE.md` and the `auth-security` skill are authoritative.

## Authentication

- Laravel Passport (OAuth2) issues tokens; the `api` guard uses the `passport`
  driver and every non-public route sits under `auth:api`.
- Public routes are a small, deliberate block at the top of `routes/api.php`.
  Adding one is a security decision, not a routing convenience.
- Authorisation state is read server-side per request — never trusted from a
  client-supplied field.
- Sign-in, sign-up, password reset, and email verification resist account
  enumeration and observable timing differences.
- Tokens, passwords, OTPs, and secrets are never logged, never returned in a
  Resource, and never included in an exception message.
- Token revocation on password/email change is part of the change, not a
  follow-up.

## Authorization

Every protected operation verifies:

1. an authenticated actor;
2. the permission or Gate for the action (Spatie roles/permissions);
3. object visibility — the actor may see *this* record;
4. ownership and any tenant/provider scope;
5. a valid lifecycle state for the transition.

Route middleware proves a policy exists. **The repository query proves record
access.** A permission check that loads the record first and filters afterwards
is a leak waiting for its first cross-account report.

Use 404 for a record the actor may not see, and 400/403 for a visible record
whose action is forbidden — a 403 on an invisible record confirms it exists.

## Input and authority

Never trust client-supplied:

- role, permission, or scope;
- tenant, provider, or owner foreign keys;
- price, amount, total, discount, or entitlement;
- approval or status transitions;
- audit or actor metadata.

Validate shape, semantics, limits, and canonical representation in the Form
Request. `$fillable` stays empty and assignment stays explicit in the repository
— that is the mass-assignment control, and it only works if nobody adds a
`fill()` shortcut.

Recompute authoritative money server-side with `BigDecimal`. A total that
arrives in the request body is an input, never a fact.

## Sensitive sinks

Review inputs reaching:

- Eloquent queries, raw expressions, `whereRaw`, and ordering by a client value;
- remote URLs and the `Http` facade;
- file names, storage paths, and parsers;
- shell execution and dynamic evaluation;
- mail, SMS, and push payloads;
- queued job payloads;
- logs and the activity log;
- API Resources.

Check mass assignment, injection, FK escalation, SSRF, path traversal, unsafe
deserialisation, resource exhaustion, and over-exposed Resource fields.

## Abuse controls

- public, auth, and dispatching routes need explicit rate limiting;
- webhooks need signature, freshness, and replay controls;
- retries and queued jobs need idempotency and duplicate safety;
- expensive operations need bounds and backpressure;
- list endpoints are bounded — never an unpaginated full-table read;
- administrative and security actions are recorded in the activity log.

## Data exposure

- stable, safe error messages; no driver, SQL, or stack detail;
- health and status responses never expose hosts, users, or credentials;
- Resources exclude secrets and internal columns by omission, not by hope;
- log redaction expands whenever a new sensitive field appears;
- activity-log metadata is useful but minimised.

High-risk changes require a threat model and negative authorization tests.
Critical changes require qualified human security review.
