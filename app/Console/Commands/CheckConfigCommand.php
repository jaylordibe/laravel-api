<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Validates the EFFECTIVE runtime configuration and fails when something is
 * wrong in a way that would otherwise only surface in production.
 *
 * docker/entrypoint.sh runs this at container start for the `api` and `worker`
 * runtimes, AFTER config caching, so it inspects the configuration the
 * application will actually use rather than the environment it was handed.
 *
 * The point is WHERE the failure happens. Every check below currently fails
 * somewhere useless: a missing APP_KEY fails on the first request that touches
 * the session, a `file` cache in a scaled deployment never fails at all and just
 * quietly loses locks, and Passport keys that were never injected fail on the
 * first sign-in after deploy. A container that refuses to start is a deployment
 * that rolls back on its own.
 *
 * Deliberately NOT run for the one-shot runtimes (migrate, scheduler, artisan),
 * so an operator can still exec into a misconfigured deployment and diagnose it.
 *
 * Nothing here reaches out over the network. It reads configuration only, so it
 * cannot hang a container start behind a dependency that is still coming up —
 * that is the readiness probe's job.
 */
class CheckConfigCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-config {--strict : Treat warnings as errors}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate the effective runtime configuration and fail on unsafe production settings.';

    /**
     * Problems that must fail the command.
     *
     * @var array<int, string>
     */
    private array $errors = [];

    /**
     * Problems worth surfacing that do not, on their own, justify refusing to
     * start.
     *
     * @var array<int, string>
     */
    private array $warnings = [];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $isProduction = app()->environment('production');

        $this->checkApplication($isProduction);
        $this->checkDatabase($isProduction);
        $this->checkRedis();
        $this->checkQueue();
        $this->checkSharedState($isProduction);
        $this->checkFilesystem();
        $this->checkPassport($isProduction);
        $this->checkLogging($isProduction);
        $this->checkProxies($isProduction);

        foreach ($this->warnings as $warning) {
            $this->components->warn($warning);
        }

        foreach ($this->errors as $error) {
            $this->components->error($error);
        }

        if (!empty($this->errors)) {
            return self::FAILURE;
        }

        if ($this->option('strict') && !empty($this->warnings)) {
            $this->components->error('Warnings present and --strict was given.');

            return self::FAILURE;
        }

        $this->components->info('Configuration check passed for environment [' . app()->environment() . '].');

        return self::SUCCESS;
    }

    /**
     * Core application settings.
     *
     * @param bool $isProduction
     *
     * @return void
     */
    private function checkApplication(bool $isProduction): void
    {
        if (empty(config('app.key'))) {
            $this->errors[] = 'APP_KEY is not set. Encryption, signed URLs and sessions cannot work without it.';
        }

        if ($isProduction && config('app.debug')) {
            // Debug mode in production renders stack traces, environment
            // variables and query bindings straight into HTTP responses.
            $this->errors[] = 'APP_DEBUG is true in production. This exposes stack traces and configuration to clients.';
        }

        if (empty(config('app.url')) || config('app.url') === 'http://localhost') {
            $this->warnings[] = 'APP_URL is unset or still http://localhost. Generated links (email verification, signed URLs) will point there.';
        } elseif ($isProduction && !str_starts_with((string) config('app.url'), 'https://')) {
            $this->warnings[] = 'APP_URL is not https in production.';
        }
    }

    /**
     * Database connectivity settings — configuration only, no connection.
     *
     * @param bool $isProduction
     *
     * @return void
     */
    private function checkDatabase(bool $isProduction): void
    {
        $connection = config('database.default');
        $config = config('database.connections.' . $connection);

        if (empty($config)) {
            $this->errors[] = "Database connection [{$connection}] is not defined in config/database.php.";

            return;
        }

        // A DSN in DB_URL supplies host/database/credentials on its own.
        if (!empty($config['url'])) {
            return;
        }

        foreach (['host', 'database', 'username'] as $key) {
            if (empty($config[$key])) {
                $this->errors[] = "Database connection [{$connection}] has no {$key}.";
            }
        }

        if (!$isProduction || $connection !== 'pgsql') {
            return;
        }

        // `disable` sends credentials and every row in the clear. `prefer` and
        // `allow` are worse than they look: they negotiate TLS opportunistically
        // and fall back to plaintext without complaint, so the connection can be
        // downgraded with nothing logged.
        $sslMode = (string) ($config['sslmode'] ?? '');

        if (in_array($sslMode, ['disable', 'allow', 'prefer'], true)) {
            $this->errors[] = "DB_SSLMODE is '{$sslMode}' in production. Use 'require' at minimum, or 'verify-full' with DB_SSL_ROOT_CERT for a database reached over an untrusted network.";
        } elseif ($sslMode === 'require') {
            // `require` encrypts but does not authenticate the server, so it
            // stops passive interception and not an active attacker.
            $this->warnings[] = "DB_SSLMODE is 'require', which encrypts but does not verify the server's certificate. Prefer 'verify-full' with DB_SSL_ROOT_CERT.";
        }

        if (in_array($sslMode, ['verify-ca', 'verify-full'], true) && empty($config['sslrootcert'])) {
            $this->warnings[] = "DB_SSLMODE is '{$sslMode}' but DB_SSL_ROOT_CERT is unset; verification will fall back to the system trust store.";
        }
    }

    /**
     * Redis settings, including the TLS shape when it is enabled.
     *
     * @return void
     */
    private function checkRedis(): void
    {
        $config = config('database.redis.default');

        if (empty($config['url']) && empty($config['host'])) {
            $this->errors[] = 'Redis has neither REDIS_URL nor REDIS_HOST configured.';
        }

        if (($config['scheme'] ?? 'tcp') !== 'tls') {
            return;
        }

        $ssl = $config['context']['ssl'] ?? [];

        // config/database.php hard-codes these true and offers no override, so
        // reaching this branch means something outside the template weakened it.
        if (($ssl['verify_peer'] ?? false) !== true || ($ssl['verify_peer_name'] ?? false) !== true) {
            $this->errors[] = 'Redis TLS is enabled but peer verification is disabled. This makes the TLS useless against an active attacker.';
        }

        $caFile = $ssl['cafile'] ?? null;

        if (!empty($caFile) && !File::exists($caFile)) {
            $this->errors[] = "REDIS_TLS_CA points at '{$caFile}', which does not exist in this container.";
        }
    }

    /**
     * Queue configuration.
     *
     * @return void
     */
    private function checkQueue(): void
    {
        $connection = config('queue.default');

        if (empty(config('queue.connections.' . $connection))) {
            $this->errors[] = "Queue connection [{$connection}] is not defined in config/queue.php.";

            return;
        }

        if ($connection !== 'redis') {
            // Horizon only supervises Redis queues, so any other driver means the
            // worker runtime starts and processes nothing.
            $this->warnings[] = "QUEUE_CONNECTION is [{$connection}], but Horizon (the worker runtime) only manages redis queues.";

            return;
        }

        // A worker whose timeout exceeds the connection's retry_after has the job
        // released to another worker while the first is still running it, so the
        // job executes twice concurrently.
        $retryAfter = (int) config('queue.connections.redis.retry_after');
        $timeout = (int) config('horizon.defaults.supervisor-1.timeout');

        if ($timeout >= $retryAfter) {
            $this->errors[] = "HORIZON_TIMEOUT ({$timeout}s) must be lower than the queue retry_after ({$retryAfter}s), or jobs will be run twice concurrently.";
        }
    }

    /**
     * State that must be SHARED across replicas rather than held per-container.
     *
     * @param bool $isProduction
     *
     * @return void
     */
    private function checkSharedState(bool $isProduction): void
    {
        if (!$isProduction) {
            return;
        }

        $cacheStore = config('cache.default');

        // The failure mode here is silence. A `file` cache in a scaled deployment
        // still "works": every replica keeps its own copy, cache invalidation
        // reaches one container, and Cache::lock() / the scheduler's
        // onOneServer() guard nothing at all because the lock is local.
        if (in_array($cacheStore, ['file', 'array'], true)) {
            $this->errors[] = "CACHE_STORE is '{$cacheStore}' in production. It is per-container, so cache invalidation and atomic locks silently stop working across replicas. Use 'redis'.";
        }

        if (config('session.driver') === 'file') {
            $this->warnings[] = "SESSION_DRIVER is 'file' in production, which is per-container. Harmless for a pure token API; wrong if anything relies on sessions.";
        }
    }

    /**
     * The selected storage disk has the configuration its driver requires.
     *
     * @return void
     */
    private function checkFilesystem(): void
    {
        $diskName = config('custom.storage.disk');
        $disk = config('filesystems.disks.' . $diskName);

        if (empty($disk)) {
            $this->errors[] = "Storage disk [{$diskName}] is not defined in config/filesystems.php.";

            return;
        }

        match ($disk['driver']) {
            's3' => $this->checkS3Disk($diskName, $disk),
            'gcs' => $this->checkGcsDisk($diskName, $disk),
            'local' => $this->checkLocalDisk($diskName),
            default => null,
        };

        // Checked on the RESOLVED visibility, not on the disk's NAME.
        //
        // Skipping by name (`$diskName !== 'public'`) let `APP_STORAGE_DISK=public`
        // send every user upload to a world-readable disk while this check passed
        // — so the code disagreed with DEPLOYMENT.md, which states that startup
        // fails when the selected disk is public. The `public` disk is fine to
        // EXIST; it is not fine to be the one FileUtil writes uploads to.
        if (($disk['visibility'] ?? 'private') === 'public') {
            $this->errors[] = "Storage disk [{$diskName}] is the application's upload disk but has visibility 'public'. Uploaded objects would be readable by anyone with the URL.";
        }
    }

    /**
     * @param string $diskName
     * @param array<string, mixed> $disk
     *
     * @return void
     */
    private function checkS3Disk(string $diskName, array $disk): void
    {
        if (empty($disk['bucket'])) {
            $this->errors[] = "Storage disk [{$diskName}] is an s3 disk with no AWS_BUCKET.";
        }

        if (empty($disk['region']) && empty($disk['endpoint'])) {
            $this->errors[] = "Storage disk [{$diskName}] needs AWS_DEFAULT_REGION or AWS_ENDPOINT.";
        }

        // Not an error: absent credentials are the RECOMMENDED production setup,
        // because the SDK then uses the attached role.
        if (empty($disk['key']) && empty($disk['secret'])) {
            $this->components->info("Storage disk [{$diskName}] has no static credentials; using the ambient AWS credential chain.");
        }
    }

    /**
     * @param string $diskName
     * @param array<string, mixed> $disk
     *
     * @return void
     */
    private function checkGcsDisk(string $diskName, array $disk): void
    {
        if (empty($disk['bucket'])) {
            $this->errors[] = "Storage disk [{$diskName}] is a gcs disk with no GOOGLE_CLOUD_STORAGE_BUCKET.";
        }

        $keyFile = $disk['key_file_path'] ?? null;

        if (empty($keyFile)) {
            $this->components->info("Storage disk [{$diskName}] has no key file; using Application Default Credentials.");

            return;
        }

        if (!File::exists($keyFile)) {
            $this->errors[] = "GOOGLE_CLOUD_KEY_FILE points at '{$keyFile}', which does not exist in this container.";
        }
    }

    /**
     * @param string $diskName
     *
     * @return void
     */
    private function checkLocalDisk(string $diskName): void
    {
        if (app()->environment('production')) {
            // Not an error, because a fork may legitimately store only scratch
            // files. It is a warning because a container filesystem is per-replica
            // and discarded on restart, so anything a user uploaded is gone.
            $this->warnings[] = "Storage disk [{$diskName}] is on the container's own filesystem, which is ephemeral and not shared between replicas. Uploads will not survive a restart.";
        }
    }

    /**
     * Passport signing keys are present and stable.
     *
     * @param bool $isProduction
     *
     * @return void
     */
    private function checkPassport(bool $isProduction): void
    {
        $hasEnvironmentKeys = !empty(config('passport.private_key')) && !empty(config('passport.public_key'));

        if ($hasEnvironmentKeys) {
            if (!str_contains((string) config('passport.private_key'), '-----BEGIN')) {
                $this->errors[] = 'PASSPORT_PRIVATE_KEY does not look like PEM content. It must be the key itself, not a path.';
            }

            return;
        }

        $hasKeyFiles = File::exists(storage_path('oauth-private.key'))
            && File::exists(storage_path('oauth-public.key'));

        if (!$hasKeyFiles) {
            $this->errors[] = 'Passport signing keys are missing. Inject PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY, or provision the key files. No token can be issued or validated without them.';

            return;
        }

        if ($isProduction) {
            // Key FILES in a container are per-replica and per-deploy: a token
            // minted by one replica fails on another, and a redeploy signs
            // everyone out. The files exist, so this is not fatal.
            $this->warnings[] = 'Passport is using key FILES inside the container. Inject PASSPORT_PRIVATE_KEY/PASSPORT_PUBLIC_KEY instead so every replica signs with the same key across deployments.';
        }
    }

    /**
     * Logging goes somewhere a platform can collect from.
     *
     * @param bool $isProduction
     *
     * @return void
     */
    private function checkLogging(bool $isProduction): void
    {
        if (!$isProduction) {
            return;
        }

        $channel = config('logging.default');

        if (in_array($channel, ['single', 'daily'], true)) {
            $this->warnings[] = "LOG_CHANNEL is '{$channel}', which writes into the container's ephemeral filesystem. Use 'stderr' so the platform collects the logs.";
        }
    }

    /**
     * Reverse-proxy trust is configured when it needs to be.
     *
     * @param bool $isProduction
     *
     * @return void
     */
    private function checkProxies(bool $isProduction): void
    {
        if (!$isProduction || !empty(config('trustedproxy.proxies'))) {
            return;
        }

        // Not an error: no trusted proxy is the SAFE state, and a container
        // exposed directly should stay that way. It is a warning because behind a
        // load balancer the symptom is subtle — http:// links generated for an
        // https:// site, and every client IP recorded as the balancer's, which
        // also collapses per-IP rate limiting onto one bucket.
        $this->warnings[] = 'TRUSTED_PROXIES is empty. If this runs behind a load balancer, set it — otherwise generated URLs use the wrong scheme and every request appears to come from the proxy.';
    }

}
