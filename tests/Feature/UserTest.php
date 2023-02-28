<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserTest extends TestCase
{

    private string $endpoint = '/api/users';

    /**
     * Get user payload.
     *
     * @return array
     */
    private function getPayload(): array
    {
        return [
            'firstName' => fake()->firstName(),
            'middleName' => fake()->lastName(),
            'lastName' => fake()->lastName(),
            'timezone' => fake()->timezone(),
            'phoneNumber' => fake()->phoneNumber(),
            'birthday' => now()->subYears(random_int(1, 20))->startOfDay()->toISOString()
        ];
    }

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
     * A basic test in updating auth user's username.
     */
    public function testUpdateAuthUserName(): void
    {
        $user = User::factory()->create();
        $token = $this->login($user->email);
        $payload = ['username' => fake()->unique()->userName()];
        $response = $this->withToken($token)->put("{$this->endpoint}/auth/username", $payload);

        $response->assertOk()->assertJson(['username' => $payload['username']]);
    }

    /**
     * A basic test in updating auth user's email.
     */
    public function testUpdateAuthUserEmail(): void
    {
        $user = User::factory()->create();
        $token = $this->login($user->email);
        $payload = ['email' => fake()->unique()->safeEmail()];
        $response = $this->withToken($token)->put("{$this->endpoint}/auth/email", $payload);

        $response->assertOk()->assertJson(['email' => $payload['email']]);
    }

    /**
     * A basic test in creating a user.
     */
    public function testCreateUser(): void
    {
        $token = $this->loginSystemAdminUser();
        $payload = $this->getPayload();
        $payload['email'] = fake()->unique()->safeEmail();
        $payload['password'] = 'password';
        $payload['passwordConfirmation'] = 'password';
        $response = $this->withToken($token)->post("{$this->endpoint}", $payload);

        // For assertion
        unset($payload['password']);
        unset($payload['passwordConfirmation']);
        $payload['username'] = Str::replace('.', '', Str::before($payload['email'], '@'));

        $response->assertCreated()->assertJson($payload);
    }

    /**
     * A basic test in getting paginated users.
     */
    public function testGetPaginatedUsers(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->get("{$this->endpoint}");

        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }

    /**
     * A basic test in getting user by id.
     */
    public function testGetUserById(): void
    {
        $token = $this->loginSystemAdminUser();
        $user = User::factory()->create();
        $response = $this->withToken($token)->get("{$this->endpoint}/{$user->getKey()}");

        $response->assertOk()->assertJson(['id' => $response->json()['id']]);
    }

    /**
     * A basic test in updating a user.
     */
    public function testUpdateUser(): void
    {
        $token = $this->loginSystemAdminUser();
        $user = User::factory()->create();
        $payload = $this->getPayload();
        $response = $this->withToken($token)->put("{$this->endpoint}/{$user->getKey()}", $payload);

        // For assertion
        $payload['id'] = $user->getKey();

        $response->assertOk()->assertJson($payload);
    }

    /**
     * A basic test in deleting a user.
     */
    public function testDeleteUser(): void
    {
        $token = $this->loginSystemAdminUser();
        $user = User::factory()->create();
        $response = $this->withToken($token)->delete("{$this->endpoint}/{$user->getKey()}");

        $response->assertOk()->assertJsonStructure(['success']);
    }

}
