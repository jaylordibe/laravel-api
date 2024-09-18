<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserFeatureTest extends TestCase
{

    private string $resource = '/api/users';

    #[Test]
    public function testSignUpUser(): void
    {
        $payload = [
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'phoneNumber' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'passwordConfirmation' => 'password'
        ];
        $response = $this->post("{$this->resource}/sign-up", $payload);
        $expected = [
            'firstName' => $payload['firstName'],
            'lastName' => $payload['lastName'],
            'phoneNumber' => $payload['phoneNumber'],
            'email' => $payload['email'],
            'username' => Str::replace('.', '', Str::before($payload['email'], '@'))
        ];

        $response->assertCreated()->assertJson($expected);
    }

    #[Test]
    public function testGetAuthUser(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->get("{$this->resource}/auth");

        $response->assertOk();
    }

    #[Test]
    public function testUpdateAuthUserName(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $this->login($user->email);
        $payload = ['username' => fake()->unique()->userName()];
        $response = $this->withToken($token)->put("{$this->resource}/auth/username", $payload);

        $expected = [
            'username' => $payload['username']
        ];
        $response->assertOk()->assertJson($expected);
    }

    #[Test]
    public function testUpdateAuthUserEmail(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $this->login($user->email);
        $payload = ['email' => fake()->unique()->safeEmail()];
        $response = $this->withToken($token)->put("{$this->resource}/auth/email", $payload);

        $expected = [
            'email' => $payload['email']
        ];
        $response->assertOk()->assertJson($expected);
    }

    #[Test]
    public function testCreateUser(): void
    {
        $token = $this->loginSystemAdminUser();
        $payload = [
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'phoneNumber' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'passwordConfirmation' => 'password'
        ];
        $response = $this->withToken($token)->post("{$this->resource}", $payload);
        $expected = [
            'firstName' => $payload['firstName'],
            'lastName' => $payload['lastName'],
            'phoneNumber' => $payload['phoneNumber'],
            'email' => $payload['email'],
            'username' => Str::replace('.', '', Str::before($payload['email'], '@'))
        ];

        $response->assertCreated()->assertJson($expected);
    }

    #[Test]
    public function testGetPaginatedUsers(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->get("{$this->resource}");

        $expected = [
            'data',
            'links',
            'meta'
        ];
        $response->assertOk()->assertJsonStructure($expected);
    }

    #[Test]
    public function testGetUserById(): void
    {
        $token = $this->loginSystemAdminUser();
        /** @var User $user */
        $user = User::factory()->create();
        $response = $this->withToken($token)->get("{$this->resource}/{$user->id}");

        $expected = [
            'id' => $user->id
        ];
        $response->assertOk()->assertJson($expected);
    }

    #[Test]
    public function testUpdateUser(): void
    {
        $token = $this->loginSystemAdminUser();
        /** @var User $user */
        $user = User::factory()->create();
        $payload = [
            'firstName' => fake()->firstName(),
            'middleName' => fake()->lastName(),
            'lastName' => fake()->lastName(),
            'timezone' => fake()->timezone(),
            'phoneNumber' => fake()->phoneNumber(),
            'birthday' => now()->subYears(25)->startOfDay()->toISOString()
        ];
        $response = $this->withToken($token)->put("{$this->resource}/{$user->id}", $payload);
        $expected = [
            'id' => $user->id,
            'firstName' => $payload['firstName'],
            'middleName' => $payload['middleName'],
            'lastName' => $payload['lastName'],
            'timezone' => $payload['timezone'],
            'phoneNumber' => $payload['phoneNumber'],
            'birthday' => $this->stripMilliseconds($payload['birthday'])
        ];

        $response->assertOk()->assertJson($expected);
    }

    #[Test]
    public function testDeleteUser(): void
    {
        $token = $this->loginSystemAdminUser();
        /** @var User $user */
        $user = User::factory()->create();
        $response = $this->withToken($token)->delete("{$this->resource}/{$user->id}");
        $expected = [
            'success' => true
        ];
        $response->assertOk()->assertJson($expected);
    }

}
