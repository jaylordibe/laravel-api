<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{

    use RefreshDatabase;

    private string $endpoint = '/api/users';

    /**
     * A basic test in getting authenticated user by id.
     */
    public function testGetAuthUser(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->get("{$this->endpoint}/auth");

        $response->assertOk();
    }

    /**
     * A basic test in creating a user.
     */
    public function testCreateUser(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->post("{$this->endpoint}");

        $response->assertOk();
    }

    /**
     * A basic test in getting paginated users.
     */
    public function testGetUsers(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->get("{$this->endpoint}");

        $response->assertOk();
    }

    /**
     * A basic test in getting user by id.
     */
    public function testGetUserById(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->get("{$this->endpoint}");

        $response->assertOk();
    }

    /**
     * A basic test in updating a user.
     */
    public function testUpdateUser(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->put("{$this->endpoint}");

        $response->assertOk();
    }

    /**
     * A basic test in deleting a user.
     */
    public function testDeleteUser(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->delete("{$this->endpoint}");

        $response->assertOk();
    }

}
