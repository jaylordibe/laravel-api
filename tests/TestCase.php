<?php

namespace Tests;

use App\Data\UserData;
use App\Enums\Gender;
use Database\Seeders\TestDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{

    use RefreshDatabase;

    /**
     * Seeded once per test database, when RefreshDatabase migrates it. Each test then runs inside a
     * transaction that is rolled back, so no test sees another's rows — including across --parallel
     * workers, which each get their own database.
     *
     * @var string
     */
    protected string $seeder = TestDatabaseSeeder::class;

    /**
     * The application's default auth guard, captured before any request can switch it.
     *
     * @var string
     */
    private string $defaultGuard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultGuard = config('auth.defaults.guard');

        // Disable rate limiting for tests (important for parallel tests)
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /**
     * Refuse to migrate anything but a test database.
     *
     * RefreshDatabase runs migrate:fresh on whatever connection config resolves to. A cached config or an
     * APP_ENV exported by the shell would point it at a real database; stop before that happens.
     *
     * @return void
     */
    protected function beforeRefreshingDatabase(): void
    {
        $database = (string) config('database.connections.' . config('database.default') . '.database');

        if ($this->app->configurationIsCached() || !str_contains($database, '_test')) {
            throw new RuntimeException("Refusing to refresh [{$database}]: tests only run against a *_test database with uncached config.");
        }
    }

    /**
     * Login.
     *
     * @param string $identifier
     * @param string $password
     *
     * @return string
     */
    protected function login(string $identifier, string $password = 'password'): string
    {
        $data = [
            'identifier' => $identifier,
            'password' => $password
        ];
        $response = $this->post('/api/auth/sign-in', $data);

        // Fail here, not later: an empty token is always 401, so a test asserting that a session
        // was revoked would otherwise pass without the session ever having existed.
        $response->assertOk();
        $token = (string) $response->json('token');
        self::assertNotSame('', $token);

        return $token;
    }

    /**
     * Login system admin user.
     *
     * @return string
     */
    protected function loginSystemAdminUser(): string
    {
        return $this->login(config('custom.sysad_email'), config('custom.sysad_password'));
    }

    /**
     * Reset authentication to what a fresh request would see.
     *
     * The application instance, session and default headers are shared by all requests in a test, so
     * without this a guard can keep answering with the user it resolved for an earlier request — a
     * revoked token would look valid — the session login `Auth::attempt` leaves behind, or a bearer
     * header set by `withToken()`, trips the `guest` middleware on the next sign-in, and an `auth:api`
     * request leaves `api` as the default guard, which sign-in cannot attempt against. Call it between
     * requests that must each authenticate afresh.
     *
     * @return void
     */
    protected function forgetAuthenticatedUsers(): void
    {
        $this->flushSession();
        $this->withoutToken();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse($this->defaultGuard);
    }

    /**
     * Get auth user.
     *
     * @param string $token
     *
     * @return UserData
     */
    protected function getAuthUser(string $token): UserData
    {
        $response = $this->withToken($token)->get('/api/users/auth');
        $authUser = $response->json();

        return new UserData(
            firstName: $authUser['firstName'],
            middleName: $authUser['middleName'],
            lastName: $authUser['lastName'],
            username: $authUser['username'],
            email: $authUser['email'],
            emailVerifiedAt: empty($authUser['emailVerifiedAt']) ? null : Carbon::parse($authUser['emailVerifiedAt']),
            phoneNumber: $authUser['phoneNumber'],
            gender: Gender::tryFrom($authUser['gender'] ?? null),
            birthdate: $authUser['birthdate'],
            timezone: $authUser['timezone'],
            profileImage: $authUser['profileImage'],
            address: $authUser['address'],
            id: $authUser['id'],
            createdAt: Carbon::parse($authUser['createdAt']),
            updatedAt: Carbon::parse($authUser['updatedAt'])
        );
    }

}
