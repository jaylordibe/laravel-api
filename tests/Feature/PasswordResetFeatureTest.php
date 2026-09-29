<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetFeatureTest extends TestCase
{

    #[Test]
    public function aResetLinkIsSentAndOpensTheClientApp(): void
    {
        Notification::fake();
        /** @var User $user */
        $user = User::factory()->create();

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'If that email has an account, a reset link has been sent.']);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, config('custom.app_frontend_url') . '/reset-password?')
                && str_contains($url, 'token=' . $notification->token)
                && str_contains($url, 'email=' . urlencode($user->email));
        });
    }

    #[Test]
    public function anUnknownEmailGetsTheSameAnswerAndNothingIsSent(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', ['email' => 'nobody@example.test'])
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'If that email has an account, a reset link has been sent.']);

        Notification::assertNothingSent();
    }

    #[Test]
    public function theResetEmailIsQueued(): void
    {
        self::assertInstanceOf(ShouldQueue::class, new ResetPasswordNotification('token'));
    }

    #[Test]
    public function aValidTokenResetsThePasswordAndSignsOutEverySession(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $sessionToken = $this->login($user->email);
        $resetToken = Password::createToken($user);

        $this->forgetAuthenticatedUsers();
        $this->postJson('/api/reset-password', [
            'token' => $resetToken,
            'email' => $user->email,
            'password' => 'brand-new-password',
            'passwordConfirmation' => 'brand-new-password'
        ])->assertOk()->assertExactJson(['success' => true, 'message' => 'Password reset successfully.']);

        self::assertTrue(Hash::check('brand-new-password', $user->refresh()->password));
        $this->forgetAuthenticatedUsers();
        $this->withToken($sessionToken)->getJson('/api/users/auth')->assertUnauthorized();
        $this->forgetAuthenticatedUsers();
        $this->login($user->email, 'brand-new-password');
    }

    #[Test]
    public function aTokenCannotBeUsedTwiceOrGuessed(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $resetToken = Password::createToken($user);
        $payload = ['email' => $user->email, 'password' => 'brand-new-password', 'passwordConfirmation' => 'brand-new-password'];

        $this->postJson('/api/reset-password', ['token' => 'not-the-token'] + $payload)
            ->assertBadRequest()
            ->assertJson(['success' => false, 'message' => __(Password::INVALID_TOKEN)]);
        self::assertTrue(Hash::check('password', $user->refresh()->password));

        $this->postJson('/api/reset-password', ['token' => $resetToken] + $payload)->assertOk();
        $this->postJson('/api/reset-password', ['token' => $resetToken] + $payload)->assertBadRequest();
    }

    #[Test]
    public function aResetNeedsAMatchingConfirmation(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->postJson('/api/reset-password', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'brand-new-password',
            'passwordConfirmation' => 'something-else'
        ])->assertBadRequest()->assertJson(['success' => false]);

        self::assertTrue(Hash::check('password', $user->refresh()->password));
    }

}
