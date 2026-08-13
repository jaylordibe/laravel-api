<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins the configuration decisions that keep this template deployable on any
 * provider — and the security defaults that are easy to relax by accident.
 *
 * These assert on CONFIGURATION rather than behaviour on purpose. Each one
 * describes a setting whose wrong value produces no error, no failing request and
 * no log line, which is exactly the class of problem a test suite otherwise
 * cannot see.
 *
 * The container-level half of this contract — that a runtime-injected DB_HOST is
 * actually honoured by the built image — cannot be proven in-process, because the
 * suite never loads a config cache. It is asserted in the `docker` job of
 * .github/workflows/test.yml against the real image.
 */
class ConfigurationContractFeatureTest extends TestCase
{

    #[Test]
    public function theConfigurationCheckCommandPasses(): void
    {
        // The command that docker/entrypoint.sh runs before starting the api and
        // worker runtimes. If it fails here, a container will refuse to boot.
        self::assertSame(0, Artisan::call('app:check-config'));
    }

    #[Test]
    public function theHorizonDashboardGateDeniesByDefaultAndAllowsOnlyConfiguredEmails(): void
    {
        // Exercises the GATE, not the config value behind it. The dashboard shows
        // failed-job payloads, which carry whatever the job was handed.
        $user = \App\Models\User::factory()->create();

        config()->set('custom.horizon_dashboard_emails', []);
        self::assertTrue(\Illuminate\Support\Facades\Gate::forUser($user)->denies('viewHorizon'));

        config()->set('custom.horizon_dashboard_emails', ['someone-else@example.com']);
        self::assertTrue(\Illuminate\Support\Facades\Gate::forUser($user)->denies('viewHorizon'));

        config()->set('custom.horizon_dashboard_emails', [$user->email]);
        self::assertTrue(\Illuminate\Support\Facades\Gate::forUser($user)->allows('viewHorizon'));
    }

    #[Test]
    public function theCommittedDefaultsAreSafeRegardlessOfTheTestEnvironment(): void
    {
        // Asserts the COMMITTED defaults, not whatever .env.testing happens to
        // set. The previous versions of these read the test environment's value,
        // so they would have stayed green if the shipped default were changed to
        // something unsafe.
        $this->withEnv('DB_CONNECTION', null, function (): void {
            $config = require config_path('database.php');

            self::assertSame('pgsql', $config['default']);
        });

        $this->withEnv('HORIZON_DASHBOARD_EMAILS', null, function (): void {
            $config = require config_path('custom.php');

            self::assertSame([], $config['horizon_dashboard_emails'], 'The dashboard must deny everyone until explicitly configured.');
        });

        $this->withEnv('TRUSTED_PROXIES', null, function (): void {
            $config = require config_path('trustedproxy.php');

            self::assertEmpty($config['proxies'], 'No proxy may be trusted by default.');
        });
    }

    #[Test]
    public function postgresDefaultsToARefusingSslMode(): void
    {
        // `prefer` — libpq's default, and Laravel's stock value — negotiates TLS
        // and SILENTLY falls back to plaintext, so a downgraded connection leaves
        // no trace anywhere. The template's default must refuse instead.
        //
        // The DEFAULT is what matters here, so DB_SSLMODE is unset for the
        // assertion; .env.testing sets `disable` because the local test database
        // is a container on a private network with no TLS configured.
        $this->withEnv('DB_SSLMODE', null, function (): void {
            $config = require config_path('database.php');

            self::assertSame(
                'require',
                $config['connections']['pgsql']['sslmode'],
                'With DB_SSLMODE unset the connection must refuse plaintext, not silently downgrade.'
            );
        });
    }

    #[Test]
    public function databaseConnectionsAreTimeBounded(): void
    {
        // Without a connect timeout an unreachable database blocks every php-fpm
        // worker until the OS gives up, turning a database problem into a total
        // outage.
        $options = config('database.connections.pgsql.options');

        self::assertArrayHasKey(\PDO::ATTR_TIMEOUT, $options);
        self::assertGreaterThan(0, $options[\PDO::ATTR_TIMEOUT]);
    }

    #[Test]
    public function persistentDatabaseConnectionsAreOffByDefault(): void
    {
        // Persistent connections are held per PHP worker process, so the server
        // sees (replicas x workers) connections whether or not they are in use.
        self::assertArrayNotHasKey(\PDO::ATTR_PERSISTENT, config('database.connections.pgsql.options'));
    }

    #[Test]
    public function redisTlsEnforcesPeerVerification(): void
    {
        // TLS without verification stops a passive listener and nothing else,
        // which is not the threat model that justifies running Redis over TLS.
        // Rebuilt with TLS on, since the suite runs against a plaintext Redis.
        $this->withEnv('REDIS_TLS_ENABLED', 'true', function (): void {
            $config = require config_path('database.php');
            $connection = $config['redis']['default'];

            self::assertSame('tls', $connection['scheme']);
            self::assertTrue($connection['context']['ssl']['verify_peer']);
            self::assertTrue($connection['context']['ssl']['verify_peer_name']);
        });
    }

    #[Test]
    public function plainRedisCarriesNoTransportSecuritySettings(): void
    {
        // The local-development shape, and the baseline the cases below differ
        // from. TLS off must leave the connection a plain tcp one: a stray
        // `scheme` or `context` key here would make phpredis attempt a TLS
        // handshake against a plaintext container and fail at connect time.
        $this->withEnvs(['REDIS_TLS_ENABLED' => 'false'], function (): void {
            $connection = $this->rebuildRedisConnection();

            self::assertArrayNotHasKey('scheme', $connection);
            self::assertArrayNotHasKey('context', $connection);
            // Bounded even in the plain case: a hung Redis must surface as a
            // failed request, not as a parked php-fpm worker.
            self::assertGreaterThan(0, $connection['timeout']);
            self::assertGreaterThan(0, $connection['read_timeout']);
        });
    }

    #[Test]
    public function authenticatedRedisPassesBothCredentialsThrough(): void
    {
        // A managed endpoint typically issues an ACL username as well as a
        // password. Dropping the username silently downgrades the connection to
        // the `default` ACL user, which on a properly configured server has no
        // permissions — an authentication failure blamed on the password.
        $this->withEnvs([
            'REDIS_USERNAME' => 'app-user',
            'REDIS_PASSWORD' => 'app-secret',
        ], function (): void {
            $connection = $this->rebuildRedisConnection();

            self::assertSame('app-user', $connection['username']);
            self::assertSame('app-secret', $connection['password']);
        });
    }

    #[Test]
    public function redisTlsWithoutACustomCaVerifiesAgainstTheSystemTrustStore(): void
    {
        // The managed-service case: a publicly trusted certificate and no CA to
        // supply. `cafile` must then be ABSENT rather than present-and-empty —
        // an empty cafile is not "use the system store", it is a path that fails
        // to open, and the connection dies with an opaque SSL error.
        $this->withEnvs(['REDIS_TLS_ENABLED' => 'true', 'REDIS_TLS_CA' => null], function (): void {
            $ssl = $this->rebuildRedisConnection()['context']['ssl'];

            self::assertArrayNotHasKey('cafile', $ssl);
            self::assertTrue($ssl['verify_peer']);
            self::assertTrue($ssl['verify_peer_name']);
        });
    }

    #[Test]
    public function redisTlsAcceptsAPrivateCaAndAClientCertificate(): void
    {
        // The private-CA case is the one people disable verification for. It is
        // supported properly instead: supply the CA, keep verification on.
        // REDIS_TLS_CERT/KEY cover an endpoint requiring mutual TLS.
        $this->withEnvs([
            'REDIS_TLS_ENABLED' => 'true',
            'REDIS_TLS_CA' => '/etc/ssl/certs/private-ca.pem',
            'REDIS_TLS_CERT' => '/etc/ssl/certs/client.pem',
            'REDIS_TLS_KEY' => '/etc/ssl/private/client.key',
        ], function (): void {
            $ssl = $this->rebuildRedisConnection()['context']['ssl'];

            self::assertSame('/etc/ssl/certs/private-ca.pem', $ssl['cafile']);
            self::assertSame('/etc/ssl/certs/client.pem', $ssl['local_cert']);
            self::assertSame('/etc/ssl/private/client.key', $ssl['local_pk']);
            self::assertTrue($ssl['verify_peer']);
        });
    }

    #[Test]
    public function noEnvironmentVariableCanWeakenRedisPeerVerification(): void
    {
        // The insecure escape hatch must not exist. Its usual shape is an
        // environment variable someone sets "just for now" during an incident and
        // nobody ever unsets, so the guarantee has to be that no value of any
        // variable produces verify_peer => false.
        $this->withEnvs([
            'REDIS_TLS_ENABLED' => 'true',
            // Plausible names for the knob this template deliberately lacks.
            'REDIS_TLS_INSECURE' => 'true',
            'REDIS_TLS_VERIFY_PEER' => 'false',
            'REDIS_VERIFY_PEER' => '0',
        ], function (): void {
            $ssl = $this->rebuildRedisConnection()['context']['ssl'];

            self::assertTrue($ssl['verify_peer']);
            self::assertTrue($ssl['verify_peer_name']);
        });

        // And the source carries no such branch, so the assertion above cannot
        // pass merely because the variable names guessed here are the wrong ones.
        $source = file_get_contents(config_path('database.php'));

        self::assertStringNotContainsString("'verify_peer' => false", $source);
        self::assertStringNotContainsString("'verify_peer_name' => false", $source);
    }

    #[Test]
    public function everyRedisConsumerSharesOneConnectionDefinition(): void
    {
        // Cache, queue, Horizon, locks and rate limiting must not be able to
        // drift apart in transport security. Configuring one and forgetting
        // another is the failure the shared factory removes.
        $default = config('database.redis.default');
        $cache = config('database.redis.cache');

        self::assertSame($default['host'], $cache['host']);
        self::assertSame($default['scheme'] ?? 'tcp', $cache['scheme'] ?? 'tcp');
        // Separate logical databases, so `cache:clear` cannot wipe queued jobs.
        self::assertNotSame($default['database'], $cache['database']);

        self::assertSame('default', config('horizon.use'));
        self::assertSame('default', config('queue.connections.redis.connection'));
    }

    #[Test]
    public function theHorizonTimeoutStaysBelowTheQueueRetryWindow(): void
    {
        // If the worker timeout meets or exceeds retry_after, the queue hands the
        // job to a second worker while the first is still running it, and the job
        // executes twice concurrently.
        self::assertLessThan(
            (int) config('queue.connections.redis.retry_after'),
            (int) config('horizon.defaults.supervisor-1.timeout')
        );
    }

    #[Test]
    public function horizonProcessCountsAreEnvironmentDriven(): void
    {
        // Worker concurrency is a deployment decision. Hard-coding it means a
        // fork edits committed config — a code change and a release — to scale.
        self::assertSame(5, (int) config('horizon.defaults.supervisor-1.maxProcesses'));

        $this->withEnv('HORIZON_MAX_PROCESSES', '17', function (): void {
            $config = require config_path('horizon.php');

            self::assertSame(17, $config['defaults']['supervisor-1']['maxProcesses']);
        });
    }

    #[Test]
    public function horizonDoesNotTerminateBeforeItsWorkersDrain(): void
    {
        // Fast termination bets on the old process being allowed to finish. In a
        // container the platform SIGKILLs after the grace period, so returning
        // early just means the kill lands mid-job.
        self::assertFalse(config('horizon.fast_termination'));
    }

    #[Test]
    public function trustedProxiesUseTheFrameworksOwnConfigHook(): void
    {
        // Laravel's TrustProxies middleware falls back to
        // `config('trustedproxy.proxies')` and does the comma-splitting, trimming
        // and '*' handling itself. This asserts the template feeds that hook
        // rather than re-implementing the parsing and pushing the result in from
        // a service provider.
        self::assertTrue(
            file_exists(config_path('trustedproxy.php')),
            'config/trustedproxy.php is the key TrustProxies reads; the name is not arbitrary.'
        );

        $this->withEnv('TRUSTED_PROXIES', '10.0.1.0/24, 192.168.1.7', function (): void {
            $config = require config_path('trustedproxy.php');

            self::assertSame('10.0.1.0/24, 192.168.1.7', $config['proxies']);
        });
    }

    #[Test]
    public function xForwardedHostIsNeverTrusted(): void
    {
        // Trusting it lets a client choose the host Laravel believes it serves,
        // which poisons every generated absolute URL — reset links, verification
        // links and signed URLs — while the signature stays valid.
        $headers = (new \ReflectionClass(\Illuminate\Http\Middleware\TrustProxies::class))
            ->getStaticPropertyValue('alwaysTrustHeaders');

        self::assertIsInt($headers, 'bootstrap/app.php must pin the trusted header set.');
        self::assertSame(0, $headers & Request::HEADER_X_FORWARDED_HOST);
        self::assertSame(0, $headers & Request::HEADER_X_FORWARDED_PREFIX);
        self::assertNotSame(0, $headers & Request::HEADER_X_FORWARDED_PROTO);
    }

    #[Test]
    public function passportKeysAreInjectableFromTheEnvironment(): void
    {
        // Key STABILITY is the deployment requirement: a pair generated per
        // container means a token minted by one replica fails on the next, and a
        // redeploy signs everyone out. So the config must read the environment
        // rather than only the container's filesystem.
        $escaped = '-----BEGIN RSA PRIVATE KEY-----\nMIIEow==\n-----END RSA PRIVATE KEY-----';

        $this->withEnv('PASSPORT_PRIVATE_KEY', $escaped, function () use ($escaped): void {
            $config = require config_path('passport.php');

            self::assertSame($escaped, $config['private_key']);
        });

        // NOTE: the "\n"-escaped form that most secret stores force is un-escaped
        // by PASSPORT ITSELF, in PassportServiceProvider::makeCryptKey. This
        // config deliberately passes the raw value straight through — a
        // normalising closure here would only duplicate the framework.
    }

    #[Test]
    public function theProductionLogChannelWritesToTheContainerStream(): void
    {
        // A file inside an ephemeral container filesystem is written, rotated and
        // then thrown away with the container that produced it.
        self::assertSame('php://stderr', config('logging.channels.stderr.with.stream'));
    }

    #[Test]
    public function signedUrlsAreShortLived(): void
    {
        // A signed URL is a bearer credential for one object, routinely pasted
        // into chat and logged by intermediaries.
        $ttl = (int) config('custom.storage.temporary_url_ttl');

        self::assertGreaterThan(0, $ttl);
        self::assertLessThanOrEqual(60, $ttl);
    }

    #[Test]
    public function everyDiskExceptPublicStoresObjectsPrivately(): void
    {
        // An object store is unforgiving here: a bucket serving publicly readable
        // objects has no second gate behind it, and nothing inside the
        // application can tell that the last upload became world-readable. The
        // `public` disk is the single deliberate exception — it is the local disk
        // behind the storage symlink, for assets that are meant to be public.
        //
        // Asserted on the SHIPPED file rather than the resolved config, so a fork
        // that sets `visibility => public` on s3 or gcs fails here even if its
        // environment never selects that disk.
        $disks = (require config_path('filesystems.php'))['disks'];

        foreach ($disks as $name => $disk) {
            if ($name === 'public') {
                self::assertSame('public', $disk['visibility']);

                continue;
            }

            self::assertSame(
                'private',
                $disk['visibility'] ?? 'private',
                "Disk [{$name}] must store objects privately; the application hands out short-lived signed URLs instead."
            );
        }
    }

    #[Test]
    public function noStorageDiskRequiresStaticCredentials(): void
    {
        // Workload identity is the recommended production posture: with the
        // key/secret and key-file variables unset, each SDK falls back to its
        // ambient credential chain — an attached role or managed identity, with
        // no long-lived secret to distribute or rotate. A committed default that
        // filled these in would force static keys back into every deployment.
        $this->withEnvs([
            'AWS_ACCESS_KEY_ID' => null,
            'AWS_SECRET_ACCESS_KEY' => null,
            'GOOGLE_CLOUD_KEY_FILE' => null,
        ], function (): void {
            $disks = (require config_path('filesystems.php'))['disks'];

            self::assertNull($disks['s3']['key']);
            self::assertNull($disks['s3']['secret']);
            self::assertNull($disks['gcs']['key_file_path']);
        });
    }

    #[Test]
    public function queueBookkeepingUsesTheApplicationsOwnDatabase(): void
    {
        // Laravel's stock fallback here is `sqlite`. With DB_CONNECTION unset
        // that split the application (pgsql) from its batch and failed-job
        // bookkeeping (a SQLite file inside the container), so the first failed
        // job would be recorded somewhere nobody looks — or fail inside the
        // failure handler.
        $this->withEnvs(['DB_CONNECTION' => null], function (): void {
            $queue = require config_path('queue.php');
            $database = require config_path('database.php');

            self::assertSame($database['default'], $queue['failed']['database']);
            self::assertSame($database['default'], $queue['batching']['database']);
        });
    }

    #[Test]
    public function theLocalDiskCanIssueTemporaryUrls(): void
    {
        // Without `serve`, temporaryUrl() throws on a local disk, so the
        // development path diverges from the production one — which means the
        // production path is never exercised until it is in production.
        self::assertTrue(config('filesystems.disks.local.serve'));
    }

    #[Test]
    public function apiDocumentationStaysRestricted(): void
    {
        // The generated OpenAPI document describes every endpoint, including the
        // administrative ones.
        self::assertContains(
            \Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess::class,
            config('scramble.middleware')
        );
    }

    /**
     * Run a callback with one environment variable temporarily set, then restore
     * whatever was there before.
     *
     * All THREE stores are written, and that is the point. phpdotenv's repository
     * consults its adapters in order — $_SERVER before $_ENV — so setting only
     * $_ENV is silently ignored for any key that .env has already written into
     * $_SERVER, and the test reads the ORIGINAL value while appearing to override
     * it. That produced a confusing false failure before this helper existed.
     *
     * @param string $key
     * @param string|null $value - null unsets the variable for the duration
     * @param callable $callback
     *
     * @return void
     */
    private function withEnv(string $key, ?string $value, callable $callback): void
    {
        $original = $_SERVER[$key] ?? $_ENV[$key] ?? null;

        $apply = static function (?string $newValue) use ($key): void {
            if ($newValue === null) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);

                return;
            }

            putenv($key . '=' . $newValue);
            $_ENV[$key] = $newValue;
            $_SERVER[$key] = $newValue;
        };

        $apply($value);

        try {
            $callback();
        } finally {
            $apply($original === null ? null : (string) $original);
        }
    }

    /**
     * The same, for several variables at once.
     *
     * Redis TLS is only meaningful as a COMBINATION — enabled plus a CA, enabled
     * plus a client certificate — so asserting on one variable at a time would
     * not describe any configuration a deployment actually uses.
     *
     * @param array<string, string|null> $variables - null unsets that variable for the duration
     * @param callable $callback
     *
     * @return void
     */
    private function withEnvs(array $variables, callable $callback): void
    {
        if (empty($variables)) {
            $callback();

            return;
        }

        $key = array_key_first($variables);
        $value = $variables[$key];
        unset($variables[$key]);

        // Nested rather than looped, so every variable is restored by the same
        // `finally` that set it even when the callback throws.
        $this->withEnv($key, $value, function () use ($variables, $callback): void {
            $this->withEnvs($variables, $callback);
        });
    }

    /**
     * Re-evaluate config/database.php against the environment currently in place
     * and return the default Redis connection it produces.
     *
     * The connection array is built by a closure at config LOAD time, so the
     * already-booted application's `config('database.redis.default')` reflects
     * the test environment and nothing else. Re-requiring the file is what makes
     * a TLS or credential variable observable at all.
     *
     * @return array<string, mixed>
     */
    private function rebuildRedisConnection(): array
    {
        return (require config_path('database.php'))['redis']['default'];
    }

}
