<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Utils\AppUtil;
use App\Utils\DateUtil;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Routing\Middleware\ThrottleRequests;
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
     * A factory admin, so the actor is explicit in the test rather than borrowed from the seed data.
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

    #[Test]
    public function theAuthUserStillIncludesAccessControlWhenAskedFor(): void
    {
        $token = $this->loginSystemAdminUser();

        $withAccess = $this->withToken($token)->get("{$this->resource}/auth?includeAccessControl=true")->assertOk();
        self::assertContains(UserRole::SYSTEM_ADMIN->value, $withAccess->json('roles'));
        self::assertNotEmpty($withAccess->json('permissions'));

        $this->forgetAuthenticatedUsers();
        $withoutAccess = $this->withToken($token)->get("{$this->resource}/auth")->assertOk();
        self::assertArrayNotHasKey('roles', $withoutAccess->json());
        self::assertArrayNotHasKey('permissions', $withoutAccess->json());
    }

    #[Test]
    public function usersCanBeSortedByNameButNeverByAHiddenColumn(): void
    {
        $token = $this->loginSystemAdminUser();
        // Scoped by a unique marker so the seeded users never enter the list.
        $marker = Str::lower(AppUtil::generateUniqueToken());
        $expected = ["{$marker}a", "{$marker}b", "{$marker}c"];
        User::factory()->create(['last_name' => $expected[1]]);
        User::factory()->create(['last_name' => $expected[2]]);
        User::factory()->create(['last_name' => $expected[0]]);

        foreach (['asc' => $expected, 'desc' => array_reverse($expected)] as $direction => $expectedOrder) {
            $this->forgetAuthenticatedUsers();
            $lastNames = collect($this->withToken($token)
                ->get("{$this->resource}?search={$marker}&sortField=last_name&sortDirection={$direction}")
                ->assertOk()
                ->json('data'))
                ->pluck('lastName')
                ->all();

            self::assertSame($expectedOrder, $lastNames);
        }

        foreach (['password', 'remember_token'] as $hiddenColumn) {
            $this->forgetAuthenticatedUsers();
            $this->withToken($token)->get("{$this->resource}?sortField={$hiddenColumn}")
                ->assertBadRequest()
                ->assertExactJson(['success' => false, 'message' => 'The requested sort field is not supported.']);
        }
    }

    #[Test]
    public function aUserRecordNeverCarriesRelationsOrSelectedColumns(): void
    {
        $token = $this->loginSystemAdminUser();
        /** @var User $user */
        $user = User::factory()->create();

        foreach (["{$this->resource}/{$user->id}?relations=roles", "{$this->resource}/{$user->id}?relations=tokens"] as $path) {
            $this->forgetAuthenticatedUsers();
            $this->withToken($token)->get($path)
                ->assertBadRequest()
                ->assertExactJson(['success' => false, 'message' => 'The requested relation is not supported.']);
        }

        $this->forgetAuthenticatedUsers();
        $response = $this->withToken($token)->get("{$this->resource}?columns=" . urlencode('id|password as email'));
        $response->assertBadRequest()->assertExactJson(['success' => false, 'message' => 'Column selection is not supported.']);
        self::assertStringNotContainsString('$2y$', $response->getContent());
    }

    #[Test]
    public function signingUpWithARegisteredEmailLooksLikeANewSignUpAndChangesNothing(): void
    {
        Notification::fake();
        /** @var User $existingUser */
        $existingUser = User::factory()->create();
        $before = $existingUser->refresh()->getRawOriginal();
        $payload = [
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'phoneNumber' => fake()->phoneNumber(),
            'email' => $existingUser->email,
            'password' => 'password',
            'passwordConfirmation' => 'password'
        ];

        // Identical to a first sign-up, so the endpoint cannot tell anyone which emails are registered.
        $this->post("{$this->resource}/sign-up", $payload)
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Sign up successful. Please check your email for verification link.']);

        self::assertSame(1, User::withTrashed()->where('email', $existingUser->email)->count());
        self::assertSame($before, $existingUser->refresh()->getRawOriginal());
        Notification::assertNothingSent();
    }

    #[Test]
    public function signingUpWithAnEmailInADifferentCaseIsTheSameAccount(): void
    {
        Notification::fake();
        /** @var User $existingUser */
        $existingUser = User::factory()->create();

        $this->post("{$this->resource}/sign-up", [
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'phoneNumber' => fake()->phoneNumber(),
            'email' => Str::upper($existingUser->email),
            'password' => 'password',
            'passwordConfirmation' => 'password'
        ])->assertOk();

        self::assertSame(1, User::withTrashed()->where('email', $existingUser->email)->count());
        Notification::assertNothingSent();
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidBirthdates(): array
    {
        return [
            'not a date' => ['not-a-date'],
            'impossible date' => ['2024-02-30'],
            'number' => [12345],
            'relative offset into the far future' => ['1990-01-01 +5000000 years'],
            'relative offset before year zero' => ['2024-01-01 -3000 years'],
            'in the future' => ['2999-01-01'],
        ];
    }

    #[Test]
    #[DataProvider('invalidBirthdates')]
    public function anInvalidBirthdateIsAValidationErrorNotAServerError(mixed $birthdate): void
    {
        $token = $this->loginSystemAdminUser();
        /** @var User $user */
        $user = User::factory()->create();

        $before = $user->refresh()->getRawOriginal();

        $this->withToken($token)->put("{$this->resource}/{$user->id}", [
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'phoneNumber' => fake()->phoneNumber(),
            'birthdate' => $birthdate
        ])->assertBadRequest()->assertJson(['success' => false])->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'birthdate'));

        self::assertSame($before, $user->refresh()->getRawOriginal());
    }

    #[Test]
    public function signUpIsRateLimitedPerClient(): void
    {
        Notification::fake();
        $this->withMiddleware(ThrottleRequests::class);
        $limit = (int) config('custom.rate_limits.sensitive');

        foreach (range(1, $limit + 1) as $attempt) {
            $response = $this->postJson("{$this->resource}/sign-up", [
                'firstName' => fake()->firstName(),
                'lastName' => fake()->lastName(),
                'phoneNumber' => fake()->phoneNumber(),
                'email' => "throttle{$attempt}@example.com",
                'password' => 'password',
                'passwordConfirmation' => 'password'
            ]);

            $response->assertStatus($attempt <= $limit ? 200 : 429);
        }
    }

    #[Test]
    public function signingUpWithTheEmailOfAUserDeletedOutsideARequestIsStillTheGenericAnswer(): void
    {
        // Deleted with no signed-in user (a console command or job), the row keeps its email, and the
        // unique index still covers it.
        Notification::fake();
        /** @var User $deletedUser */
        $deletedUser = User::factory()->create();
        $deletedUser->delete();
        self::assertSame($deletedUser->email, User::withTrashed()->find($deletedUser->id)->email);

        $this->postJson("{$this->resource}/sign-up", [
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'phoneNumber' => fake()->phoneNumber(),
            'email' => $deletedUser->email,
            'password' => 'password',
            'passwordConfirmation' => 'password'
        ])->assertOk()->assertJson(['success' => true]);

        Notification::assertNothingSent();
    }

    #[Test]
    public function aUsernameHeldByADeletedUserIsNotReusedAndTheSignUpSucceeds(): void
    {
        Notification::fake();
        /** @var User $deletedUser */
        $deletedUser = User::factory()->create(['username' => 'jay', 'email' => 'jay@old.test']);
        $deletedUser->delete();

        $this->postJson("{$this->resource}/sign-up", [
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'phoneNumber' => fake()->phoneNumber(),
            'email' => 'jay@new.test',
            'password' => 'password',
            'passwordConfirmation' => 'password'
        ])->assertOk();

        /** @var User $newUser */
        $newUser = User::query()->where('email', 'jay@new.test')->firstOrFail();
        self::assertNotSame('jay', $newUser->username);
        Notification::assertSentTo($newUser, VerifyEmail::class);
    }

}
