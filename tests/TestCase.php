<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{

    use CreatesApplication;

    protected function loginSystemAdminUser(): string
    {
        $data = [
            'identifier' => config('custom.sysad_email'),
            'password' => config('custom.sysad_password')
        ];
        $response = $this->post('/api/authenticate', $data);

        return $response->json()['token'] ?? '';
    }

}
