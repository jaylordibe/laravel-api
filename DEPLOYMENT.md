# Deployment Guide

**This template ships no configured deployment target.** It ships a *runtime
contract*: a set of generic capabilities the application needs, and the exact
commands that consume them. Any platform that can supply those capabilities can
run this image unchanged.

The application depends on **capabilities**, never on providers:

```
HTTP runtime · SQL database · Redis-compatible backend · object storage
runtime secrets · queue workers · scheduler invocation · logging · health checks
```

Mapping those to a specific cloud is **infrastructure's job, not the
application's**. There is no provider SDK, no provider-specific configuration and
no provider branch anywhere in `app/`.

---

## 1. Runtime commands

The image has **one build** and selects its runtime with `APP_RUNTIME_MODE`
(or by overriding the command). `docker/entrypoint.sh` owns the dispatch.

| Runtime | `APP_RUNTIME_MODE` | Process | Lifetime |
|---|---|---|---|
| **API** | `api` *(default)* | nginx + php-fpm | long-running |
| **Worker** | `worker` | `php artisan horizon` | long-running |
| **Migration** | `migrate` | `php artisan migrate --force` | one-shot, exits |
| **Scheduler** | `scheduler` | `php artisan schedule:run` | one-shot, exits |
| **Ad-hoc** | `artisan` | `php artisan <args>` | one-shot, exits |
| **All-in-one** | `all` | nginx + php-fpm + Horizon | long-running, **1 replica only** |

```bash
# API
docker run -e APP_RUNTIME_MODE=api      <image>
# Worker
docker run -e APP_RUNTIME_MODE=worker   <image>
# Migration
docker run -e APP_RUNTIME_MODE=migrate  <image>
# Scheduler (invoke once per minute from your scheduler of choice)
docker run -e APP_RUNTIME_MODE=scheduler <image>
# Ad-hoc, e.g. a one-off maintenance command
docker run -e APP_RUNTIME_MODE=artisan  <image> <any artisan command>
```

### Why the API and the worker are separate

The base image starts nginx, php-fpm **and** Horizon from a single supervisord
configuration. That is right for one development container and wrong for anything
that scales, so `api` mode explicitly disables the Horizon supervisor program.

Without that split, **every API replica also runs a Horizon master**: queue
concurrency becomes a function of web traffic, database connections multiply with
web autoscaling, and a deploy's `horizon:terminate` races across replicas. None of
it produces an error — it just quietly stops being a queue you can reason about.

`all` mode is the deliberate exception, for a single-box Docker Compose or VM
deployment. **Never scale it past one replica.**

### Why the scheduler is a short-lived command

`schedule:run` runs whatever is due right now and exits. That is what makes it
provider-neutral: anything able to run a command on a one-minute cadence drives
it unchanged.

`schedule:work` — the long-running loop — is **not used**, and must not be added
to the API container: it would tie the scheduler's lifetime to web autoscaling and
run every task once per replica.

> **If more than one thing can invoke the scheduler**, tasks need
> `withoutOverlapping()` and `onOneServer()`. Both take a **cache lock**, so both
> are only as shared as `CACHE_STORE`. With `CACHE_STORE=file` each container
> locks against itself and neither guard does anything — silently.
> `app:check-config` fails production for exactly this reason.

### Migrations are independent

`migrate` is its own runtime and is **never** wired into API, worker or scheduler
startup. Running migrations from a container entrypoint means they run once per
replica, concurrently, during a scale-up.

Run them **before** the new build starts serving. The old build then runs against
the new schema for the length of the swap, so every migration must be
backwards-compatible — expand → migrate → contract, across two releases. The
`migration-safety` job in `.github/workflows/test.yml` proves that window is
survivable, on a seeded database.

---

## 2. Container contract

| Property | Value |
|---|---|
| **Default port** | **80** |
| **`$PORT`** | Honoured. `PORT` (or `APP_HTTP_PORT`) rewrites the nginx listener at startup. |
| **Bind host** | `0.0.0.0` (and `[::]`) |
| **Health — liveness** | `GET /up` |
| **Health — readiness** | `GET /api/health/ready` |
| **Container healthcheck** | `docker/healthcheck.sh`, mode-aware |
| **Logs** | stdout/stderr |
| **Shutdown** | SIGTERM to PID 1; graceful |
| **User** | nginx master + supervisord run as root to bind the port; **all request handling, Horizon and every one-shot command run as `www-data`** |

**The port is 80, not 8080.** 8080 is one cloud's convention, not a contract —
forcing it would silently change the port for every deployment that does not use
that cloud. If your platform injects `PORT`, it is honoured automatically. If
`PORT=8080`, the php-fpm diagnostic listener (normally `127.0.0.1:8080`) moves to
8081 so it cannot collide; the healthcheck follows it via `/run/app-runtime.env`.

**Shutdown.** `api`/`all` run supervisord as PID 1, which stops nginx and php-fpm
with `QUIT` (graceful). `worker` runs Horizon **as PID 1**, so SIGTERM reaches it
directly: it stops accepting jobs, finishes the in-flight ones and exits.
`horizon.fast_termination` is `false` deliberately, so shutdown blocks until
in-flight jobs finish.

**Set the termination grace period to at least `HORIZON_TIMEOUT + 15s` — 75s with
the shipped default of 60.** Both common defaults are too low: Kubernetes uses
`terminationGracePeriodSeconds: 30` and `docker stop` uses 10s. If SIGKILL lands
first the job is neither completed nor failed; it stays reserved until
`retry_after` (90s) and then runs a second time, which with `HORIZON_TRIES=3` is a
silent duplicate execution rather than a visible failure.

Do **not** try to drain a worker with `horizon:terminate` from a separate
container. It matches masters by hostname and signals by PID, so from another
container it finds nothing, prints "No processes to terminate." and exits 0 —
looking like a successful drain. The drain path is SIGTERM to Horizon as PID 1,
which is what the platform already does when it replaces the container.

**Filesystem.** Nothing durable may live in the container. `storage/` is scratch
only; uploads belong on an object-store disk and logs go to the stream.

---

## 3. Configuration is resolved at RUNTIME

`php artisan config:cache` evaluates every `env()` call and writes the **result**;
Laravel then loads that file and stops reading `.env` and `config/*.php`
altogether.

Running it during `docker build` therefore freezes **build-time** values — and
since `.dockerignore` excludes `.env`, those are just the defaults in
`config/*.php`. The image would ship `DB_HOST=127.0.0.1` baked in and **silently
ignore** every value the platform injects.

So the build caches **only** compiled Blade views, and `docker/entrypoint.sh`
caches config and routes **at container start**, from the environment the
container actually received. The `docker` job in `.github/workflows/test.yml`
asserts both halves: that no config cache exists in the image, and that a
`docker run -e DB_HOST=…` value is the one the application resolves.

Set `APP_RUNTIME_CONFIG_CACHE=false` only for a read-only root filesystem — slower, still
correct.

---

## 4. Environment contract

### 4a. Generic configuration (not secret)

| Variable | Purpose |
|---|---|
| `APP_NAME`, `APP_ENV=production`, `APP_URL` | Identity; `APP_URL` backs generated links |
| `APP_DEBUG=false` | **Must** be false — startup fails otherwise |
| `APP_RUNTIME_MODE` | `api` \| `worker` \| `scheduler` \| `migrate` \| `artisan` \| `all` |
| `PORT` | HTTP port; defaults to 80 |
| `TRUSTED_PROXIES` | See below — **required behind a load balancer** |
| `LOG_CHANNEL=stderr`, `LOG_LEVEL` | Container-native logging |
| `CACHE_STORE=redis` | **Must not** be `file` when scaled |
| `QUEUE_CONNECTION=redis` | Horizon only manages Redis queues |
| `SESSION_DRIVER` | `redis` if anything uses sessions |
| `FILESYSTEM_DISK` | `local` \| `s3` \| `gcs` |
| `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_SSLMODE` | Database |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_TLS_ENABLED` | Redis |
| `HORIZON_MAX_PROCESSES`, `HORIZON_TRIES`, `HORIZON_TIMEOUT` | Worker sizing |
| `HORIZON_DASHBOARD_EMAILS` | Dashboard allowlist; **empty denies everyone** |
| `HEALTH_DB_TIMEOUT_MS` | Readiness database probe ceiling (default 3000) |
| `APP_RUNTIME_CONFIG_CACHE`, `APP_CONFIG_CHECK` | Startup behaviour escape hatches |

### 4b. Runtime secrets — injected, never baked

| Secret | Notes |
|---|---|
| `APP_KEY` | Generate **once** per environment. **Never** generate at container start. |
| `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` | PEM content. Generate **once** per environment. Multi-line values work as-is; `\n`-escaped values are un-escaped by Passport itself. |
| `DB_USERNAME`, `DB_PASSWORD` | Or an ambient identity, if the database supports it |
| `REDIS_USERNAME`, `REDIS_PASSWORD` | If the endpoint is authenticated |
| `MAILGUN_SECRET` / `MAIL_PASSWORD` | Mail transport |
| `SYSAD_*`, `APPAD_*` | Seeded admin accounts |

The application reads **plain environment variables**. It has no dependency on
any secret-manager SDK, which is what lets any platform's secret injection work
without an application change. Every secret is redacted from logs by
`App\Logging\RedactSensitiveData`.

> **`APP_KEY` and the Passport keys must be STABLE across replicas and
> deployments.** Generating them at startup gives each replica a different key:
> tokens minted by one replica fail on the next (an intermittent 401 that
> load-balances), and a redeploy signs every user out. The template never
> generates them outside `start.sh`, which is local-only.
>
> Multi-line PEM values work directly; `\n`-escaped values are un-escaped by
> Passport itself, so no special handling is needed.

### 4c. Optional, storage-provider-specific

Only the block for the disk you select is needed; all are optional.

| Disk | Variables | Production auth |
|---|---|---|
| `s3` | `AWS_BUCKET`, `AWS_DEFAULT_REGION`, optional `AWS_ENDPOINT` + `AWS_USE_PATH_STYLE_ENDPOINT` | **Leave `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY` unset** → SDK default credential chain (attached role) |
| `gcs` | `GOOGLE_CLOUD_STORAGE_BUCKET`, `GOOGLE_CLOUD_PROJECT_ID` | **Leave `GOOGLE_CLOUD_KEY_FILE` unset** → Application Default Credentials |
| `local` | — | Ephemeral; scratch only |

---

## 5. Database contract

**Engine: PostgreSQL.** The application needs a reachable PostgreSQL endpoint and
nothing else — it does not know or care whether that is a managed service, a
container or a process on a VM.

| Requirement | Detail |
|---|---|
| Engine | PostgreSQL (see `config/database.php`) |
| Connection | `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, or a single `DB_URL` DSN |
| Encoding | UTF-8 (`DB_CHARSET=utf8`) |
| TLS | `DB_SSLMODE` defaults to **`require`**. Use **`verify-full`** with `DB_SSL_ROOT_CERT` for any database reached over a network you do not control. |
| Timeouts | `DB_CONNECT_TIMEOUT` (default 5s) |
| Pooling | `DB_PERSISTENT=false` — leave it off |
| Privileges | DDL only for the `migrate` runtime; DML is enough for API and worker |

**`prefer` and `allow` are not acceptable production values.** They negotiate TLS
opportunistically and fall back to plaintext with nothing logged, so the
connection can be downgraded invisibly. `app:check-config` fails on them.

### Connection capacity — do this arithmetic before you scale

Every runtime holds connections, and they share one server-side limit:

```
  (api replicas      × php-fpm workers)      ← pm.max_children = 20 in the base image
+ (worker replicas   × HORIZON_MAX_PROCESSES)
+ (concurrent scheduler invocations)
+ (migration job)
< the database's max_connections (minus its own reserved superuser slots)
```

Worked example — 3 API replicas, 2 worker replicas, defaults:

```
3 × 20  = 60   API
2 ×  5  = 10   workers
        +  1   scheduler
        +  1   migration
        = 72 connections at steady state
```

During a rolling deploy add the surge replicas, and remember the migration job
runs while both the old and new replica sets are up. With `maxSurge=1` the same
example peaks at `4 x 20 + 10 + 1 + 1 = 92`, not 72.

72 steady / 92 peak already exceeds the low end of the ~50–100 limit typical of a
small managed instance. Your
levers, in order of preference: lower php-fpm `pm.max_children` (it is the
dominant term), lower `HORIZON_MAX_PROCESSES`, put a connection pooler in front,
or raise the database's limit.

The defaults here are deliberately conservative for a template. **Do not raise
`HORIZON_MAX_PROCESSES` without redoing this sum.**

---

## 6. Redis contract

Any Redis-compatible endpoint. One generic variable set serves all of them; there
are no provider-named variables.

| Variable | Default | Purpose |
|---|---|---|
| `REDIS_CLIENT` | `phpredis` | Extension is present in the base image |
| `REDIS_HOST` / `REDIS_PORT` | `127.0.0.1` / `6379` | Endpoint |
| `REDIS_URL` | — | Full DSN; wins over the discrete values |
| `REDIS_USERNAME` / `REDIS_PASSWORD` | — | ACL / AUTH |
| `REDIS_TLS_ENABLED` | `false` | Switches the scheme to `tls://` |
| `REDIS_TLS_CA` | — | PEM CA bundle for a private CA |
| `REDIS_TLS_CERT` / `REDIS_TLS_KEY` | — | Mutual TLS, if required |
| `REDIS_TIMEOUT` / `REDIS_READ_TIMEOUT` | `5` / `10` | Bounded, so a hung Redis cannot park a worker |
| `REDIS_DB` / `REDIS_CACHE_DB` | `0` / `1` | Separate logical databases |

**Peer verification is always on and cannot be disabled.** There is no
`REDIS_TLS_INSECURE`. TLS to an unverified peer defends only against a passive
listener — and the reason to run Redis over TLS at all is that the network is not
trusted. `REDIS_TLS_CA` is the supported answer to a private CA.

Redis is used by the cache, the queue, Horizon, atomic locks and rate limiting.
All of them resolve through the same connection definition, so transport security
cannot drift between them.

---

## 7. Object storage contract

Everything goes through Laravel's `Storage` abstraction. No application code
imports a provider SDK, so switching provider is an environment variable.

| Disk | Driver | Status |
|---|---|---|
| `local` | local filesystem | Default; ephemeral; scratch only |
| `public` | local filesystem | **Deliberately public** — for genuinely public assets, never uploads |
| `s3` | S3 and S3-compatible | Production-ready |
| `gcs` | Google Cloud Storage | Production-ready |
| Azure Blob | — | Supported by the abstraction; requires `league/flysystem-azure-blob-storage` and a `Storage::extend` registration. Not stubbed, because a disk entry for an uninstalled driver fails confusingly at resolve time. |

**Objects are private on every disk except `public`.** The application never hands
out a permanent link: `FileUtil` issues **short-lived signed URLs**
(`APP_STORAGE_TEMPORARY_URL_TTL`, default 15 minutes) after the caller has been
authorized by the normal route middleware.

`Storage::url()` returning a string is **not** evidence an object is reachable —
it composes a URL from configuration without asking the backend anything. Use
`FileUtil::generatePublicUrl()`.

**GCS + Application Default Credentials has a signing cost.** With no key file
the client holds no private key, so every signed URL is an HTTPS round trip to the
IAM credentials API — one per object. `FileUtil` memoises per request, which
removes repeats, but a page of 100 DISTINCT objects is still 100 calls. If you
serve signed URLs in list responses on GCS, either supply a key file, use an
impersonated service-account signer, or omit the URL from collection responses and
mint it on the detail endpoint. S3 is unaffected: presigning is a local HMAC.

**The local disk's `serve` flag registers two routes** — `GET /storage/{path}` and
`PUT /storage/{path}`, both signature-gated by `APP_KEY`. They sit outside
`api_path`, so `scramble:export` does not document them and the ZAP scan does not
reach them. Set `FILESYSTEM_LOCAL_SERVE=false` to remove them if you never use the
local disk for served objects.

**Prefer workload identity.** Leave the static credentials unset and each SDK
falls back to its ambient chain — an attached role or managed identity, with no
long-lived secret to distribute or rotate. Static keys remain supported for local
development and for S3-compatible storage with no identity story.

---

## 8. Logging

Production logs to **stdout/stderr** (`LOG_CHANNEL=stderr`). File channels remain
for local development, where tailing a file is genuinely easier.

Set `LOG_STDERR_FORMATTER=Monolog\Formatter\JsonFormatter` for structured lines.

`App\Logging\RedactSensitiveData` is applied to every channel and strips
passwords, tokens, bearer headers, DSN credentials, signed-URL signatures and PEM
private-key blocks from messages and context — including nested context, which is
where exception and failed-job payloads hide.

---

## 9. Health checks

| Probe | Path | Semantics |
|---|---|---|
| **Liveness** | `GET /up` | Is the process wedged? Cheap; **no dependency checks**. |
| **Readiness** | `GET /api/health/ready` | Can this replica serve? Verifies database + Redis. `200` / `503`. |
| **Worker** | `docker/healthcheck.sh` (`horizon:status`) | Container-level only. |

**Do not point a liveness probe at readiness.** Failing liveness *kills and
restarts* the container, so a brief database blip would restart every replica at
once — turning a recoverable dependency problem into a self-inflicted outage.
Failing readiness only removes the replica from the load balancer, and it returns
by itself when the dependency does.

**Worker health is deliberately not part of API readiness.** Whether Horizon is
draining a queue says nothing about whether this API replica can answer, and
folding it in would pull the whole API out of rotation over a queue problem.

Readiness is public (a load balancer cannot present a token) and returns only a
boolean per dependency — no error text, no hostnames, no versions.

**It is deliberately NOT rate-limited.** Laravel's limiter resolves through the
default cache store, which production requires to be Redis — so a throttle in
front of this endpoint would throw during the exact Redis outage the probe exists
to report, turning the documented `503` into an undiagnosable `500`. What bounds
it instead is time: the database probe runs under a transaction-local
`statement_timeout` (`HEALTH_DB_TIMEOUT_MS`, default 3000) and the Redis probe
under the connection's read timeout, so a slow dependency cannot park php-fpm
workers until the pool is exhausted. If your platform exposes the path to the
internet, restrict it at the edge.

---

## 10. Reverse proxies

`TRUSTED_PROXIES` is **empty by default**, so no proxy is trusted and
`X-Forwarded-*` is ignored.

Behind a load balancer this **must** be set, or Laravel generates `http://` links
for an `https://` site and records every client IP as the balancer's — which also
collapses per-IP rate limiting into a single bucket.

```env
TRUSTED_PROXIES=10.0.1.0/24,192.168.1.7   # enumerate where you can
TRUSTED_PROXIES=*                          # managed platforms, where nothing else can reach the container
```

**`X-Forwarded-Host` is never trusted**, whatever you set. Trusting it lets a
client choose the host Laravel believes it is serving, which poisons every
absolute URL the application generates — password-reset and email-verification
links, and signed URLs, all pointing at an attacker's host while carrying a valid
signature. The base image's nginx does not forward that header either.

The value is read by Laravel's own `TrustProxies` middleware from
`config/trustedproxy.php`, which is the framework's documented hook; the trusted
HEADER set is pinned in `bootstrap/app.php`.

---

## 11. Startup validation

`app:check-config` runs automatically before the `api` and `worker` runtimes start
(`APP_CONFIG_CHECK=false` disables it), against the **effective** configuration.

**Fails startup:** missing `APP_KEY`; `APP_DEBUG=true` in production; missing
database settings; a downgrading `DB_SSLMODE`; `CACHE_STORE=file` in production;
Redis TLS with verification disabled or a missing CA file; a storage disk missing
its required settings or set to public visibility; missing Passport keys; a
Horizon timeout that meets or exceeds the queue's `retry_after`.

**Warns:** file-based Passport keys; file log channel; `SESSION_DRIVER=file`;
`TRUSTED_PROXIES` empty; `DB_SSLMODE=require` without full verification; a local
storage disk in production.

The one-shot runtimes deliberately skip the check, so an operator can still run
commands against a deployment whose configuration is the thing being diagnosed.

---

## 12. Example provider mappings

**Examples, not requirements.** No provider is mandatory, and none of these needs
an application change.

| Capability | Example A | Example B | Example C | Self-hosted |
|---|---|---|---|---|
| API runtime | managed container service | container task service | container apps | Docker / Kubernetes |
| Worker | same image, `APP_RUNTIME_MODE=worker` | same | same | same |
| Migration | one-off job, `migrate` | one-off task | job | `docker run` |
| Scheduler | managed scheduler → `scheduler` | scheduled task | scheduler | cron / CronJob |
| Database | managed PostgreSQL | managed PostgreSQL | managed PostgreSQL | PostgreSQL container |
| Redis | managed Redis-compatible | managed Redis-compatible | managed Redis-compatible | Redis container |
| Storage | object storage (`gcs`) | object storage (`s3`) | blob storage (adapter) | S3-compatible / `local` |
| Secrets | injected env from secret store | injected env from secret store | injected env from secret store | env file / orchestrator secrets |
| Logs | stdout → platform collector | stdout → platform collector | stdout → platform collector | `docker logs` / collector |

---

## 13. Infrastructure-facing contract

The generic inputs any infrastructure definition must supply. **This repository
deliberately contains no Terraform, Pulumi, CDK or Kubernetes manifests** — that
mapping lives outside the application.

```
api image              <registry>/<repo>:<tag>          (built from ./Dockerfile)
api command            APP_RUNTIME_MODE=api             (image default)
api port               80, or the value of PORT
health path            /up            (liveness)
                       /api/health/ready (readiness)

worker enabled         yes
worker command         APP_RUNTIME_MODE=worker
worker sizing          HORIZON_MAX_PROCESSES (default 5) per replica
                       termination grace period > longest job timeout

migration enabled      yes, as an INDEPENDENT one-shot job
migration command      APP_RUNTIME_MODE=migrate
migration ordering     before the new build serves traffic

scheduler enabled      yes, as a one-shot invocation
scheduler command      APP_RUNTIME_MODE=scheduler
scheduler cadence      every 1 minute

database engine        PostgreSQL
database settings      host, port, database, username, password
                       TLS: require (minimum) / verify-full + CA
                       connection capacity: see section 5

redis settings         host, port, optional username/password
                       optional TLS + CA (verification always on)

object storage         one private bucket/container
                       workload identity preferred over static keys

runtime secrets        APP_KEY
                       PASSPORT_PRIVATE_KEY, PASSPORT_PUBLIC_KEY
                       DB_*, REDIS_*, MAIL_*
                       stable across replicas and deployments
```

---

## 14. Wiring up a real deployment

`.github/workflows/deploy-to-production.yml` and `deploy-to-staging.yml` are ready
except for the step that ships. Replace the `Deploy to …` step, then set the
repository variable `DEPLOY_ENABLED=true`.

**Keep the surrounding shape** — each property is load-bearing:

| Property | Why it is there |
|---|---|
| `uses: ./.github/workflows/test.yml` | The deploy gates on the *same* test workflow a PR runs. Restating the job is how a check ends up enforced on the PR but not on the deploy that follows it. |
| `if: vars.DEPLOY_ENABLED == 'true'` | A fresh fork runs CI and skips the deploy, instead of failing on secrets it does not have yet. |
| `concurrency` with `cancel-in-progress: false` | Two pushes in quick succession queue instead of racing; an in-flight deploy is never killed half-done. |
| `environment:` | Scopes secrets to the environment and lets you require reviewer approval — the human gate on production. |
| `timeout-minutes` | A runaway-job guard. If it trips, find out what got stuck rather than raising the number. |
| Smoke test | Fails the run when the app does not answer, so a broken build cannot sit silently in production. |

A deploy should run, in order: **migrate → start/replace workers → start/replace
API → verify**.

---

## Key files

- **`Dockerfile`** — builds on `jaylordibe/laravel-php:8.5`; installs the runtime
  entrypoint; caches **views only**.
- **`docker/entrypoint.sh`** — the runtime-mode dispatcher, port contract and
  runtime configuration caching.
- **`docker/healthcheck.sh`** — mode-aware container healthcheck.
- **`docker-compose.yml`** — **local development only**; not a deployment artifact.
- **`app/Console/Commands/CheckConfigCommand.php`** — startup validation.
- **`.github/workflows/test.yml`** — format check, test suite, migration safety,
  and the image job that proves runtime env is honoured and that `api` mode does
  not start Horizon.
- **`.github/workflows/security.yml`**, **`security-dast.yml`** — dependency
  advisories, Trivy, OWASP ZAP.

---

## Base image assumption

The runtime is built on **`jaylordibe/laravel-php:8.5`**
([source](https://github.com/jaylordibe/docker-laravel-php)), which is maintained
separately from this repository. This template depends on it for:

- nginx (`user www-data`) + php-fpm 8.5 (`pm.max_children=20`) under supervisord;
- `/entrypoint.sh` rendering nginx real-IP config from `TRUSTED_PROXIES` and
  fixing ownership of `storage/` and `bootstrap/cache`;
- the `pdo_pgsql`, `redis`, `gd`, `intl`, `bcmath` and `zip` extensions.

**Stated assumptions you should verify for your own threat model:** the image is
published `linux/amd64` only; supervisord runs as root in order to bind the port
and to drop its children to `www-data` (accepted as Trivy `DS-0002`, see
`.trivyignore`); and this repository cannot verify the provenance of a base image
it does not build. Pin it by digest if that matters to you.
