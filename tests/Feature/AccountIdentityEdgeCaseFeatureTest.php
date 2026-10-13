<?php

namespace Tests\Feature;

use App\Constants\UserConstant;
use App\Http\Requests\SignUpUserRequest;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\UserService;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Edge cases in how accounts get and lose their identifiers: which addresses are accepted, what usernames are
 * generated, what a soft delete leaves behind, and how a sign-up that loses a uniqueness race answers.
 */
class AccountIdentityEdgeCaseFeatureTest extends TestCase
{

    /**
     * A valid sign-up payload.
     *
     * @param string $email
     *
     * @return array<string, string>
     */
    private function signUpPayload(string $email): array
    {
        return [
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'phoneNumber' => fake()->phoneNumber(),
            'email' => $email,
            'password' => 'password',
            'passwordConfirmation' => 'password'
        ];
    }

    /**
     * An address of the given length that FILTER_VALIDATE_EMAIL accepts.
     *
     * @param int $length
     *
     * @return string
     */
    private function emailOfLength(int $length): string
    {
        $domainLength = $length - strlen('a@') - strlen('.com');
        $labels = [];

        while ($domainLength > 0) {
            $labelLength = min(60, $domainLength);
            $labels[] = Str::lower(Str::random($labelLength));
            $domainLength -= $labelLength + 1;
        }

        return 'a@' . implode('.', $labels) . '.com';
    }

    public static function emailEndpoints(): array
    {
        return [
            'sign-up' => ['POST', '/api/users/sign-up', false],
            'admin create' => ['POST', '/api/users', true],
            'forgot password' => ['POST', '/api/forgot-password', false],
            'reset password' => ['POST', '/api/reset-password', false],
            'resend verification' => ['POST', '/api/email/verification-notification', false],
            'email change' => ['PUT', '/api/users/auth/email', true],
        ];
    }

    #[Test]
    #[DataProvider('emailEndpoints')]
    public function anAddressSignInWouldReadAsAUsernameIsRefusedEverywhere(string $method, string $uri, bool $isAuthenticated): void
    {
        // `someone@localhost` passes Laravel's default `email` rule, but sign-in routes an identifier to the
        // email column only when FILTER_VALIDATE_EMAIL accepts it — so such an account could never sign in.
        Notification::fake();

        if ($isAuthenticated) {
            $this->actingAsSystemAdmin();
        }

        $this->json($method, $uri, $this->signUpPayload('someone@localhost') + ['role' => 'APP_ADMIN', 'token' => 'x', 'currentPassword' => 'password'])
            ->assertBadRequest()
            ->assertJson(['success' => false, 'message' => 'The email field must be a valid email address.']);

        self::assertFalse(User::withTrashed()->where('email', 'someone@localhost')->exists());
    }

    #[Test]
    public function aUserWithTheLongestAcceptedEmailCanBeDeleted(): void
    {
        // The soft-delete suffix used to push a long address past varchar(255), and the delete failed.
        $email = $this->emailOfLength(254);
        /** @var User $user */
        $user = User::factory()->create(['email' => $email, 'username' => Str::lower(Str::random(UserConstant::MAX_USERNAME_LENGTH))]);
        $this->actingAsSystemAdmin();

        $this->deleteJson("/api/users/{$user->id}")->assertOk();

        $deleted = User::withTrashed()->findOrFail($user->id);
        self::assertLessThanOrEqual(255, strlen($deleted->email));
        self::assertStringContainsString("_deleted_{$user->id}_", $deleted->email);
        self::assertStringContainsString("_deleted_{$user->id}_", $deleted->username);
        self::assertFalse(User::withTrashed()->where('email', $email)->exists(), 'The address is free for a new account.');
    }

    #[Test]
    public function deletingAnAccountDropsItsPendingEmailChange(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'pending_email' => 'pending@example.com',
            'pending_email_token' => hash('sha256', 'token'),
            'pending_email_expires_at' => now()->addHour()
        ]);
        Passport::actingAs($user);

        $this->deleteJson('/api/users/auth')->assertOk();

        $deleted = User::withTrashed()->findOrFail($user->id);
        self::assertNull($deleted->pending_email);
        self::assertNull($deleted->pending_email_token);
    }

    public static function emailsForUsernames(): array
    {
        return [
            'all-symbol local part' => ['...@example.com'],
            'two-character local part' => ['ab@example.com'],
            'mixed case and symbols' => ['Jay.Lord+Tag@Example.com'],
            'very long local part' => [str_repeat('x', 64) . '@example.com'],
        ];
    }

    #[Test]
    #[DataProvider('emailsForUsernames')]
    public function aGeneratedUsernameAlwaysHasTheAcceptedFormat(string $email): void
    {
        $service = app(UserService::class);

        foreach (range(1, 2) as $attempt) {
            $username = $service->generateUsername($email);

            self::assertMatchesRegularExpression('/^[a-z0-9]{3,' . UserConstant::MAX_USERNAME_LENGTH . '}$/', $username);
            // The second round must step past the first round's name.
            User::factory()->create(['username' => $username]);
        }
    }

    #[Test]
    public function losingASignUpEmailRaceIsAClientErrorNotAServerError(): void
    {
        // The request's unique rule passed, then a concurrent sign-up took the address first.
        Notification::fake();
        /** @var User $existing */
        $existing = User::factory()->create();
        $request = new class extends SignUpUserRequest {

            public function rules(): array
            {
                return ['email' => ['required', 'string']] + array_diff_key(parent::rules(), ['email' => true]);
            }

        };
        $this->app->bind(SignUpUserRequest::class, fn () => $request);

        $this->postJson('/api/users/sign-up', $this->signUpPayload($existing->email))
            ->assertBadRequest()
            ->assertExactJson(['success' => false, 'message' => 'The email has already been taken.']);

        Notification::assertNothingSent();
        self::assertSame(1, User::withTrashed()->where('email', $existing->email)->count());
    }

    #[Test]
    public function losingASignUpUsernameRaceIsAClientErrorNotAServerError(): void
    {
        Notification::fake();
        $this->partialMock(UserRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isUsernameExists')->andReturnFalse();
        });
        User::factory()->create(['username' => 'racer']);

        $this->postJson('/api/users/sign-up', $this->signUpPayload('racer@example.com'))
            ->assertBadRequest()
            ->assertExactJson(['success' => false, 'message' => 'Sign up failed. Please try again.']);

        Notification::assertNothingSent();
    }

    #[Test]
    public function passwordCheckingRoutesArePerUserAndThePublicConfirmIsPerIp(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->mapWithKeys(fn (Route $route): array => [implode('|', $route->methods()) . ' ' . $route->uri() => $route]);

        foreach (['PUT api/users/auth/email', 'PUT api/users/auth/password', 'PUT api/users/{userId}/password'] as $route) {
            self::assertContains('throttle:step-up', $routes[$route]->gatherMiddleware(), "{$route} must be on the per-user step-up limiter.");
        }

        self::assertContains('throttle:sensitive', $routes['POST api/email/change/confirm']->gatherMiddleware());
    }

    #[Test]
    public function aSharedAddressCannotUseUpAnotherUsersStepUpBudget(): void
    {
        $this->withMiddleware(ThrottleRequests::class);
        $limit = (int) config('custom.rate_limits.sensitive');
        Notification::fake();

        // Someone behind the same NAT exhausts the IP-keyed budget on public routes.
        foreach (range(1, $limit + 1) as $attempt) {
            $this->postJson('/api/forgot-password', ['email' => "nobody{$attempt}@example.com"]);
        }
        $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com'])->assertStatus(429);

        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user);
        $wrongPassword = ['currentPassword' => 'wrong', 'password' => 'a-new-password', 'passwordConfirmation' => 'a-new-password'];

        // The signed-in user still has their own budget, and it is enforced.
        foreach (range(1, $limit) as $attempt) {
            $this->putJson('/api/users/auth/password', $wrongPassword)->assertBadRequest();
        }
        $this->putJson('/api/users/auth/password', $wrongPassword)->assertStatus(429);

        // Another user on the same address is unaffected by the first one's budget.
        Passport::actingAs(User::factory()->create());
        $this->putJson('/api/users/auth/password', $wrongPassword)->assertBadRequest();
    }

    #[Test]
    public function aDeleteWithNobodySignedInStillDropsThePendingChange(): void
    {
        // A console or queued delete keeps the identifiers reserved, but never a confirmable change.
        /** @var User $user */
        $user = User::factory()->create([
            'pending_email' => 'pending@example.com',
            'pending_email_token' => hash('sha256', 'token'),
            'pending_email_expires_at' => now()->addHour()
        ]);

        app(UserRepository::class)->delete($user->id);

        $deleted = User::withTrashed()->findOrFail($user->id);
        self::assertNull($deleted->pending_email);
        self::assertNull($deleted->pending_email_token);
        self::assertSame($user->email, $deleted->email);
    }

    #[Test]
    public function theStepUpBudgetIsPerUserWithRealBearerTokens(): void
    {
        // Real tokens, not Passport::actingAs, so the limiter sees the user only if auth ran before it.
        $this->withMiddleware(ThrottleRequests::class);
        $limit = (int) config('custom.rate_limits.sensitive');
        /** @var User $first */
        $first = User::factory()->create();
        /** @var User $second */
        $second = User::factory()->create();
        $firstToken = $this->login($first->email);
        $this->forgetAuthenticatedUsers();
        $secondToken = $this->login($second->email);
        $this->forgetAuthenticatedUsers();
        $wrongPassword = ['currentPassword' => 'wrong', 'password' => 'a-new-password', 'passwordConfirmation' => 'a-new-password'];

        foreach (range(1, $limit) as $attempt) {
            $this->withToken($firstToken)->putJson('/api/users/auth/password', $wrongPassword)->assertBadRequest();
            $this->forgetAuthenticatedUsers();
        }

        $this->withToken($firstToken)->putJson('/api/users/auth/password', $wrongPassword)->assertStatus(429);
        $this->forgetAuthenticatedUsers();
        $this->withToken($secondToken)->putJson('/api/users/auth/password', $wrongPassword)->assertBadRequest();
    }

    #[Test]
    public function aFailedDeleteHookRollsBackTheSoftDelete(): void
    {
        // The soft delete and the hook that frees its identifiers commit together or not at all.
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAsSystemAdmin();
        User::deleted(function (): void {
            throw new RuntimeException('Simulated failure after deleted_at was written.');
        });

        try {
            app(UserRepository::class)->delete($user->id);
            self::fail('The delete should have failed.');
        } catch (RuntimeException) {
        }

        self::assertNull(User::withTrashed()->findOrFail($user->id)->deleted_at);
    }

}
