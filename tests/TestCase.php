<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
        $response = $this->post('/api/authenticate', $data);

        return $response->json()['token'] ?? '';
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

}
