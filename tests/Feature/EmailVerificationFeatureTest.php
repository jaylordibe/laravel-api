<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Utils\AppUtil;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The verification link is opened from an email, with no signed-in user: the signed URL is the
 * only credential. Sign-in refuses unverified users, so if this flow breaks, nobody who signs up
 * can ever sign in.
 */
class EmailVerificationFeatureTest extends TestCase
{

    /**
     * Sign up through the API and return the new user and the link the real notification carries.
     *
     * @return array{User, string}
     */
    private function signUpAndCaptureLink(): array
    {
        Notification::fake();
        $email = AppUtil::generateUniqueToken() . fake()->unique()->safeEmail();

        $this->post('/api/users/sign-up', [
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'email' => $email,
            'phoneNumber' => fake()->phoneNumber(),
            'password' => 'password',
            'passwordConfirmation' => 'password'
        ])->assertOk();

        /** @var User $user */
        // Stored lower-cased — the canonical form User keeps.
        $user = User::where('email', Str::lower($email))->firstOrFail();
        $link = '';
        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user, &$link): bool {
            $link = $notification->toMail($user)->actionUrl;

            return true;
        });

        return [$user, $this->pathAndQuery($link)];
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
     * A correctly signed link carrying the given hash.
     *
     * @param int $userId
     * @param string $hash
     *
     * @return string
     */
    private function signedLink(int $userId, string $hash): string
    {
        return $this->pathAndQuery(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $userId, 'hash' => $hash]));
    }

    #[Test]
    public function aGuestCanVerifyWithTheEmailedLinkAndThenSignIn(): void
    {
        [$user, $link] = $this->signUpAndCaptureLink();

        $this->get($link)->assertOk()->assertJson(['success' => true, 'message' => 'Email verified successfully.']);

        self::assertNotNull($user->refresh()->email_verified_at);
        $this->login($user->email);
    }

    #[Test]
    public function openingTheLinkAgainStillSucceeds(): void
    {
        [$user, $link] = $this->signUpAndCaptureLink();

        $this->get($link)->assertOk();
        $this->get($link)->assertOk()->assertJson(['success' => true]);
    }

    #[Test]
    public function aTamperedSignatureIsRejected(): void
    {
        [$user, $link] = $this->signUpAndCaptureLink();

        $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature=' . str_repeat('0', 64), $link))
            ->assertBadRequest()
            ->assertJson(['success' => false, 'message' => 'Invalid verification link.']);

        self::assertNull($user->refresh()->email_verified_at);
    }

    #[Test]
    public function anExpiredLinkIsRejected(): void
    {
        [$user, $link] = $this->signUpAndCaptureLink();

        $this->travel(config('auth.verification.expire', 60) + 1)->minutes();
        $this->get($link)->assertBadRequest()->assertJson(['message' => 'Invalid verification link.']);

        self::assertNull($user->refresh()->email_verified_at);
    }

    #[Test]
    public function aLinkForAnotherAddressIsRejected(): void
    {
        // Validly signed, but issued for an address the account no longer has — e.g. before an
        // email change. It must not verify the current address.
        [$user] = $this->signUpAndCaptureLink();

        $this->get($this->signedLink($user->id, sha1('previous@example.test')))
            ->assertBadRequest()
            ->assertJson(['message' => 'Invalid verification link.']);

        self::assertNull($user->refresh()->email_verified_at);
    }

    #[Test]
    public function aNonNumericIdDoesNotMatchTheRoute(): void
    {
        // Control first: the same path with a numeric id resolves (and fails on its signature), so
        // the 404 below can only come from the numeric constraint, not from a renamed route.
        $this->get('/api/email/verify/123?hash=x')->assertBadRequest()->assertJson(['message' => 'Invalid verification link.']);
        $this->get('/api/email/verify/abc?hash=x')->assertNotFound();
    }

    #[Test]
    public function aValidSignatureForAMissingUserRevealsNothing(): void
    {
        $missingId = (int) User::withTrashed()->max('id') + 1000;

        $this->get($this->signedLink($missingId, sha1('nobody@example.test')))
            ->assertBadRequest()
            ->assertJson(['message' => 'Invalid verification link.']);
    }

    #[Test]
    public function theVerificationEmailIsQueued(): void
    {
        self::assertInstanceOf(ShouldQueue::class, new VerifyEmailNotification());
    }

    #[Test]
    public function anAdminCreatedUserIsSentAVerificationLinkAndCanThenSignIn(): void
    {
        Notification::fake();
        $this->actingAsSystemAdmin();

        $this->post('/api/users', [
            'firstName' => 'Invited',
            'lastName' => 'User',
            'phoneNumber' => '5550100',
            'email' => 'invited@example.test',
            'password' => 'invited-password',
            'passwordConfirmation' => 'invited-password',
            'role' => UserRole::APP_ADMIN->value
        ])->assertCreated();

        /** @var User $user */
        $user = User::where('email', 'invited@example.test')->firstOrFail();
        $link = '';
        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user, &$link): bool {
            $link = $notification->toMail($user)->actionUrl;

            return true;
        });

        $this->get($this->pathAndQuery($link))->assertOk();
        $this->forgetAuthenticatedUsers();
        $this->login('invited@example.test', 'invited-password');
    }

    #[Test]
    public function aNewLinkCanBeRequestedForAnUnverifiedAccount(): void
    {
        Notification::fake();
        /** @var User $unverifiedUser */
        $unverifiedUser = User::factory()->create(['email_verified_at' => null]);
        /** @var User $verifiedUser */
        $verifiedUser = User::factory()->create();
        $expected = ['success' => true, 'message' => 'If that email needs verification, a new link has been sent.'];

        foreach ([$unverifiedUser->email, $verifiedUser->email, 'nobody@example.test'] as $email) {
            $this->postJson('/api/email/verification-notification', ['email' => $email])->assertOk()->assertExactJson($expected);
        }

        Notification::assertSentTo($unverifiedUser, VerifyEmailNotification::class);
        Notification::assertNotSentTo($verifiedUser, VerifyEmailNotification::class);
    }

}
