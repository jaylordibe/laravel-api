<?php

namespace Tests\Feature;

use App\Data\UpdatePasswordData;
use App\Models\User;
use App\Notifications\EmailChangeRequestedNotification;
use App\Notifications\VerifyEmailChangeNotification;
use App\Repositories\UserRepository;
use App\Utils\AppUtil;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Self-service email change: `PUT users/auth/email` needs the current password and only records a pending
 * address; the account's email changes when the client app posts the token from the link sent to the new
 * address to `POST email/change/confirm`, and that signs out every session.
 */
class EmailChangeFeatureTest extends TestCase
{

    private string $resource = '/api/users/auth/email';

    /**
     * A unique, valid address.
     *
     * @return string
     */
    private function newEmail(): string
    {
        return Str::lower(AppUtil::generateUniqueToken() . fake()->unique()->safeEmail());
    }

    /**
     * Request a change as the given user and return the confirmation payload the emailed link carries.
     *
     * @param User $user
     * @param string $email
     *
     * @return array{userId: string, token: string}
     */
    private function requestChange(User $user, string $email): array
    {
        Notification::fake();
        Passport::actingAs($user);

        $this->putJson($this->resource, ['email' => $email, 'currentPassword' => 'password'])
            ->assertOk()
            ->assertJson(['email' => $user->email, 'pendingEmail' => $email]);

        $link = '';
        Notification::assertSentOnDemand(VerifyEmailChangeNotification::class, function (VerifyEmailChangeNotification $notification, array $channels, object $notifiable) use ($email, &$link): bool {
            $link = $notification->toMail($notifiable)->actionUrl;

            return $notifiable->routes['mail'] === $email;
        });
        $this->forgetAuthenticatedUsers();

        // The link opens the client app, never an API route that changes state on GET.
        self::assertStringStartsWith(config('custom.app_frontend_url') . '/confirm-email-change?', $link);
        parse_str(parse_url($link, PHP_URL_QUERY), $payload);

        return $payload;
    }

    /**
     * Post a confirmation, as the client app does.
     *
     * @param array<string, mixed> $payload
     *
     * @return TestResponse
     */
    private function confirm(array $payload): TestResponse
    {
        return $this->postJson('/api/email/change/confirm', $payload);
    }

    /**
     * The request target of an absolute URL, so the test client sends it to this application.
     *
     * @param string $url
     *
     * @return string
     */
    private function pathAndQuery(string $url): string
    {
        $parts = parse_url($url);

        return $parts['path'] . '?' . ($parts['query'] ?? '');
    }

    /**
     * Assert the confirmation is refused and the account's email is unchanged.
     *
     * @param array<string, mixed> $payload
     * @param User $user
     * @param string $email
     *
     * @return void
     */
    private function assertLinkRefused(array $payload, User $user, string $email): void
    {
        $this->confirm($payload)
            ->assertBadRequest()
            ->assertJson(['success' => false, 'message' => 'This email change link is invalid or has expired.']);
        self::assertSame($email, $user->refresh()->email);
    }

    #[Test]
    public function theCurrentEmailStaysActiveUntilTheNewOneIsConfirmed(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $oldEmail = $user->email;
        $newEmail = $this->newEmail();

        $this->requestChange($user, $newEmail);

        $user->refresh();
        self::assertSame($oldEmail, $user->email);
        self::assertNotNull($user->email_verified_at);
        self::assertSame($newEmail, $user->pending_email);
        // Only a hash of the token is stored.
        self::assertSame(64, strlen($user->pending_email_token));
        $this->login($oldEmail);
        $this->forgetAuthenticatedUsers();
        $this->postJson('/api/auth/sign-in', ['identifier' => $newEmail, 'password' => 'password'])->assertBadRequest();
    }

    #[Test]
    public function confirmingTheLinkChangesTheEmailAndSignsOutEverySession(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $this->login($user->email);
        $this->forgetAuthenticatedUsers();
        $newEmail = $this->newEmail();
        $link = $this->requestChange($user, $newEmail);

        $this->confirm($link)->assertOk()->assertJson(['success' => true]);

        $user->refresh();
        self::assertSame($newEmail, $user->email);
        self::assertNotNull($user->email_verified_at);
        self::assertNull($user->pending_email);
        self::assertNull($user->pending_email_token);
        self::assertNull($user->pending_email_expires_at);

        $this->withToken($token)->getJson('/api/users/auth')->assertUnauthorized();
        $this->forgetAuthenticatedUsers();
        $this->login($newEmail);
    }

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
    public function aChangeNeedsTheCurrentPassword(array $currentPassword): void
    {
        Notification::fake();
        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->putJson($this->resource, ['email' => $this->newEmail()] + $currentPassword)
            ->assertBadRequest()
            ->assertJson(['success' => false]);

        self::assertNull($user->refresh()->pending_email);
        Notification::assertNothingSent();
    }

    #[Test]
    public function aGuestCannotRequestAChange(): void
    {
        $this->putJson($this->resource, ['email' => $this->newEmail(), 'currentPassword' => 'password'])->assertUnauthorized();
    }

    #[Test]
    public function aPasswordWithSurroundingWhitespaceIsNotTrimmedBeforeTheCheck(): void
    {
        Notification::fake();
        /** @var User $user */
        $user = User::factory()->create(['password' => Hash::make('  spaced password  ')]);
        Passport::actingAs($user);

        $this->putJson($this->resource, ['email' => $this->newEmail(), 'currentPassword' => '  spaced password  '])->assertOk();
    }

    #[Test]
    public function theLinkWorksOnce(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $newEmail = $this->newEmail();
        $link = $this->requestChange($user, $newEmail);

        $this->confirm($link)->assertOk()->assertJson(['success' => true]);
        $this->assertLinkRefused($link, $user, $newEmail);
    }

    #[Test]
    public function anExpiredLinkIsRefused(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $link = $this->requestChange($user, $this->newEmail());

        $this->travel((int) config('auth.verification.expire') + 1)->minutes();

        $this->assertLinkRefused($link, $user, $user->email);
    }

    #[Test]
    public function aTamperedTokenIsRefused(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $link = $this->requestChange($user, $this->newEmail());
        $link['token'][0] = $link['token'][0] === 'a' ? 'b' : 'a';

        $this->assertLinkRefused($link, $user, $user->email);
    }

    #[Test]
    public function aGuessedTokenIsRefused(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $link = $this->requestChange($user, $this->newEmail());

        $this->assertLinkRefused(['userId' => $link['userId'], 'token' => Str::random(64)], $user, $user->email);
    }

    #[Test]
    public function aTokenIsBoundToTheUserItWasIssuedFor(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();
        $this->requestChange($other, $this->newEmail());
        $link = $this->requestChange($owner, $this->newEmail());

        $this->assertLinkRefused(['userId' => $other->id, 'token' => $link['token']], $other, $other->email);
        $this->assertLinkRefused(['userId' => $owner->id + 100000, 'token' => $link['token']], $owner, $owner->email);
    }

    public static function malformedConfirmations(): array
    {
        return [
            'nothing' => [[]],
            'no token' => [['userId' => 1]],
            'no user id' => [['token' => str_repeat('a', 64)]],
            'short token' => [['userId' => 1, 'token' => 'abc']],
            'non-numeric user id' => [['userId' => 'abc', 'token' => str_repeat('a', 64)]],
            'token not a string' => [['userId' => 1, 'token' => [str_repeat('a', 64)]]],
        ];
    }

    #[Test]
    #[DataProvider('malformedConfirmations')]
    public function aMalformedConfirmationIsAValidationError(array $payload): void
    {
        $this->confirm($payload)->assertBadRequest()->assertJson(['success' => false]);
    }

    #[Test]
    public function theApiNeverChangesTheEmailOnAGet(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $link = $this->requestChange($user, $this->newEmail());

        $this->get('/api/email/change/confirm?' . http_build_query($link))->assertStatus(405);
        self::assertNotNull($user->refresh()->pending_email);
    }

    #[Test]
    public function theCurrentAddressIsToldAboutTheRequestAtTheAddressItHadThen(): void
    {
        // Routed to the captured address, not to the User: a queued notification on the User is sent to
        // whatever its email is at delivery, which after a quick confirmation would be the new inbox.
        /** @var User $user */
        $user = User::factory()->create();
        $oldEmail = $user->email;
        $newEmail = $this->newEmail();
        $link = $this->requestChange($user, $newEmail);
        $this->confirm($link)->assertOk();

        Notification::assertSentOnDemand(EmailChangeRequestedNotification::class, function (EmailChangeRequestedNotification $notification, array $channels, object $notifiable) use ($oldEmail, $newEmail): bool {
            return $notifiable->routes['mail'] === $oldEmail && $notification->pendingEmail === $newEmail;
        });
        Notification::assertNotSentTo($user, EmailChangeRequestedNotification::class);
        self::assertContains(ShouldQueue::class, class_implements(EmailChangeRequestedNotification::class));
    }

    #[Test]
    public function aRequestHoldsTheRowLockFromThePasswordCheckToTheSave(): void
    {
        // A concurrent password change or reset cannot be raced in a single-connection test, so this pins the
        // structure that orders them: the request locks the user row inside a transaction before it writes.
        Notification::fake();
        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user);
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = ['sql' => Str::lower($query->sql), 'level' => DB::transactionLevel()];
        });

        $this->putJson($this->resource, ['email' => $this->newEmail(), 'currentPassword' => 'password'])->assertOk();

        $lockAt = collect($queries)->search(fn (array $query): bool => str_contains($query['sql'], 'for update') && str_contains($query['sql'], 'users'));
        $saveAt = collect($queries)->search(fn (array $query): bool => str_starts_with($query['sql'], 'update') && str_contains($query['sql'], 'pending_email_token'));
        self::assertNotFalse($lockAt, 'The request must lock the user row.');
        self::assertNotFalse($saveAt);
        self::assertLessThan($saveAt, $lockAt);
        // RefreshDatabase holds level 1; the request's own transaction is deeper and spans both statements.
        self::assertSame($queries[$lockAt]['level'], $queries[$saveAt]['level']);
        self::assertGreaterThan(1, $queries[$lockAt]['level']);
    }

    /**
     * Capture every SQL statement the callback runs, lower-cased, with the transaction level it ran at.
     *
     * @param callable $callback
     *
     * @return array<int, array{sql: string, level: int}>
     */
    private function captureQueries(callable $callback): array
    {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = ['sql' => Str::lower($query->sql), 'level' => DB::transactionLevel()];
        });
        $callback();

        return $queries;
    }

    #[Test]
    public function aConfirmationAppliesTheChangeUnderTheRowLock(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $link = $this->requestChange($user, $this->newEmail());

        $queries = collect($this->captureQueries(fn () => $this->confirm($link)->assertOk()));

        $lockAt = $queries->search(fn (array $query): bool => str_contains($query['sql'], 'for update') && str_contains($query['sql'], 'users'));
        $applyAt = $queries->search(fn (array $query): bool => str_starts_with($query['sql'], 'update "users"') && str_contains($query['sql'], '"email" ='));
        self::assertNotFalse($lockAt, 'Confirmation must lock the user row.');
        self::assertNotFalse($applyAt);
        self::assertLessThan($applyAt, $lockAt);
        self::assertSame($queries[$lockAt]['level'], $queries[$applyAt]['level']);
        self::assertGreaterThan(1, $queries[$lockAt]['level']);
    }

    #[Test]
    public function aWrongTokenNeverTakesTheRowLock(): void
    {
        // The route is public: a guessed token must not be able to hold a user's row.
        /** @var User $user */
        $user = User::factory()->create();
        $this->requestChange($user, $this->newEmail());

        $queries = $this->captureQueries(fn () => $this->confirm(['userId' => $user->id, 'token' => Str::random(64)])->assertBadRequest());

        self::assertEmpty(array_filter($queries, fn (array $query): bool => str_contains($query['sql'], 'for update')));
    }

    #[Test]
    public function thePasswordIsCheckedAgainstTheLockedRow(): void
    {
        // Stands in for a password change that committed after authentication but before the lock was taken.
        Notification::fake();
        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user);
        $this->partialMock(UserRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findByIdForUpdate')->once()->andReturnUsing(function (int $id): User {
                $lockedUser = User::findOrFail($id);
                $lockedUser->password = 'changed-by-a-concurrent-reset';

                return $lockedUser;
            });
        });

        $this->putJson($this->resource, ['email' => $this->newEmail(), 'currentPassword' => 'password'])
            ->assertBadRequest()
            ->assertJson(['message' => 'Current password is incorrect.']);

        self::assertNull($user->refresh()->pending_email);
        Notification::assertNothingSent();
    }

    #[Test]
    public function anAccountDeletedMidRequestIsAClientErrorNotAServerError(): void
    {
        Notification::fake();
        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user);
        $this->partialMock(UserRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findByIdForUpdate')->once()->andReturnNull();
        });

        $this->putJson($this->resource, ['email' => $this->newEmail(), 'currentPassword' => 'password'])
            ->assertBadRequest()
            ->assertJson(['message' => 'User not found.']);

        Notification::assertNothingSent();
    }

    #[Test]
    public function aLinkStillWorksJustBeforeItExpires(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $newEmail = $this->newEmail();
        $link = $this->requestChange($user, $newEmail);

        $this->travel(59)->minutes();

        $this->confirm($link)->assertOk();
        self::assertSame($newEmail, $user->refresh()->email);
    }

    #[Test]
    public function cancellingIsStampedWithTheCaller(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->requestChange($user, $this->newEmail());
        User::whereKey($user->id)->update(['updated_by' => null]);
        Passport::actingAs($user);

        $this->deleteJson("{$this->resource}/pending")->assertOk();

        self::assertSame($user->id, $user->refresh()->updated_by);
    }

    #[Test]
    public function signingOutEverywhereRevokesAndClearsUnderTheRowLock(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->requestChange($user, $this->newEmail());
        $token = $this->login($user->email);
        $this->forgetAuthenticatedUsers();

        $queries = collect($this->captureQueries(fn () => $this->withToken($token)->postJson('/api/auth/sign-out-all')->assertOk()));

        $lockAt = $queries->search(fn (array $query): bool => str_contains($query['sql'], 'for update') && str_contains($query['sql'], 'users'));
        $revokeAt = $queries->search(fn (array $query): bool => str_starts_with($query['sql'], 'update "oauth_access_tokens"'));
        $clearAt = $queries->search(fn (array $query): bool => str_starts_with($query['sql'], 'update "users"') && str_contains($query['sql'], 'pending_email_token'));
        self::assertNotFalse($lockAt);
        self::assertNotFalse($revokeAt);
        self::assertNotFalse($clearAt);
        self::assertLessThan($revokeAt, $lockAt);
        self::assertLessThan($clearAt, $lockAt);
        // RefreshDatabase holds level 1; the lock must be inside the service's own transaction, and so must the
        // revoke (a nested savepoint) and the clear.
        self::assertGreaterThan(1, $queries[$lockAt]['level']);
        self::assertGreaterThanOrEqual($queries[$lockAt]['level'], $queries[$revokeAt]['level']);
        self::assertSame($queries[$lockAt]['level'], $queries[$clearAt]['level']);
    }

    #[Test]
    public function aLinkReplacedAfterThePreCheckIsRefusedUnderTheLock(): void
    {
        // Stands in for a new request or cancellation committing between the unlocked check and the lock.
        /** @var User $user */
        $user = User::factory()->create();
        $link = $this->requestChange($user, $this->newEmail());
        $this->partialMock(UserRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findByIdForUpdate')->once()->andReturnUsing(function (int $id): User {
                $lockedUser = User::findOrFail($id);
                $lockedUser->pending_email_token = hash('sha256', 'a-newer-request');

                return $lockedUser;
            });
        });

        $this->assertLinkRefused($link, $user, $user->email);
    }

    #[Test]
    public function cancellingReturnsTheRowAsSaved(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->requestChange($user, $this->newEmail());
        $this->travel(1)->minutes();
        Passport::actingAs($user);

        $response = $this->deleteJson("{$this->resource}/pending")->assertOk();

        self::assertSame($user->refresh()->updated_at->toISOString(), $response->json('updatedAt'));
    }

    #[Test]
    public function bothNotificationsAreSentOnlyAfterTheTransactionCommits(): void
    {
        self::assertTrue((new VerifyEmailChangeNotification('https://example.test'))->afterCommit);
        self::assertTrue((new EmailChangeRequestedNotification('new@example.test'))->afterCommit);
    }

    #[Test]
    public function aNewPasswordDiscardsThePendingChangeInTheSameStatement(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'pending_email' => 'pending@example.com',
            'pending_email_token' => hash('sha256', 'token'),
            'pending_email_expires_at' => now()->addHour()
        ]);
        $updates = [];
        DB::listen(function (QueryExecuted $query) use (&$updates): void {
            if (str_starts_with(Str::lower($query->sql), 'update') && str_contains($query->sql, 'users')) {
                $updates[] = $query->sql;
            }
        });

        app(UserRepository::class)->updatePassword(new UpdatePasswordData(userId: $user->id, password: 'a-new-password', passwordConfirmation: 'a-new-password', currentPassword: ''));

        self::assertCount(1, $updates);
        self::assertStringContainsString('"password"', $updates[0]);
        self::assertStringContainsString('"pending_email_token"', $updates[0]);
        $user->refresh();
        self::assertTrue(Hash::check('a-new-password', $user->password));
        self::assertNull($user->pending_email);
        self::assertNull($user->pending_email_token);
        self::assertNull($user->pending_email_expires_at);
    }

    #[Test]
    public function theAddressIsNotCheckedForAnAccountUntilThePasswordIsRight(): void
    {
        // Otherwise a bearer token alone could test which addresses are registered.
        Notification::fake();
        /** @var User $taken */
        $taken = User::factory()->create();
        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->putJson($this->resource, ['email' => $taken->email, 'currentPassword' => 'wrong-password'])
            ->assertBadRequest()
            ->assertJson(['message' => 'Current password is incorrect.']);
    }

    #[Test]
    public function aNewRequestInvalidatesTheEarlierLink(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $firstLink = $this->requestChange($user, $this->newEmail());
        $secondEmail = $this->newEmail();
        $secondLink = $this->requestChange($user, $secondEmail);

        $this->assertLinkRefused($firstLink, $user, $user->email);
        $this->confirm($secondLink)->assertOk();
        self::assertSame($secondEmail, $user->refresh()->email);
    }

    #[Test]
    public function reRequestingTheSameAddressInvalidatesTheEarlierLink(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $newEmail = $this->newEmail();
        $firstLink = $this->requestChange($user, $newEmail);
        $secondLink = $this->requestChange($user, $newEmail);

        $this->assertLinkRefused($firstLink, $user, $user->email);
        $this->confirm($secondLink)->assertOk();
    }

    #[Test]
    public function cancellingInvalidatesTheLink(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $link = $this->requestChange($user, $this->newEmail());
        Passport::actingAs($user);

        $this->deleteJson("{$this->resource}/pending")->assertOk()->assertJson(['email' => $user->email, 'pendingEmail' => null]);

        $this->forgetAuthenticatedUsers();
        $this->assertLinkRefused($link, $user, $user->email);
    }

    #[Test]
    public function submittingTheCurrentEmailCancelsAPendingChange(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $link = $this->requestChange($user, $this->newEmail());
        Passport::actingAs($user);

        $this->putJson($this->resource, ['email' => $user->email, 'currentPassword' => 'password'])
            ->assertOk()
            ->assertJson(['pendingEmail' => null]);

        $this->forgetAuthenticatedUsers();
        $this->assertLinkRefused($link, $user, $user->email);
    }

    #[Test]
    public function resettingThePasswordCancelsAPendingChange(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $link = $this->requestChange($user, $this->newEmail());
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-new-password',
            'passwordConfirmation' => 'a-new-password'
        ])->assertOk();

        $this->assertLinkRefused($link, $user, $user->email);
    }

    #[Test]
    public function changingThePasswordCancelsAPendingChange(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $link = $this->requestChange($user, $this->newEmail());
        Passport::actingAs($user);

        $this->putJson('/api/users/auth/password', [
            'currentPassword' => 'password',
            'password' => 'a-new-password',
            'passwordConfirmation' => 'a-new-password'
        ])->assertOk();

        $this->forgetAuthenticatedUsers();
        $this->assertLinkRefused($link, $user, $user->email);
    }

    #[Test]
    public function clearingAPendingChangeDoesNotTrustAStaleModel(): void
    {
        // The model is loaded before a concurrent request records a change; clearing must still remove it.
        /** @var User $user */
        $user = User::factory()->create();
        $stale = User::findOrFail($user->id);
        $link = $this->requestChange($user, $this->newEmail());

        app(UserRepository::class)->clearPendingEmail($stale);

        self::assertNull($user->refresh()->pending_email_token);
        $this->assertLinkRefused($link, $user, $user->email);
    }

    #[Test]
    public function signingOutEverywhereCancelsAPendingChange(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $link = $this->requestChange($user, $this->newEmail());
        $token = $this->login($user->email);
        $this->forgetAuthenticatedUsers();

        $this->withToken($token)->postJson('/api/auth/sign-out-all')->assertOk();

        $this->forgetAuthenticatedUsers();
        $this->assertLinkRefused($link, $user, $user->email);
    }

    #[Test]
    public function aPendingChangeIsShownOnlyToItsOwnerAndOnlyUntilItExpires(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $newEmail = $this->newEmail();
        $this->requestChange($user, $newEmail);

        Passport::actingAs($user);
        $this->getJson('/api/users/auth')->assertOk()->assertJson(['pendingEmail' => $newEmail]);

        // Another user's unconfirmed, user-typed address is not shown to an admin.
        $this->forgetAuthenticatedUsers();
        $this->actingAsSystemAdmin();
        $this->getJson("/api/users/{$user->id}")->assertOk()->assertJson(['pendingEmail' => null, 'pendingEmailExpiresAt' => null]);

        // Once expired it is no longer pending, though the columns are untouched.
        $this->forgetAuthenticatedUsers();
        $this->travel((int) config('auth.verification.expire') + 1)->minutes();
        Passport::actingAs($user);
        $this->getJson('/api/users/auth')->assertOk()->assertJson(['pendingEmail' => null, 'pendingEmailExpiresAt' => null]);
        self::assertSame($newEmail, $user->refresh()->pending_email);
    }

    #[Test]
    public function anAddressAnotherAccountHoldsCannotBeRequested(): void
    {
        Notification::fake();
        /** @var User $taken */
        $taken = User::factory()->create();
        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->putJson($this->resource, ['email' => Str::upper($taken->email), 'currentPassword' => 'password'])
            ->assertBadRequest()
            ->assertJson(['success' => false, 'message' => 'The email has already been taken.']);

        Notification::assertNothingSent();
    }

    #[Test]
    public function anAddressTakenBeforeConfirmationIsRefused(): void
    {
        // Two accounts may both have the same address pending; whoever confirms first wins.
        /** @var User $first */
        $first = User::factory()->create();
        /** @var User $second */
        $second = User::factory()->create();
        $newEmail = $this->newEmail();
        $firstLink = $this->requestChange($first, $newEmail);
        $secondLink = $this->requestChange($second, $newEmail);

        $this->confirm($firstLink)->assertOk();
        $this->assertLinkRefused($secondLink, $second, $second->email);
    }

    #[Test]
    public function aUniqueIndexViolationAtConfirmationIsARefusedLinkNotAServerError(): void
    {
        // Simulates losing the race the pre-check cannot see: the unique index is the last word.
        /** @var User $taken */
        $taken = User::factory()->create();
        /** @var User $user */
        $user = User::factory()->create();
        $link = $this->requestChange($user, $this->newEmail());
        $user->refresh();
        $user->pending_email = $taken->email;
        $user->save();
        $this->partialMock(UserRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isEmailExists')->once()->andReturnFalse();
        });

        $this->assertLinkRefused($link, $user, $user->email);
        // The aborted statement was rolled back to its savepoint; the connection still works.
        self::assertSame($taken->email, $taken->refresh()->email);
    }

    public static function invalidEmails(): array
    {
        return [
            'missing' => [null],
            'blank' => ['   '],
            'not an email' => ['not-an-email'],
            'no domain dot (sign-in would treat it as a username)' => ['someone@localhost'],
            'longer than 254' => [str_repeat('a', 10) . '@' . implode('.', array_fill(0, 4, str_repeat('b', 60))) . '.com'],
            'not a string' => [['someone@example.com']],
        ];
    }

    #[Test]
    #[DataProvider('invalidEmails')]
    public function anInvalidEmailIsAValidationError(mixed $email): void
    {
        Notification::fake();
        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->putJson($this->resource, ['email' => $email, 'currentPassword' => 'password'])
            ->assertBadRequest()
            ->assertJson(['success' => false]);

        self::assertNull($user->refresh()->pending_email);
        Notification::assertNothingSent();
    }

    #[Test]
    public function aPendingChangeDoesNotAffectSignUpVerificationOfTheCurrentAddress(): void
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create();
        $this->requestChange($user, $this->newEmail());
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->get($this->pathAndQuery($link))->assertRedirect(config('custom.app_frontend_url') . '/?verified=1');
        self::assertNotNull($user->refresh()->email_verified_at);
    }

    #[Test]
    public function theConfirmationLinkIsQueuedAndOnlyAHashIsKept(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $link = $this->requestChange($user, $this->newEmail());

        self::assertSame(hash('sha256', $link['token']), $user->refresh()->pending_email_token);
        self::assertContains(ShouldQueue::class, class_implements(VerifyEmailChangeNotification::class));
    }

}
