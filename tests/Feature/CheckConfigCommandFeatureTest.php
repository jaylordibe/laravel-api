<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `app:check-config` is the container's fail-fast gate: docker/entrypoint.sh runs
 * it before the api and worker runtimes start, and a non-zero exit stops the
 * container booting.
 *
 * Only its SUCCESS path was covered, which is close to no coverage at all —
 * inverting a condition or deleting an entire check would have kept the suite
 * green while the gate silently stopped gating. Every test here drives a
 * production-only branch and asserts the command actually fails.
 */
class CheckConfigCommandFeatureTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        // Most checks are guarded on the production environment, so the whole
        // class runs as production and then breaks one thing at a time.
        app()['env'] = 'production';
        config()->set('app.debug', false);
        config()->set('cache.default', 'redis');
        config()->set('logging.default', 'stderr');
        config()->set('trustedproxy.proxies', '*');
        config()->set('passport.private_key', '-----BEGIN RSA PRIVATE KEY-----x-----END RSA PRIVATE KEY-----');
        config()->set('passport.public_key', '-----BEGIN PUBLIC KEY-----x-----END PUBLIC KEY-----');
        config()->set('database.connections.pgsql.sslmode', 'verify-full');
    }

    #[Test]
    public function itPassesWhenProductionConfigurationIsSound(): void
    {
        // A positive control. Without it, every assertion below could be passing
        // for an unrelated reason.
        self::assertSame(0, Artisan::call('app:check-config'));
    }

    #[Test]
    public function itFailsWhenTheApplicationKeyIsMissing(): void
    {
        config()->set('app.key', null);

        self::assertSame(1, Artisan::call('app:check-config'));
        self::assertStringContainsString('APP_KEY', Artisan::output());
    }

    #[Test]
    public function itFailsWhenDebugModeIsEnabledInProduction(): void
    {
        // Debug mode renders stack traces and configuration into HTTP responses.
        config()->set('app.debug', true);

        self::assertSame(1, Artisan::call('app:check-config'));
        self::assertStringContainsString('APP_DEBUG', Artisan::output());
    }

    #[Test]
    public function itFailsWhenTheCacheStoreIsPerContainer(): void
    {
        // The silent one: a file cache "works" in a scaled deployment while
        // atomic locks and onOneServer() scheduling quietly guard nothing.
        config()->set('cache.default', 'file');

        self::assertSame(1, Artisan::call('app:check-config'));
        self::assertStringContainsString('CACHE_STORE', Artisan::output());
    }

    #[Test]
    public function itFailsOnASilentlyDowngradingDatabaseSslMode(): void
    {
        // `prefer` negotiates TLS and falls back to plaintext with no trace.
        config()->set('database.connections.pgsql.sslmode', 'prefer');

        self::assertSame(1, Artisan::call('app:check-config'));
        self::assertStringContainsString('DB_SSLMODE', Artisan::output());
    }

    #[Test]
    public function itFailsWhenTheWorkerTimeoutCanOutliveTheQueueRetryWindow(): void
    {
        // timeout >= retry_after means the queue hands the job to a second worker
        // while the first is still running it.
        config()->set('horizon.defaults.supervisor-1.timeout', 120);
        config()->set('queue.connections.redis.retry_after', 90);

        self::assertSame(1, Artisan::call('app:check-config'));
        self::assertStringContainsString('retry_after', Artisan::output());
    }

    #[Test]
    public function itFailsWhenRedisTlsPeerVerificationIsDisabled(): void
    {
        // config/database.php offers no way to reach this state, which is exactly
        // why the gate checks it: the only route here is a fork editing that file
        // or a package overriding the connection, and both are silent. TLS
        // without verification stops a passive listener and nothing else.
        config()->set('database.redis.default.scheme', 'tls');
        config()->set('database.redis.default.context.ssl', [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]);

        self::assertSame(1, Artisan::call('app:check-config'));
        self::assertStringContainsString('peer verification', Artisan::output());
    }

    #[Test]
    public function itFailsWhenTheRedisTlsCaFileIsAbsentFromTheContainer(): void
    {
        // The realistic TLS misconfiguration: REDIS_TLS_CA names a CA bundle that
        // was never mounted into the image. Without this check the container
        // starts, serves traffic, and then fails on the first cache read — an
        // outage that looks like a Redis problem rather than a mount problem.
        config()->set('database.redis.default.scheme', 'tls');
        config()->set('database.redis.default.context.ssl', [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'cafile' => '/etc/ssl/certs/not-mounted-here.pem',
        ]);

        self::assertSame(1, Artisan::call('app:check-config'));
        self::assertStringContainsString('REDIS_TLS_CA', Artisan::output());
    }

    #[Test]
    public function itFailsWhenTheUploadDiskStoresObjectsPublicly(): void
    {
        // Regression guard: this was skipped by disk NAME, so selecting the
        // deliberately-public `public` disk as the upload target passed the check
        // while every user upload became world-readable.
        config()->set('custom.storage.disk', 'public');

        self::assertSame(1, Artisan::call('app:check-config'));
        self::assertStringContainsString('public', Artisan::output());
    }

    #[Test]
    public function itFailsWhenPassportSigningKeysAreAbsent(): void
    {
        config()->set('passport.private_key', null);
        config()->set('passport.public_key', null);

        // The key FILES must also be absent for this to be a real failure; in the
        // test container they are present, so assert on whichever branch applies
        // rather than pretending to know.
        $hasKeyFiles = file_exists(storage_path('oauth-private.key'))
            && file_exists(storage_path('oauth-public.key'));

        $exitCode = Artisan::call('app:check-config');

        if ($hasKeyFiles) {
            // Files present: not fatal, but production must be warned that
            // per-container keys are not stable across replicas.
            self::assertSame(0, $exitCode);
            self::assertStringContainsString('key FILES', Artisan::output());

            return;
        }

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Passport signing keys', Artisan::output());
    }

    #[Test]
    public function itWarnsWhenTheApiDocsFlagIsSetInProduction(): void
    {
        // Not a failure: the middleware denies production unconditionally, so the flag exposes
        // nothing. It is worth saying out loud because an operator who set it believes the docs
        // are reachable and will read the resulting 403 as a bug somewhere else.
        config()->set('custom.api_docs.enabled', true);

        self::assertSame(0, Artisan::call('app:check-config'));
        self::assertSame(1, Artisan::call('app:check-config', ['--strict' => true]));
        self::assertStringContainsString('API_DOCS_ENABLED', Artisan::output());
    }

    #[Test]
    public function itWarnsWhenTheApiDocsCredentialIsOnlyHalfConfigured(): void
    {
        // The staging shape: not production, flag on, password never injected. It fails closed,
        // so this stays a warning — but the 403 it produces is indistinguishable from the one an
        // attacker gets, and nothing else in the system would ever say why.
        app()['env'] = 'staging';
        config()->set('custom.api_docs.enabled', true);
        config()->set('custom.api_docs.username', 'docs-reader');
        config()->set('custom.api_docs.password', null);

        self::assertSame(1, Artisan::call('app:check-config', ['--strict' => true]));
        self::assertStringContainsString('API_DOCS_PASSWORD', Artisan::output());
    }

}
