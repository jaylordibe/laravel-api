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

        // For assertion
        unset($payload['password']);
        unset($payload['passwordConfirmation']);
        $payload['username'] = Str::replace('.', '', Str::before($payload['email'], '@'));

        $response->assertCreated()->assertJson($payload);
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

        $response->assertOk()->assertJson(['username' => $payload['username']]);
    }

    #[Test]
    public function testUpdateAuthUserEmail(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $this->login($user->email);
        $payload = ['email' => fake()->unique()->safeEmail()];
        $response = $this->withToken($token)->put("{$this->resource}/auth/email", $payload);

        $response->assertOk()->assertJson(['email' => $payload['email']]);
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

        // For assertion
        unset($payload['password']);
        unset($payload['passwordConfirmation']);
        $payload['username'] = Str::replace('.', '', Str::before($payload['email'], '@'));

        $response->assertOk()->assertJson($payload);
    }

    #[Test]
    public function testGetPaginatedUsers(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->get("{$this->resource}");

        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }

    #[Test]
    public function testGetUserById(): void
    {
        $token = $this->loginSystemAdminUser();
        /** @var User $user */
        $user = User::factory()->create();
        $response = $this->withToken($token)->get("{$this->resource}/{$user->id}");

        $response->assertOk()->assertJson(['id' => $response->json()['id']]);
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

        // For assertion
        $payload['id'] = $user->id;

        $response->assertOk()->assertJson($payload);
    }

    #[Test]
    public function testDeleteUser(): void
    {
        $token = $this->loginSystemAdminUser();
        /** @var User $user */
        $user = User::factory()->create();
        $response = $this->withToken($token)->delete("{$this->resource}/{$user->id}");

        $response->assertOk()->assertJsonStructure(['success']);
    }

}
