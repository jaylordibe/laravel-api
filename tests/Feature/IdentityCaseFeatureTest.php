<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression cover for the case-sensitivity defect the MySQL -> PostgreSQL move
 * introduced.
 *
 * MySQL's `utf8mb4_unicode_ci` collation made `where('email', ?)` and the
 * `users_email_unique` index case-insensitive for free. PostgreSQL compares
 * `varchar` case-sensitively, so without normalisation the same address
 * registered as `Jay@Example.com` and `jay@example.com` produces TWO accounts
 * that the unique index accepts, and a sign-in typed in the wrong case fails
 * with "Invalid username or password".
 *
 * Nothing errored, nothing logged, and the test suite was green — which is why
 * these tests exist rather than a comment.
 */
class IdentityCaseFeatureTest extends TestCase
{

    #[Test]
    public function emailIsStoredLowerCasedWhateverCaseIsSupplied(): void
    {
        $user = User::factory()->create(['email' => 'MixedCase' . Str::random(6) . '@Example.COM']);

        self::assertSame(Str::lower($user->getAttributes()['email']), $user->getAttributes()['email']);
        self::assertStringNotContainsString('Example.COM', $user->getAttributes()['email']);
    }

    #[Test]
    public function usernameIsStoredLowerCasedWhateverCaseIsSupplied(): void
    {
        $user = User::factory()->create(['username' => 'MixedUser' . Str::random(6)]);

        self::assertSame(Str::lower($user->getAttributes()['username']), $user->getAttributes()['username']);
    }

    #[Test]
    public function emailIsTrimmedOnWrite(): void
    {
        $email = '  spaced' . Str::random(6) . '@example.com  ';
        $user = User::factory()->create(['email' => $email]);

        self::assertSame(trim(Str::lower($email)), $user->getAttributes()['email']);
    }

    #[Test]
    public function anExistingEmailIsFoundRegardlessOfTheCaseQueriedWith(): void
    {
        // The duplicate-account half of the defect: this check returning false
        // for a differing-case address is what let a second account through.
        $email = 'Lookup' . Str::random(6) . '@Example.com';
        User::factory()->create(['email' => $email]);

        $repository = app(\App\Repositories\UserRepository::class);

        self::assertTrue($repository->isEmailExists($email));
        self::assertTrue($repository->isEmailExists(Str::lower($email)));
        self::assertTrue($repository->isEmailExists(Str::upper($email)));
    }

    #[Test]
    public function anExistingUsernameIsFoundRegardlessOfTheCaseQueriedWith(): void
    {
        $username = 'LookupUser' . Str::random(6);
        User::factory()->create(['username' => $username]);

        $repository = app(\App\Repositories\UserRepository::class);

        self::assertTrue($repository->isUsernameExists($username));
        self::assertTrue($repository->isUsernameExists(Str::upper($username)));
    }

    #[Test]
    public function signInSucceedsWhenTheEmailCaseDiffersFromRegistration(): void
    {
        // The headline symptom: a correct password rejected because the user
        // capitalised their own address differently than at sign-up.
        $email = 'SignIn' . Str::random(6) . '@Example.com';
        User::factory()->create([
            'email' => $email,
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $token = $this->login(Str::upper($email));

        self::assertNotEmpty($token, 'Sign-in must not depend on the case of the email typed.');
    }

    #[Test]
    public function aUserCanReSubmitTheirOwnEmailInADifferentCase(): void
    {
        // Regression: the update paths compared RAW input against the CANONICAL
        // stored value, so submitting your own address in different case failed
        // the equality check, then matched your own row in the existence check,
        // and returned "Failed to update email" for an address you already own.
        $email = 'Self' . Str::random(6) . '@Example.com';
        $user = User::factory()->create(['email' => $email, 'email_verified_at' => now()]);

        $repository = app(\App\Repositories\UserRepository::class);
        $result = $repository->updateEmail($user->id, Str::upper($email));

        self::assertNotNull($result, 'Re-submitting your own email in another case must not be rejected.');
        self::assertSame(Str::lower($email), $result->email);
    }

    #[Test]
    public function aUserCanReSubmitTheirOwnUsernameInADifferentCase(): void
    {
        $username = 'SelfUser' . Str::random(6);
        $user = User::factory()->create(['username' => $username]);

        $repository = app(\App\Repositories\UserRepository::class);
        $result = $repository->updateUsername($user->id, Str::upper($username));

        self::assertNotNull($result);
        self::assertSame(Str::lower($username), $result->username);
    }

    #[Test]
    public function aUsernameAlreadyOwnedByAnotherUserIsStillRejected(): void
    {
        // The guard above must not weaken the uniqueness check for OTHER users.
        $taken = 'Taken' . Str::random(6);
        User::factory()->create(['username' => $taken]);
        $other = User::factory()->create();

        $repository = app(\App\Repositories\UserRepository::class);

        self::assertNull($repository->updateUsername($other->id, Str::upper($taken)));
    }

    #[Test]
    public function userSearchIsCaseInsensitiveOnPostgres(): void
    {
        // The other half of the same engine change, via DatabaseUtil's ILIKE.
        // DatabaseUtilTest only asserts the operator STRING; this proves the
        // query actually matches.
        $token = $this->loginSystemAdminUser();
        $marker = 'Zeta' . Str::random(8);
        User::factory()->create(['last_name' => $marker]);

        $response = $this->withToken($token)
            ->getJson('/api/users?search=' . Str::lower($marker))
            ->assertOk();

        self::assertNotEmpty(
            $response->json('data'),
            'A lower-cased search term must match a mixed-case stored value.'
        );
    }

    #[Test]
    public function searchTreatsUnderscoreAsALiteralNotAWildcard(): void
    {
        // Unescaped, `_` matches any single character — so "no results" silently
        // became "every row".
        $token = $this->loginSystemAdminUser();
        User::factory()->create(['last_name' => 'Alpha' . Str::random(6)]);

        $response = $this->withToken($token)
            ->getJson('/api/users?search=' . urlencode('_'))
            ->assertOk();

        self::assertEmpty(
            $response->json('data'),
            'A bare underscore must be matched literally, not as a single-character wildcard.'
        );
    }

}
