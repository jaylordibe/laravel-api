<?php

namespace Tests\Feature;

use App\Models\User;
use App\Utils\AppUtil;
use App\Utils\DateUtil;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserFeatureTest extends TestCase
{

    private string $resource = '/api/users';

    #[Test]
    public function testSignUpUser(): void
    {
        // Prevent the actual notification from being sent
        Notification::fake();

        $payload = [
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'phoneNumber' => fake()->phoneNumber(),
            'email' => AppUtil::generateUniqueToken() . fake()->unique()->safeEmail(),
            'password' => 'password',
            'passwordConfirmation' => 'password'
        ];
        $response = $this->post("{$this->resource}/sign-up", $payload);

        $expected = [
            'success' => true
        ];
        $response->assertOk()->assertJson($expected);
    }

    #[Test]
    public function testGetAuthUser(): void
    {
        $token = $this->loginSystemAdminUser();
        $userData = $this->getAuthUser($token);
        $response = $this->withToken($token)->get("{$this->resource}/auth");

        $expected = [
            'id' => $userData->id,
            'firstName' => $userData->firstName,
            'middleName' => $userData->middleName,
            'lastName' => $userData->lastName,
            'username' => $userData->username,
            'email' => $userData->email,
            'timezone' => $userData->timezone,
            'phoneNumber' => $userData->phoneNumber,
            'birthday' => $userData->birthday
        ];
        $response->assertOk()->assertJson($expected);
    }

    #[Test]
    public function testUpdateAuthUserName(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $this->login($user->email);
        $payload = ['username' => AppUtil::generateUniqueToken() . fake()->unique()->userName()];
        $response = $this->withToken($token)->put("{$this->resource}/auth/username", $payload);

        $expected = [
            'id' => $user->id,
            'firstName' => $user->first_name,
            'middleName' => $user->middle_name,
            'lastName' => $user->last_name,
            'username' => $payload['username'],
            'email' => $user->email,
            'timezone' => $user->timezone,
            'phoneNumber' => $user->phone_number,
            'birthday' => $user->birthday->toISOString()
        ];
        $response->assertOk()->assertJson($expected);
    }

    #[Test]
    public function testUpdateAuthUserEmail(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $this->login($user->email);
        $payload = ['email' => AppUtil::generateUniqueToken() . fake()->unique()->safeEmail()];
        $response = $this->withToken($token)->put("{$this->resource}/auth/email", $payload);

        $expected = [
            'id' => $user->id,
            'firstName' => $user->first_name,
            'middleName' => $user->middle_name,
            'lastName' => $user->last_name,
            'username' => $user->username,
            'email' => $payload['email'],
            'timezone' => $user->timezone,
            'phoneNumber' => $user->phone_number,
            'birthday' => $user->birthday->toISOString()
        ];
        $response->assertOk()->assertJson($expected);
    }

    #[Test]
    public function testUpdateAuthUserPassword(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $this->login($user->email);
        $payload = [
            'password' => 'password',
            'passwordConfirmation' => 'password'
        ];
        $response = $this->withToken($token)->put("{$this->resource}/auth/password", $payload);

        $expected = [
            'success' => true
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
            'email' => AppUtil::generateUniqueToken() . fake()->unique()->safeEmail(),
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
            'id' => $user->id,
            'firstName' => $user->first_name,
            'middleName' => $user->middle_name,
            'lastName' => $user->last_name,
            'username' => $user->username,
            'email' => $user->email,
            'timezone' => $user->timezone,
            'phoneNumber' => $user->phone_number,
            'birthday' => $user->birthday->toISOString()
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
            'birthday' => DateUtil::stripMilliseconds($payload['birthday'])
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

    #[Test]
    public function testUpdateUserPassword(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $this->loginSystemAdminUser();
        $payload = [
            'password' => 'password',
            'passwordConfirmation' => 'password'
        ];
        $response = $this->withToken($token)->put("{$this->resource}/{$user->id}/password", $payload);

        $expected = [
            'success' => true
        ];
        $response->assertOk()->assertJson($expected);
    }

}
