<?php

namespace Tests;

use App\Data\UserData;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{

    use CreatesApplication;

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
            lastName: $authUser['lastName'],
            username: $authUser['username'],
            email: $authUser['email'],
            middleName: $authUser['middleName'],
            timezone: $authUser['timezone'],
            phoneNumber: $authUser['phoneNumber'],
            birthday: $authUser['birthday'],
            profilePicture: $authUser['profilePicture'],
            id: $authUser['id'],
            createdAt: Carbon::parse($authUser['createdAt'])
        );
    }

}
