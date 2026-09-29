<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthFeatureTest extends TestCase
{

    private string $resource = '/api/auth';

    #[Test]
    public function testSignIn(): void
    {
        $payload = [
            'identifier' => config('custom.sysad_email'),
            'password' => config('custom.sysad_password')
        ];
        $response = $this->post("{$this->resource}/sign-in", $payload);

        $response->assertOk()->assertJsonStructure(['token', 'expiresIn']);
    }

    /**
     * The token carries the configured lifetime (read from the JWT: `travel()` does not move Passport's clock).
     */
    #[Test]
    public function signInTokenLivesForTheConfiguredLifetime(): void
    {
        $lifetimeSeconds = config('custom.auth.token_ttl_minutes') * 60;

        $response = $this->post("{$this->resource}/sign-in", [
            'identifier' => config('custom.sysad_email'),
            'password' => config('custom.sysad_password')
        ])->assertOk();

        // `exp` and `iat` are microsecond timestamps taken at slightly different instants while the
        // token is minted, so compare with a few seconds' tolerance — ample to tell the configured
        // lifetime from Passport's one-year default or the old 90 days.
        $claims = $this->decodeJwtClaims((string) $response->json('token'));
        self::assertEqualsWithDelta($lifetimeSeconds, $claims['exp'] - $claims['iat'], 5);

        $expiresIn = $response->json('expiresIn');
        self::assertIsInt($expiresIn, 'expiresIn is a number of seconds, not a string.');
        self::assertEqualsWithDelta($lifetimeSeconds, $expiresIn, 10);
    }

    /**
     * Sign-out-all ends every session of the caller — including the one that asked — and nobody
     * else's.
     */
    #[Test]
    public function signOutAllRevokesEverySessionOfTheCallerOnly(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();

        $firstToken = $this->freshLogin($user->email);
        $secondToken = $this->freshLogin($user->email);
        $otherToken = $this->freshLogin($otherUser->email);

        $this->forgetAuthenticatedUsers();
        $this->withToken($firstToken)->post("{$this->resource}/sign-out-all")
            ->assertOk()
            ->assertJson(['success' => true]);

        foreach ([$firstToken, $secondToken] as $revokedToken) {
            $this->forgetAuthenticatedUsers();
            $this->withToken($revokedToken)->get('/api/users/auth')->assertUnauthorized();
        }

        $this->forgetAuthenticatedUsers();
        $this->withToken($otherToken)->get('/api/users/auth')->assertOk();
    }

    #[Test]
    public function signOutAllRequiresAToken(): void
    {
        $this->post("{$this->resource}/sign-out-all")->assertUnauthorized();
    }

    #[Test]
    public function testSignOut(): void
    {
        $token = $this->loginSystemAdminUser();
        $response = $this->withToken($token)->post("{$this->resource}/sign-out");

        $response->assertOk()->assertJsonStructure(['success']);
    }

    /**
     * Passport's `/oauth/*` routes stay unregistered (matched by the `passport.` route-name prefix).
     */
    #[Test]
    public function passportHttpRoutesAreNotRegistered(): void
    {
        $passportRoutes = collect(Route::getRoutes()->getRoutes())
            ->map(fn (RoutingRoute $route): string => (string) $route->getName())
            ->filter(fn (string $name): bool => Str::startsWith($name, 'passport.'))
            ->values()
            ->all();

        self::assertSame([], $passportRoutes);

        $this->get('/oauth/authorize')->assertNotFound();
        $this->get('/oauth/device')->assertNotFound();
        $this->post('/oauth/token', ['grant_type' => 'client_credentials'])
            ->assertNotFound()
            ->assertDontSee('grant', false);
    }

    /**
     * The token sign-in issues still authenticates once Passport's routes are gone, because
     * createToken() and the `api` guard never depended on them.
     */
    #[Test]
    public function signInTokenAuthenticatesWithoutPassportRoutes(): void
    {
        $token = $this->loginSystemAdminUser();

        $this->withToken($token)->get('/api/users/auth')->assertOk();
    }

    /**
     * Sign in afresh within one test.
     *
     * @param string $email
     *
     * @return string
     */
    private function freshLogin(string $email): string
    {
        $this->forgetAuthenticatedUsers();

        return $this->login($email);
    }

    /**
     * The claims of a JWT, read without verifying its signature — only the lifetime is inspected.
     *
     * @param string $jwt
     *
     * @return array<string, mixed>
     */
    private function decodeJwtClaims(string $jwt): array
    {
        $payload = explode('.', $jwt)[1] ?? '';

        return (array) json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    }

    #[Test]
    public function signInIsRateLimitedPerAccountAndIp(): void
    {
        $this->withMiddleware(ThrottleRequests::class);
        $limit = (int) config('custom.rate_limits.sensitive');

        foreach (range(1, $limit + 1) as $attempt) {
            $this->postJson("{$this->resource}/sign-in", ['identifier' => 'someone@example.test', 'password' => 'wrong'])
                ->assertStatus($attempt <= $limit ? 400 : 429);
        }

        // Someone else on the same IP is not locked out.
        $this->postJson("{$this->resource}/sign-in", ['identifier' => 'someone-else@example.test', 'password' => 'wrong'])
            ->assertBadRequest();
    }

}
