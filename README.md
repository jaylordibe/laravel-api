# LARAVEL API #

This README would normally document whatever steps are necessary to get your application up and running.

### What is this repository for? ###

* Quick summary
    * This repository is the Laravel API.
* Version
    * v1.0

### How do I get set up? ###

* Dependencies
    * [Docker](https://docs.docker.com/get-docker/)
* Summary of set up
    * To start the application with a fresh database or when setting up the project for the first time
    ```
    ./start.sh fresh
    ```
    * To start the application with the current database state
    ```
    ./start.sh
    ```
    * To stop the application
    ```
    ./stop.sh
    ```
* How to run tests
    ```
    ./test.sh
    ```
* Local services
    * API — http://localhost:8000
    * pgAdmin (dev database) — http://localhost:8001, PostgreSQL on host port **5433**
    * pgAdmin (test database) — http://localhost:8002, PostgreSQL on host port **5435**

### Deployment ###

The template is **provider-neutral**: it depends on generic capabilities (HTTP
runtime, PostgreSQL, a Redis-compatible backend, object storage, runtime secrets,
queue workers, scheduler invocation, logging, health checks) and contains no
cloud-provider SDK or configuration. One image serves every runtime, selected with
`APP_RUNTIME_MODE`:

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
