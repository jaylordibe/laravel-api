<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\TestDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Passport\Passport;

abstract class TestCase extends BaseTestCase
{

    use RefreshDatabase;

    /**
     * Seeds each test database once; every test then runs in a rolled-back transaction.
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

        $response->assertOk();
        $token = (string) $response->json('token');
        self::assertNotSame('', $token);

        return $token;
    }

    /**
     * Login system admin user through the real sign-in endpoint, for tests about tokens and sessions.
     *
     * @return string
     */
    protected function loginSystemAdminUser(): string
    {
        return $this->login(config('custom.sysad_email'), config('custom.sysad_password'));
    }

    /**
     * Authenticate the following requests as the seeded system admin.
     *
     * @return User
     */
    protected function actingAsSystemAdmin(): User
    {
        /** @var User $admin */
        $admin = User::query()->where('email', config('custom.sysad_email'))->firstOrFail();
        Passport::actingAs($admin);

        return $admin;
    }

    /**
     * Reset authentication between requests of one test that must each sign in afresh (the app instance,
     * session and default headers are otherwise shared, and sign-in switches the default guard).
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

}
