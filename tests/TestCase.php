<?php

namespace Tests;

use App\Data\UserData;
use App\Enums\Gender;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{

    protected function setUp(): void
    {
        parent::setUp();

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
     * Drop every guard's resolved user and the session sign-in wrote.
     *
     * The application instance and session are shared by all requests in a test, so without this a
     * guard can keep answering with the user it resolved for an earlier request — a revoked token
     * would look valid — and the session login `Auth::attempt` leaves behind trips the `guest`
     * middleware on the next sign-in. Call it between requests that must each authenticate afresh.
     *
     * @return void
     */
    protected function forgetAuthenticatedUsers(): void
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
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
