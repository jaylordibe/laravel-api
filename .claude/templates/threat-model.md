# Threat model: [Change/system]

- **Related plan/ticket:**
- **Risk:** High | Critical
- **Owner:**
- **Status:** Draft | Reviewed | Accepted

## Scope and security objectives

- In scope:
- Out of scope:
- Confidentiality:
- Integrity:
- Availability:
- Authorization:
- Ownership isolation:
- Privacy:
- Auditability:

## Assets and actors

| Asset | Sensitivity | Owner |
|---|---|---|
| | | |

| Actor | Role/permissions (Spatie) | Ownership/privilege | Prohibited action |
|---|---|---|---|
| | | | |

## Entry points and trust boundaries

| Route/job/webhook | Middleware / permission | Input | Sensitive sink |
|---|---|---|---|
| | `auth:api` / public | | |

## Abuse cases

| ID | Abuse case | Preconditions | Impact | Existing control |
|---|---|---|---|---|
| TM-01 | Another actor's record accessed | | | |
| TM-02 | Role/permission or FK escalation | | | |
| TM-03 | Price/amount/entitlement tampering | | | |
| TM-04 | Enumeration/timing leak | | | |
| TM-05 | Replay/duplicate/race | | | |
| TM-06 | Sensitive data in log, error, or Resource | | | |
| TM-07 | Public or auth endpoint abuse | | | |
| TM-08 | Mass assignment via a widened `$fillable` or `fill()` | | | |

Add upload, SSRF, webhook, parser, queue, and provider cases where relevant.

## Required controls

| Threat | Control | Prevention/detection/recovery | Code owner | Test |
|---|---|---|---|---|
| | Ownership predicate inside the repository query | | | |
| | Uniform `BadRequestException` response | | | |
| | Route rate limiter | | | |
| | Server-side `BigDecimal` recomputation | | | |
| | Idempotency/duplicate protection | | | |
| | Log redaction / activity-log entry | | | |
| | Explicit assignment in the repository, `$fillable` empty | | | |

## Security test plan

| Scenario | Expected status/message | Data/audit expectation |
|---|---|---|
| unauthenticated | 401 | |
| wrong role or permission | | |
| another actor's record | 404 | |
| forbidden action on a visible record | 400/403 | |
| tampered FK/price/owner | | |
| duplicate/replay | | |
| rate limit | 429 | |
| sensitive error path | | |

## Residual risk

| Risk | Reason retained | Compensating control | Human owner | Review date |
|---|---|---|---|---|
| | | | | |
