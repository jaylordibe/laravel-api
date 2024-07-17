<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthFeatureTest extends TestCase
{

    private string $resource = '/api/auth';

    /**
     * Get address payload.
     *
     * @return array
     */
    private function getPayload(): array
    {
        return [
            'identifier' => config('custom.sysad_email'),
            'password' => config('custom.sysad_password')
        ];
    }

    #[Test]
    public function testSignIn(): void
    {
        $payload = $this->getPayload();
        $response = $this->post("{$this->resource}/sign-in", $payload);

        $response->assertOk()->assertJsonStructure(['token']);
    }

    #[Test]
    public function testSignOut(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->post("{$this->resource}/sign-out");

        $response->assertOk()->assertJsonStructure(['success']);
    }

}
