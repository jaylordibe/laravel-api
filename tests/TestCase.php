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

        return (string) $response->json('token');
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
            id: $authUser['id'],
            createdAt: Carbon::parse($authUser['createdAt']),
            updatedAt: Carbon::parse($authUser['updatedAt'])
        );
    }

}
