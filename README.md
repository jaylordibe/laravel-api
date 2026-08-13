# LARAVEL API #

A **Laravel 13 / PHP 8.5 API starter template** — the base every new API project
is forked from. It ships a small set of framework resources (`User` with Passport
auth, `AppVersion`, `DeviceToken`, `ActivityLog`, `JobStatus`, `Constant`) and a
strict layered architecture every new resource follows.

### Stack ###

| Concern | Choice |
|---|---|
| Database | **PostgreSQL** (`DB_CONNECTION=pgsql`) |
| Queue / workers | Redis + Laravel Horizon |
| Scheduler | `php artisan schedule:run`, invoked externally |
| Auth | Laravel Passport (OAuth2 bearer tokens) |
| Object storage | Laravel `Storage` — `local`, `s3` (and S3-compatible) or `gcs` |
| API docs | `dedoc/scramble`, generated (`/docs/api`, local only) |

The database engine is PostgreSQL end to end — `config/database.php`,
`docker-compose.yml`, `.env.example`, the test suite and every CI job. A `mysql`
connection is kept configured and hardened for a fork that must run against
MySQL, but that is a fork's decision to own: migrations, tests and CI target
`pgsql`. PostgreSQL may be hosted however you like — a managed service, a
container, Kubernetes, or a process on a VM. No cloud is required.

### How do I get set up? ###

* Dependencies
    * [Docker](https://docs.docker.com/get-docker/)
* Start the application (keeps the current database state)
    ```
    ./start.sh
    ```
* First-time setup, or whenever you want to start from an empty database
    ```
    ./start.sh fresh
    ```
    > **Destructive.** `fresh` drops and re-creates the local database; `reset`
    > also removes the containers and volumes.
* To stop the application
    ```
    ./stop.sh
    ```
* How to run tests
    ```
    ./test.sh                                          # everything
    ./test.sh AppVersionFeatureTest tests/Feature/AppVersionFeatureTest.php   # one file
    ```
* Local services
    * API — http://localhost:8000
    * pgAdmin (dev database) — http://localhost:8001, PostgreSQL on host port **5433**
    * pgAdmin (test database) — http://localhost:8002, PostgreSQL on host port **5435**

### Deployment ###

The template is **provider-neutral**: it depends on generic capabilities (HTTP
runtime, PostgreSQL, a Redis-compatible backend, object storage, runtime secrets,
queue workers, scheduler invocation, logging, health checks), and no required
runtime path depends on a cloud provider.

Provider-specific integrations *do* exist — the `s3` and `gcs` filesystem disks,
and Firebase push notifications — and they are **isolated behind Laravel's
abstractions and this template's `app/Utils/<Domain>Util` boundary**, so swapping
one is configuration rather than a rewrite. Core application logic stays
provider-neutral. `DEPLOYMENT.md` §Provider neutrality lists exactly where each
adapter lives.

One image serves every runtime, selected with `APP_RUNTIME_MODE`:

| Runtime | Command |
|---|---|
| API | `APP_RUNTIME_MODE=api` (default) — nginx + php-fpm, **no Horizon** |
| Worker | `APP_RUNTIME_MODE=worker` — `php artisan horizon` |
| Migration | `APP_RUNTIME_MODE=migrate` — `php artisan migrate --force` |
| Scheduler | `APP_RUNTIME_MODE=scheduler` — `php artisan schedule:run` (one-shot) |

Port **80** by default (`PORT` is honoured); liveness at `/up`, readiness at
`/api/health/ready`. See **[`DEPLOYMENT.md`](DEPLOYMENT.md)** for the full runtime,
database, Redis, storage and secrets contract.

> **Note:** `docker-compose.yml` is local development only. It runs the base image
> directly, so it keeps nginx + php-fpm + Horizon in one container. The production
> image is built from the `Dockerfile`.

### Enabled and ready to use packages ###

1. [Laravel Resource Generator](https://github.com/jaylordibe/laravel-resource-generator)
2. [Laravel Horizon](https://laravel.com/docs/8.x/horizon)
3. [Laravel Snappy](https://github.com/barryvdh/laravel-snappy)
4. [Brick Math](https://github.com/brick/math)
5. [Google APIs Client](https://github.com/googleapis/google-api-php-client)
6. [Laravel Job Status](https://github.com/imTigger/laravel-job-status)
7. [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet)
8. [Laravel Activity Log](https://github.com/spatie/laravel-activitylog)
9. [Laravel Data](https://github.com/spatie/laravel-data)
10. [Laravel Permission](https://github.com/spatie/laravel-permission)

### Working with Claude Code (optional) ###

Contributors using [Claude Code](https://code.claude.com) get a **work-item-to-validated-diff pipeline** from the [`engineering-framework`](https://github.com/jaylordibe/claude-engineering-framework) plugin — `/engineering-framework:work-item <issue key | URL | plain requirement>` drives a change from repository mapping → approval-gated plan → implementation → independent review → read-only validation, stopping only at the plan-approval gate and the commit gate. The five stages are also invocable individually as `/engineering-framework:gate-design`, `gate-approve`, `gate-implement`, `gate-review`, `gate-validate`.

The framework owns the methodology; this repository owns the truth it works from — `CLAUDE.md` plus `.claude/`. Per-machine setup is installing the plugin (`/plugin marketplace add jaylordibe/claude-engineering-framework`, then `/plugin install engineering-framework@jaylordibe`) and, optionally, a one-time issue-tracker login (`/mcp` → authenticate **atlassian**); an issue key is optional, since a pasted requirement works the same way. Run `/engineering-framework:framework-doctor` to check the repository against the framework contract. **Not required to run, test, or deploy the API**, and no CI job depends on it.

### Contribution guidelines ###

* Writing tests
* Code review
* Other guidelines

### Who do I talk to? ###

* Repo owner or admin
* Other community or team contact
