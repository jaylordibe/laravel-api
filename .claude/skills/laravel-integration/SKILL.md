---
name: laravel-integration
description: Use when adding or modifying a third-party integration (file storage, push/FCM, SMS, email, payments, maps, any external HTTP API) — the app/Utils/<Domain>Util boundary class, config-driven credentials (config/custom.php or config/services.php, never env() outside config), the Http facade with timeouts + error mapping, Laravel's config/driver provider-swap seams (filesystem disks, mail mailers, notification channels, queue), calling integrations from the service layer, keeping side effects best-effort/queued, standardizing upstream responses, and faking externals in tests.
---

# Third-party integration pattern

Integrations are wrapped in a **`app/Utils/<Domain>Util.php`** boundary class — a plain static class, no shared base (like every other Util). The rest of the app never talks to a vendor SDK or a raw URL directly; it calls the Util. Trace the two reference integrations before adding one:

- **`FileUtil`** — object storage over `Storage::disk('public')` (upload, presigned/temporary URLs, download, zip). The vendor (local disk vs S3 vs GCS) is entirely config-driven.
- **`PushNotificationUtil`** — Firebase Cloud Messaging via `Google\Client` (service-account auth) + the `Http` facade. Credentials come from `config('custom.firebase_*')`.

`SmsUtil` and `EmailUtil` are intentionally **empty stubs** — fill them following this pattern when a fork needs SMS/transactional email.

## Where things go

```
app/Utils/<Domain>Util.php     the integration boundary — static methods, config-driven creds, Http calls
config/custom.php              custom third-party keys (env-derived), read via config('custom.<key>')
config/services.php            keys for Laravel-recognized vendors (ses, mailgun, resend, slack, postmark …)
config/filesystems.php         storage disks — the swap seam for FileUtil
```

Integrations are called **from the Service layer** (business rules orchestrate side effects — e.g. `UserService::updateProfileImage` calls `FileUtil::upload`/`getUrl`). Controllers and repositories never call a Util directly.

## Credentials & config (never `env()` outside config)

- Add every new env-derived key to **`config/custom.php`** (or `config/services.php` for a Laravel-recognized driver) and read it with `config('custom.<key>')`. **Never call `env()` outside a `config/` file** — `config:cache` (run in the Docker build) freezes config and `env()` returns `null` afterward. `PushNotificationUtil` reads `config('custom.firebase_project_id')`, never `env(...)`.
- Secrets live only in `.env` → surfaced through config. Never hardcode a key, never log one, never return one in a Resource.

## The HTTP boundary

Use Laravel's **`Http` facade** (Guzzle wrapper), not raw Guzzle:

```php
$response = Http::withToken($accessToken)
    ->asJson()
    ->timeout(10)        // ALWAYS bound the call — a hung vendor must not hang the request
    ->retry(2, 200)      // optional: retry transient failures with backoff
    ->post($url, $payload);
```

- **Every upstream call sets a `timeout()`.** (The FCM sender is deliberately fire-and-forget/best-effort; anything a request *waits on* must be bounded.)
- Handle failure at the boundary: check `$response->failed()` / `throw` and convert to the app's uniform error. From a service, throw `App\Exceptions\BadRequestException` (400) for a caller-fixable problem or `App\Exceptions\ProcessingException` (422) for a downstream/processing failure — and **never leak the raw upstream body/message** to the client. Log the real cause server-side and throw a safe message (same rule as `UserService::create` / `SpreadsheetService`). `Http::withOptions(...)` throwing `ConnectionException` should be caught and re-thrown as one of ours.

## Provider swap — the Laravel way (config/driver, not a DI token)

Laravel's swap seam is the **driver/config**, not a hand-rolled interface token. Prefer a first-class seam over a custom one:

- **Storage** — `config/filesystems.php` disks (`local`, `public`, `s3`, `gcs`). `FileUtil` goes through `Storage::disk('public')`, so pointing the `public` disk's `driver` at `s3`/`gcs` swaps the backend with **zero code change** to `FileUtil` or its callers.
- **Mail** — `config/mail.php` mailers (ses/mailgun/resend/postmark, keys in `config/services.php`). Send via `Mail::to(...)->send(new XMail(...))`.
- **Notifications** — channels (mail/database/broadcast/custom) via `Notifiable` + `Notification` classes.
- **Queue** — `config/queue.php` connections (Redis/Horizon here).

When no first-class driver fits (a bespoke vendor API like FCM), the `*Util` class **is** the seam: keep its public methods vendor-agnostic so swapping the vendor is an edit inside the Util, invisible to every caller.

## Response standardization

The Util maps the upstream shape → our shape **at the boundary**. Callers (services, Resources, the frontend) never see vendor JSON — so field naming stays camelCase, units/dates are normalized once, and a vendor swap is invisible. Return typed values/arrays, not the raw `$response->json()`.

## Resilience & side effects

- Outbound side effects (push, SMS, email, webhooks) are **best-effort** — a failed notification must not fail the primary action. Dispatch them **after commit** and prefer a **queued Job** (Redis/Horizon) so the request returns immediately and retries are automatic — see the `laravel-background-work` skill. Wrap the send in try/catch + `Log::error(...)` when kept inline.
- Authorize/validate **before** spending a paid quota (don't let unauthenticated or invalid traffic reach a metered vendor). Integration-triggering endpoints stay under `auth:api`.

## Testing — never hit a real vendor

Fake the externals; the harness (`laravel-feature-test`) hits a live MySQL DB but must not reach the network:
- `Http::fake([...])` — stub upstream responses, assert with `Http::assertSent(...)`.
- `Storage::fake('public')` — in-memory disk for `FileUtil`; assert with `Storage::disk('public')->assertExists(...)`.
- `Notification::fake()` / `Mail::fake()` — already used (`UserFeatureTest` fakes notifications on sign-up); assert `assertSentTo` / `assertSent`.
- `Bus::fake()` / `Queue::fake()` — assert a side-effect Job was dispatched without running it.

A test that would otherwise call a vendor is a bug — fake it, and assert the outgoing request/payload instead.
