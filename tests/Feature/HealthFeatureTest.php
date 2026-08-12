<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The two probes a container platform relies on.
 *
 * Both must answer WITHOUT a bearer token — a load balancer cannot present one —
 * and neither may disclose anything about the deployment's internals to the
 * anonymous callers that reach them.
 */
class HealthFeatureTest extends TestCase
{

    #[Test]
    public function livenessAnswersWithoutAuthentication(): void
    {
        // Laravel's built-in health route, registered in bootstrap/app.php. It is
        // the LIVENESS probe: failing it gets the container killed, so it must
        // depend on nothing but the process itself.
        $this->get('/up')->assertOk();
    }

    #[Test]
    public function readinessReportsEveryDependencyWhenHealthy(): void
    {
        // The suite runs against a real database and Redis, so a green run here
        // means the probe actually exercised both.
        $response = $this->getJson('/api/health/ready');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'ready',
                'checks' => [
                    'database' => true,
                    'redis' => true,
                ],
            ]);
    }

    #[Test]
    public function readinessRequiresNoAuthentication(): void
    {
        // A 401 here would make every platform health check fail and the
        // deployment never come into service.
        $this->getJson('/api/health/ready')->assertOk();
    }

    #[Test]
    public function readinessDisclosesNothingBeyondBooleanChecks(): void
    {
        // This endpoint is unauthenticated by necessity, and a dependency error
        // message is one of the easiest ways to hand out internal hostnames,
        // ports and software versions.
        $payload = $this->getJson('/api/health/ready')->json();

        // `message` keeps the readiness body inside the ONE error envelope
        // docs/api-contract.md mandates; `status` and `checks` are additive.
        self::assertSame(['success', 'message', 'status', 'checks'], array_keys($payload));

        foreach ($payload['checks'] as $name => $result) {
            self::assertIsBool($result, "Check [{$name}] must expose a boolean and nothing else.");
        }
    }

    #[Test]
    public function readinessReports503AndFlagsTheFailedDependency(): void
    {
        // Driven by a genuinely unreachable database rather than a facade mock:
        // the probe's internals (transaction + SET LOCAL + select) are an
        // implementation detail, and a mock of them would pass while the real
        // path broke.
        $this->withUnreachableDatabase(function (): void {
            $this->getJson('/api/health/ready')
                ->assertStatus(503)
                ->assertJson([
                    'success' => false,
                    'status' => 'unavailable',
                    'checks' => ['database' => false],
                ]);
        });
    }

    #[Test]
    public function readinessDisclosesNothingWhenADependencyIsDown(): void
    {
        // The disclosure assertion has to run on the FAILING path — a driver
        // error is one of the easiest ways to hand out internal hostnames,
        // private addresses, ports and software versions.
        $this->withUnreachableDatabase(function (): void {
            $body = $this->getJson('/api/health/ready')->assertStatus(503)->getContent();

            self::assertStringNotContainsString('SQLSTATE', $body);
            self::assertStringNotContainsString('pgsql', $body);
            self::assertStringNotContainsString('Connection refused', $body);
            self::assertStringNotContainsString((string) config('database.connections.pgsql.host'), $body);

            $payload = json_decode($body, true);
            self::assertSame(['success', 'message', 'status', 'checks'], array_keys($payload));

            foreach ($payload['checks'] as $name => $result) {
                self::assertIsBool($result, "Check [{$name}] must expose a boolean even when failing.");
            }
        });
    }

    /**
     * Run a callback with the database connection pointed at a closed port, then
     * restore it.
     *
     * @param callable $callback
     *
     * @return void
     */
    private function withUnreachableDatabase(callable $callback): void
    {
        $originalHost = config('database.connections.pgsql.host');
        $originalPort = config('database.connections.pgsql.port');

        config()->set('database.connections.pgsql.host', '127.0.0.1');
        config()->set('database.connections.pgsql.port', 1);
        DB::purge('pgsql');

        try {
            $callback();
        } finally {
            config()->set('database.connections.pgsql.host', $originalHost);
            config()->set('database.connections.pgsql.port', $originalPort);
            DB::purge('pgsql');
        }
    }

    #[Test]
    public function readinessIsNotPlacedBehindARedisBackedRateLimiter(): void
    {
        // The limiter resolves through the default cache store, which production
        // requires to be Redis — so a throttle here would throw during the exact
        // outage this endpoint exists to report, turning the documented 503 into
        // an undiagnosable 500.
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r): bool => $r->uri() === 'api/health/ready');

        self::assertNotNull($route);

        foreach ($route->gatherMiddleware() as $middleware) {
            self::assertStringNotContainsString(
                'throttle',
                (string) $middleware,
                'Readiness must not depend on the cache store it is reporting on.'
            );
        }
    }

    #[Test]
    public function readinessIsNotCoupledToWorkerHealth(): void
    {
        // Horizon is not running during the test suite. Readiness must still pass:
        // folding queue health into API readiness would pull every API replica out
        // of the load balancer over a worker problem.
        $this->getJson('/api/health/ready')
            ->assertOk()
            ->assertJsonMissingPath('checks.horizon');
    }

}
