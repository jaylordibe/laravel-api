<?php

namespace Tests\Feature;

use App\Constants\UserConstant;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Http\Requests\UpdateUsernameRequest;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Validation of the self-service username and profile endpoints: every persisted field has a rule, input
 * is normalised before it is checked, and nothing outside the profile can be changed through it.
 */
class UserProfileValidationFeatureTest extends TestCase
{

    /**
     * A complete, valid profile payload.
     *
     * @return array<string, mixed>
     */
    private function validProfile(): array
    {
        return [
            'firstName' => 'Ada',
            'middleName' => 'King',
            'lastName' => 'Lovelace',
            'phoneNumber' => '+639171234567',
            'gender' => Gender::FEMALE->value,
            'birthdate' => now()->subYears(30)->startOfDay()->toISOString(),
            'timezone' => 'Asia/Manila',
            'address' => '12 St James Square, London'
        ];
    }

    #[Test]
    public function aUsernameIsTrimmedAndLowerCased(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user);
        $username = 'New.User_' . Str::random(6);

        $this->putJson('/api/users/auth/username', ['username' => "  {$username}  "])
            ->assertOk()
            ->assertJson(['username' => Str::lower($username)]);
    }

    #[Test]
    public function resubmittingYourOwnUsernameSucceeds(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['username' => 'self' . Str::lower(Str::random(8))]);
        Passport::actingAs($user);

        $this->putJson('/api/users/auth/username', ['username' => Str::upper($user->username)])
            ->assertOk()
            ->assertJson(['username' => $user->username]);
    }

    public static function invalidUsernames(): array
    {
        return [
            'missing' => [null],
            'blank' => ['   '],
            'too short' => ['ab'],
            'too long' => [str_repeat('a', UserConstant::MAX_USERNAME_LENGTH + 1)],
            'contains @ (would be read as an email at sign-in)' => ['someone@example.com'],
            'contains a space' => ['two words'],
            'contains other punctuation' => ['name!'],
            'not a string' => [['name']],
        ];
    }

    #[Test]
    #[DataProvider('invalidUsernames')]
    public function anInvalidUsernameIsAValidationError(mixed $username): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->putJson('/api/users/auth/username', ['username' => $username])
            ->assertBadRequest()
            ->assertJson(['success' => false]);

        self::assertSame($user->username, $user->refresh()->username);
    }

    #[Test]
    public function aUsernameAnotherAccountHoldsIsRejected(): void
    {
        /** @var User $taken */
        $taken = User::factory()->create(['username' => 'taken' . Str::lower(Str::random(8))]);
        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->putJson('/api/users/auth/username', ['username' => Str::upper($taken->username)])
            ->assertBadRequest()
            ->assertJson(['success' => false, 'message' => 'The username has already been taken.']);
    }

    #[Test]
    public function losingAUsernameRaceIsAClientErrorNotAServerError(): void
    {
        // Validation and the repository check both pass; the unique index catches the race.
        /** @var User $taken */
        $taken = User::factory()->create(['username' => 'race' . Str::lower(Str::random(8))]);
        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user);
        $this->partialMock(UserRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isUsernameExists')->once()->andReturnFalse();
        });
        $request = new class extends UpdateUsernameRequest {

            public function rules(): array
            {
                return ['username' => ['required', 'string']];
            }

        };
        $this->app->bind(UpdateUsernameRequest::class, fn () => $request);

        $this->putJson('/api/users/auth/username', ['username' => $taken->username])
            ->assertBadRequest()
            ->assertJson(['success' => false, 'message' => 'The username has already been taken.']);

        self::assertNotSame($taken->username, $user->refresh()->username);
    }

    #[Test]
    public function everyProfileFieldIsPersisted(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user);
        $payload = $this->validProfile();

        $this->putJson('/api/users/auth', $payload)->assertOk()->assertJson([
            'firstName' => 'Ada',
            'middleName' => 'King',
            'lastName' => 'Lovelace',
            'gender' => Gender::FEMALE->value,
            'timezone' => 'Asia/Manila',
            'address' => '12 St James Square, London'
        ]);
    }

    #[Test]
    public function blankOptionalFieldsAreStoredAsNull(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['address' => 'Somewhere']);
        Passport::actingAs($user);
        $payload = ['middleName' => '  ', 'gender' => '', 'timezone' => '', 'address' => ''] + $this->validProfile();

        $this->putJson('/api/users/auth', $payload)->assertOk();

        $user->refresh();
        self::assertNull($user->middle_name);
        self::assertNull($user->gender);
        self::assertNull($user->timezone);
        self::assertNull($user->address);
    }

    public static function invalidProfiles(): array
    {
        return [
            'blank first name' => [['firstName' => '   ']],
            'blank last name' => [['lastName' => '']],
            'blank phone number' => [['phoneNumber' => ' ']],
            'first name too long' => [['firstName' => str_repeat('a', 256)]],
            'middle name too long' => [['middleName' => str_repeat('a', 256)]],
            'last name too long' => [['lastName' => str_repeat('a', 256)]],
            'phone number too long' => [['phoneNumber' => str_repeat('1', 256)]],
            'unknown gender' => [['gender' => 'unknown']],
            'gender not a string' => [['gender' => ['female']]],
            'unknown timezone' => [['timezone' => 'Mars/Olympus_Mons']],
            'timezone not a string' => [['timezone' => 8]],
            'address too long' => [['address' => str_repeat('a', 1001)]],
            'address not a string' => [['address' => ['line' => 'x']]],
        ];
    }

    #[Test]
    #[DataProvider('invalidProfiles')]
    public function anInvalidProfileFieldIsAValidationError(array $override): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user);
        $before = $user->refresh()->getAttributes();

        $this->putJson('/api/users/auth', $override + $this->validProfile())
            ->assertBadRequest()
            ->assertJson(['success' => false]);

        self::assertSame($before, $user->refresh()->getAttributes());
    }

    #[Test]
    public function theProfileEndpointCannotChangeIdentityVerificationOwnershipOrAccess(): void
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create();
        /** @var User $other */
        $other = User::factory()->create();
        Passport::actingAs($user);
        $before = $user->refresh();
        $otherBefore = $other->refresh()->getAttributes();

        $this->putJson('/api/users/auth', [
            'id' => $other->id,
            'username' => 'hijacked' . Str::lower(Str::random(6)),
            'email' => 'hijacked@example.com',
            'emailVerifiedAt' => now()->toISOString(),
            'email_verified_at' => now()->toISOString(),
            'password' => 'new-password',
            'pendingEmail' => 'hijacked@example.com',
            'profileImage' => 'https://evil.example/avatar.png',
            'roles' => [UserRole::SYSTEM_ADMIN->value],
            'createdBy' => $other->id,
        ] + $this->validProfile())->assertOk()->assertJson(['id' => $user->id]);

        $user->refresh();
        self::assertSame($before->username, $user->username);
        self::assertSame($before->email, $user->email);
        self::assertNull($user->email_verified_at);
        self::assertNull($user->pending_email);
        self::assertNull($user->profile_image);
        self::assertTrue(Hash::check('password', $user->password));
        self::assertFalse($user->hasRole(UserRole::SYSTEM_ADMIN->value));
        self::assertSame($before->created_by, $user->created_by);
        self::assertSame($otherBefore, $other->refresh()->getAttributes());
    }

}
