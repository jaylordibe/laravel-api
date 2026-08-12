<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| These run when something invokes `php artisan schedule:run`, which is a
| SHORT-LIVED command: it runs whatever is due at that moment and exits.
|
| That is deliberate, and it is why the scheduler is provider-neutral. Anything
| that can run a command on a one-minute cadence drives it unchanged — a Linux
| cron line, a managed scheduler triggering a container, a Kubernetes CronJob, a
| systemd timer. The alternative, `schedule:work`, is a process that sleeps in a
| loop and must therefore live inside a container that stays up; putting it in the
| API container ties a scheduler to web autoscaling and runs every task once per
| replica. It is not used here.
|
| TWO GUARDS MATTER WHEN MORE THAN ONE THING CAN INVOKE THE SCHEDULER:
|
|   withoutOverlapping()  a run does not start while the previous one is still
|                         going. Guards a slow task against a one-minute cadence.
|
|   onOneServer()         when several containers can each fire schedule:run,
|                         only one executes the task.
|
| BOTH TAKE A CACHE LOCK, so both are only as shared as the cache store. With
| CACHE_STORE=file each container locks against itself and neither guard does
| anything across replicas — with no error and no log line. app:check-config
| fails production for exactly this reason.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/*
 * Horizon's metrics require snapshots; nothing else writes them, so without this
 * the dashboard's throughput and runtime graphs stay permanently empty. This is
 * Horizon's own documented schedule entry, not application work.
 *
 * onOneServer() because the snapshot is global: taking it once per replica
 * multiplies every metric by the replica count.
 *
 * Guarded on the redis queue connection so a fork that switches drivers does not
 * schedule a command that then errors every five minutes. Reading config here is
 * safe — route files are loaded after configuration.
 */
if (config('queue.default') === 'redis') {
    // NO ->runInBackground(). It would deadlock this task for 24 hours.
    //
    // A background event defers releasing its withoutOverlapping mutex to a
    // detached `schedule:finish` subprocess. In the `scheduler` runtime,
    // `schedule:run` IS pid 1 of a one-shot container: it returns immediately,
    // the container exits, the kernel tears the namespace down, and
    // `schedule:finish` never runs. The Redis lock then sits until its default
    // 24-hour expiry, and every subsequent snapshot is skipped — leaving the
    // metrics graphs permanently empty, which is the exact thing this task exists
    // to populate. Run it in the foreground; it is short.
    Schedule::command('horizon:snapshot')
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->onOneServer();
}
