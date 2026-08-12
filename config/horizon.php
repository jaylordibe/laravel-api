<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    /*
     * Names a connection in config/database.php's `redis` block, so Horizon
     * inherits the same host, credentials and TLS settings as everything else
     * rather than carrying its own copy that could be left unencrypted.
     */
    'use' => env('HORIZON_REDIS_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    /*
     * LEFT FALSE DELIBERATELY.
     *
     * Fast termination lets `horizon:terminate` return before the workers have
     * actually stopped, so a new instance can start while the old one drains.
     * That is a bet on the OLD process being allowed to finish, which holds on a
     * long-lived VM and does NOT hold in a container: the platform sends SIGTERM,
     * waits out its grace period, then SIGKILLs whatever is left. Returning early
     * just means the kill lands on a worker that is still mid-job.
     *
     * With this false, shutdown blocks until the in-flight jobs finish, which is
     * exactly what the grace period is for. Set the platform's termination grace
     * period comfortably above the longest job timeout below.
     */
    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | EVERY SIZING KNOB IS ENVIRONMENT-DRIVEN. Worker concurrency is a deployment
    | decision, not a source-code one: the same image runs against a small managed
    | database in staging and a much larger one in production, and the right
    | process count differs. Hard-coding it means a fork edits committed config to
    | scale, which is a code change and a release for what should be a variable.
    |
    | maxProcesses is the number of worker PROCESSES this container may run, and
    | each one holds a database connection while it works. The capacity ceiling is
    | therefore shared with the API:
    |
    |   (api replicas x php-fpm workers)
    | + (worker replicas x HORIZON_MAX_PROCESSES)
    | + scheduler invocations
    | + migration jobs
    | < the database's connection limit
    |
    | The base image's php-fpm pool is pm.max_children=20, so ONE api replica can
    | reach 20 connections on its own. The default of 5 processes below is
    | deliberately conservative for a template — small enough that a modest
    | database survives a few replicas of each. Raise it against the arithmetic
    | above and the real connection limit, not by feel. DEPLOYMENT.md works a
    | full example.
    |
    */
    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => explode(',', (string) env('HORIZON_QUEUES', 'default')),
            'balance' => env('HORIZON_BALANCE', 'auto'),
            'autoScalingStrategy' => env('HORIZON_AUTO_SCALING_STRATEGY', 'time'),
            'minProcesses' => (int) env('HORIZON_MIN_PROCESSES', 1),
            'maxProcesses' => (int) env('HORIZON_MAX_PROCESSES', 5),
            'maxTime' => (int) env('HORIZON_MAX_TIME', 0),

            /*
             * Recycle a worker after this many jobs. A non-zero value bounds the
             * blast radius of a slow memory leak in a job or a driver, at the
             * cost of a process restart. 0 disables it.
             */
            'maxJobs' => (int) env('HORIZON_MAX_JOBS', 0),
            'memory' => (int) env('HORIZON_MEMORY', 128),

            /*
             * tries defaults to 3, not 1.
             *
             * With tries=1 a job that hits any transient failure — a Redis
             * blip, a database failover, a rate-limited upstream — is dead on
             * first contact and lands in failed_jobs. On a managed platform those
             * blips are routine, not exceptional.
             *
             * Retries are only safe if jobs are IDEMPOTENT, because a job can
             * also be retried after it partially succeeded. This template ships
             * no jobs of its own, so this default costs nothing here; a fork
             * adding a job that charges a card or sends an email owns making it
             * safe to run twice. See the background-work skill.
             */
            'tries' => (int) env('HORIZON_TRIES', 3),

            /*
             * Must stay BELOW the queue connection's retry_after
             * (REDIS_QUEUE_RETRY_AFTER, default 90s in config/queue.php). If
             * timeout exceeds retry_after, the queue releases the job for another
             * worker while the first one is still running it, and the job
             * executes twice concurrently.
             */
            'timeout' => (int) env('HORIZON_TIMEOUT', 60),
            'nice' => 0,
        ],
    ],

    /*
     * Per-environment overrides for the autoscaler's responsiveness only. The
     * process counts themselves come from the environment in every environment,
     * so there is exactly one place to change them.
     */
    'environments' => [
        'production' => [
            'supervisor-1' => [
                'balanceMaxShift' => (int) env('HORIZON_BALANCE_MAX_SHIFT', 1),
                'balanceCooldown' => (int) env('HORIZON_BALANCE_COOLDOWN', 3),
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'maxProcesses' => (int) env('HORIZON_MAX_PROCESSES', 3),
            ],
        ],

        /*
         * The test suite never runs a Horizon master, but Horizon still validates
         * that the current environment has a configuration block. Without this,
         * any artisan command that boots Horizon under APP_ENV=testing fails.
         */
        'testing' => [
            'supervisor-1' => [
                'maxProcesses' => 1,
            ],
        ],
    ],
];
