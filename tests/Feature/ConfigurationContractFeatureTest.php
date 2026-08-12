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

}
