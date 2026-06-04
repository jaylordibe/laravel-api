---
name: laravel-background-work
description: Use when adding or editing a queued job (app/Jobs) or a console/scheduled command (app/Console/Commands + routes/console.php) — the Redis/Horizon queue, the Trackable job-status pattern with progress reporting, $tries, scheduling via the Schedule facade, the convention that commands resolve dependencies from the container and delegate to repositories/services, and how to run the queue/scheduler in the Docker container.
---

# Background work (queued jobs & scheduled commands)

Queues run on **Redis** and are processed by **Laravel Horizon** (`QUEUE_CONNECTION=redis`). `imtigger/laravel-job-status` is installed for DB-backed, frontend-pollable job status (see `JobStatusController`). Heavy work (imports, bulk generation, exports) belongs in a job, not the request lifecycle.

## Queued jobs (`app/Jobs`)

Pattern:
- `implements ShouldQueue`, `use Queueable, Trackable;` (`Imtigger\LaravelJobStatus\Trackable`).
- Set retries with `public int $tries = 2;`.
- In the constructor: store inputs, then `$this->prepareStatus();` and `$this->setInput($payload);`.
- In `handle()`: report progress with `$this->setProgressMax(100)` / `$this->setProgressNow(n)` and finish with `$this->setOutput([...])`.
- **Keep domain logic in services/repositories** — the job orchestrates and reports progress; resolve collaborators inside `handle()` from the container. On failure, throw — the failed-job machinery records it.
- Dispatch with `JobClass::dispatch($payload)`.

## Console / scheduled commands (`app/Console/Commands`)

Pattern (see `ResetRolePermissionsCommand`):
- `extends Command`; `protected $signature = 'app:<verb>-<noun>';` (the `app:` prefix matches the convention).
- **Resolve repositories/services via method injection on `handle()`** — `public function handle(SomeRepository $repo): void` — Laravel injects from the container; don't `new` them.
- Delegate persistence to repositories; print progress with `$this->info(...)`.
- Use `AppConstant`/`config('app.timezone')` for date math; don't hardcode timezones.

## Scheduling (`routes/console.php`)

Register recurring commands with the `Schedule` facade:
```php
use App\Console\Commands\SomeCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(SomeCommand::class)->dailyAt('01:00');
```
Pick the cadence to match the business meaning. The scheduler relies on a single `schedule:run` firing every minute.

## Running locally (inside the container)

```
docker exec -it laravel-api bash -c "php artisan horizon"        # process queued jobs
docker exec -it laravel-api bash -c "php artisan schedule:work"  # run the scheduler loop
docker exec -it laravel-api bash -c "php artisan app:<command>"  # run a command directly
```

Test commands/jobs by invoking the public work method directly (with factories + the real test DB) — see `laravel-feature-test`. Don't test cron timing.
