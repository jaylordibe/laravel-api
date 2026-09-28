<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Utils\AppUtil;
use App\Utils\DateUtil;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
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
            // Lower-cased: User normalises email/username on write so the
            // PostgreSQL unique index and every lookup compare the same value.
            'username' => Str::lower($userData->username),
            'email' => Str::lower($userData->email),
            'timezone' => $userData->timezone,
            'phoneNumber' => $userData->phoneNumber,
            'birthdate' => $userData->birthdate
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
            'username' => Str::lower($payload['username']),
            'email' => $user->email,
            'timezone' => $user->timezone,
            'phoneNumber' => $user->phone_number,
            'birthdate' => $user->birthdate->toISOString()
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
            'email' => Str::lower($payload['email']),
            'timezone' => $user->timezone,
            'phoneNumber' => $user->phone_number,
            'birthdate' => $user->birthdate->toISOString()
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
            'currentPassword' => 'password',
            'password' => 'new-password',
            'passwordConfirmation' => 'new-password'
        ];
        $response = $this->withToken($token)->put("{$this->resource}/auth/password", $payload);

        $expected = [
            'success' => true
        ];
        $response->assertOk()->assertJson($expected);
        self::assertTrue(Hash::check('new-password', $user->refresh()->password));

        // Every session re-authenticates with the new password, the caller's included.
        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->get("{$this->resource}/auth")->assertUnauthorized();
    }

    /**
     * A bearer token alone must not be able to change its owner's password: that would let a stolen
     * token take the account over — the change signs every session out — however short it lives.
     *
     * @return array<string, array{array<string, string>}>
     */
    public static function missingOrWrongCurrentPassword(): array
    {
        return [
            'missing' => [[]],
            'empty' => [['currentPassword' => '']],
            'wrong' => [['currentPassword' => 'not-the-password']],
        ];
    }

    #[Test]
    #[DataProvider('missingOrWrongCurrentPassword')]
    public function selfPasswordChangeRequiresTheCurrentPassword(array $currentPassword): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $this->login($user->email);
        $payload = $currentPassword + [
            'password' => 'new-password',
            'passwordConfirmation' => 'new-password'
        ];

        $this->withToken($token)->put("{$this->resource}/auth/password", $payload)
            ->assertBadRequest()
            ->assertJson(['success' => false, 'message' => 'Current password is incorrect.']);

        self::assertTrue(Hash::check('password', $user->refresh()->password), 'The password must be unchanged.');
        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->get("{$this->resource}/auth")->assertOk();
    }

    /**
     * A factory admin, never the seeded one: these tests change passwords, and the shared test
     * database is not refreshed between runs, so a regression here must not break every other test
     * that signs in as the seeded system admin.
     *
     * @return User
     */
    private function createAdmin(): User
    {
        return User::factory()->withRole(UserRole::SYSTEM_ADMIN)->create();
    }

    #[Test]
    public function adminChangingTheirOwnPasswordAlsoNeedsTheCurrentPassword(): void
    {
        $admin = $this->createAdmin();
        $token = $this->login($admin->email);
        $payload = [
            'password' => 'new-password',
            'passwordConfirmation' => 'new-password'
        ];

        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->put("{$this->resource}/{$admin->id}/password", $payload)
            ->assertBadRequest()
            ->assertJson(['message' => 'Current password is incorrect.']);

        self::assertTrue(Hash::check('password', $admin->refresh()->password), 'The password must be unchanged.');
    }

    #[Test]
    public function adminChangingTheirOwnPasswordWithTheCurrentPasswordSucceeds(): void
    {
        $admin = $this->createAdmin();
        $token = $this->login($admin->email);
        $payload = [
            'currentPassword' => 'password',
            'password' => 'new-password',
            'passwordConfirmation' => 'new-password'
        ];

        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->put("{$this->resource}/{$admin->id}/password", $payload)->assertOk();

        self::assertTrue(Hash::check('new-password', $admin->refresh()->password));
        $this->forgetAuthenticatedUsers();
        $this->withToken($token)->get("{$this->resource}/auth")->assertUnauthorized();
    }

    #[Test]
    public function passwordRoutesAreRateLimitedLikeSignIn(): void
    {
        // The current password makes these routes a guessing surface; AGENTS.md's auth rule puts
        // every auth-input endpoint under `sensitive`. The suite disables throttling globally, so
        // the limiter is asserted on the route itself.
        foreach (['users/auth/password', 'users/{userId}/password'] as $uri) {
            $route = collect(Route::getRoutes()->getRoutes())
                ->first(fn ($candidate): bool => $candidate->uri() === "api/{$uri}" && in_array('PUT', $candidate->methods(), true));

            self::assertNotNull($route, "Route PUT api/{$uri} is missing.");
            self::assertContains('throttle:sensitive', $route->gatherMiddleware(), "PUT api/{$uri} must use the sensitive limiter.");
        }
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
            'passwordConfirmation' => 'password',
            'role' => fake()->randomElement(UserRole::cases())->value
        ];
        $response = $this->withToken($token)->post("{$this->resource}", $payload);

        $expected = [
            'firstName' => $payload['firstName'],
            'lastName' => $payload['lastName'],
            'phoneNumber' => $payload['phoneNumber'],
            'email' => Str::lower($payload['email']),
            'username' => Str::lower(Str::replace('.', '', Str::before($payload['email'], '@')))
        ];

        $response->assertCreated()->assertJson($expected);
    }

    #[Test]
    public function testCreateUserWithDuplicateEmailReturnsSafeGenericError(): void
    {
        $token = $this->loginSystemAdminUser();
        /** @var User $existingUser */
        $existingUser = User::factory()->create();
        $payload = [
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'phoneNumber' => fake()->phoneNumber(),
            'email' => $existingUser->email,
            'password' => 'password',
            'passwordConfirmation' => 'password',
            'role' => fake()->randomElement(UserRole::cases())->value
        ];
        $response = $this->withToken($token)->post("{$this->resource}", $payload);

        // The duplicate email hits the users.email unique constraint inside the
        // transaction; the hardened generic catch must surface the uniform 400
        // envelope with a SAFE message — never the raw database error.
        $expected = [
            'success' => false,
            'message' => 'Failed to create user.'
        ];
        $response->assertBadRequest()->assertJson($expected);
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
            'birthdate' => $user->birthdate->toISOString()
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
            'birthdate' => now()->subYears(25)->startOfDay()->toISOString()
        ];
        $response = $this->withToken($token)->put("{$this->resource}/{$user->id}", $payload);

        $expected = [
            'id' => $user->id,
            'firstName' => $payload['firstName'],
            'middleName' => $payload['middleName'],
            'lastName' => $payload['lastName'],
            'timezone' => $payload['timezone'],
            'phoneNumber' => $payload['phoneNumber'],
            'birthdate' => DateUtil::stripMilliseconds($payload['birthdate'])
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
        // An admin resetting SOMEONE ELSE's password needs no current password — the one path that
        // skips it — and the reset still signs the target out everywhere.
        /** @var User $user */
        $user = User::factory()->create();
        $userToken = $this->login($user->email);
        $this->forgetAuthenticatedUsers();
        $token = $this->login($this->createAdmin()->email);
        $payload = [
            'password' => 'reset-password',
            'passwordConfirmation' => 'reset-password'
        ];
        $this->forgetAuthenticatedUsers();
        $response = $this->withToken($token)->put("{$this->resource}/{$user->id}/password", $payload);

        $expected = [
            'success' => true
        ];
        $response->assertOk()->assertJson($expected);
        self::assertTrue(Hash::check('reset-password', $user->refresh()->password));

        $this->forgetAuthenticatedUsers();
        $this->withToken($userToken)->get("{$this->resource}/auth")->assertUnauthorized();
    }

}
