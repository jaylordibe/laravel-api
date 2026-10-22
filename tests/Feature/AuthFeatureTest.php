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


    /**
     * Fail sign-in with a fresh identifier each time, from the given client address.
     *
     * @param int $count
     * @param string $ip
     * @param array<string, string> $headers
     *
     * @return void
     */
    private function failSignInWithDistinctIdentifiers(int $count, string $ip = '127.0.0.1', array $headers = []): void
    {
        foreach (range(1, $count) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson("{$this->resource}/sign-in", ['identifier' => "sprayed-{$attempt}-" . Str::random(6) . '@example.test', 'password' => 'Password1'], $headers)
                ->assertBadRequest()
                ->assertJson(['message' => 'Invalid username or password']);
        }
    }

    #[Test]
    public function switchingIdentifiersCannotBypassThePerIpSignInBudget(): void
    {
        $this->withMiddleware(ThrottleRequests::class);
        config(['custom.rate_limits.sensitive' => 5, 'custom.rate_limits.sign_in_per_ip' => 3]);

        $this->failSignInWithDistinctIdentifiers(3);

        // A new identifier, which the per-account limit has never seen, is still refused.
        $this->postJson("{$this->resource}/sign-in", ['identifier' => 'one-more@example.test', 'password' => 'Password1'])
            ->assertStatus(429);

        // Another address has its own budget.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->postJson("{$this->resource}/sign-in", ['identifier' => 'one-more@example.test', 'password' => 'Password1'])
            ->assertBadRequest();
    }

    #[Test]
    public function anUntrustedForwardedForHeaderCannotResetThePerIpBudget(): void
    {
        $this->withMiddleware(ThrottleRequests::class);
        config(['custom.rate_limits.sensitive' => 5, 'custom.rate_limits.sign_in_per_ip' => 3, 'trustedproxy.proxies' => null]);

        $this->failSignInWithDistinctIdentifiers(3);

        $this->postJson("{$this->resource}/sign-in", ['identifier' => 'spoofer@example.test', 'password' => 'Password1'], ['X-Forwarded-For' => '198.51.100.' . random_int(1, 254)])
            ->assertStatus(429);
    }

    #[Test]
    public function behindATrustedProxyThePerIpBudgetIsKeyedByTheForwardedClient(): void
    {
        // The TRUST_ANY_PROXY shape: nginx unfiltered, the balancer at REMOTE_ADDR, the client in
        // X-Forwarded-For. '*' is the only non-null value config/trustedproxy.php produces.
        $this->withMiddleware(ThrottleRequests::class);
        config(['custom.rate_limits.sensitive' => 5, 'custom.rate_limits.sign_in_per_ip' => 3, 'trustedproxy.proxies' => '*']);

        $this->failSignInWithDistinctIdentifiers(3, '127.0.0.1', ['X-Forwarded-For' => '198.51.100.7']);

        $this->postJson("{$this->resource}/sign-in", ['identifier' => 'next@example.test', 'password' => 'Password1'], ['X-Forwarded-For' => '198.51.100.7'])
            ->assertStatus(429);
        // A different client behind the same proxy is not locked out.
        $this->postJson("{$this->resource}/sign-in", ['identifier' => 'next@example.test', 'password' => 'Password1'], ['X-Forwarded-For' => '198.51.100.8'])
            ->assertBadRequest();
    }

    #[Test]
    public function withProxiesListedForNginxThePerIpBudgetIsKeyedByTheClientNginxResolved(): void
    {
        // The TRUSTED_PROXIES shape: the base image's nginx has already put the client in
        // REMOTE_ADDR and passes no X-Forwarded-For, and Laravel trusts '*'. Trusting '*' must not
        // collapse clients into one bucket — each REMOTE_ADDR keeps its own budget.
        $this->withMiddleware(ThrottleRequests::class);
        config(['custom.rate_limits.sensitive' => 5, 'custom.rate_limits.sign_in_per_ip' => 3, 'trustedproxy.proxies' => '*']);

        $this->failSignInWithDistinctIdentifiers(3, '198.51.100.7');

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->postJson("{$this->resource}/sign-in", ['identifier' => 'next@example.test', 'password' => 'Password1'])
            ->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.8'])
            ->postJson("{$this->resource}/sign-in", ['identifier' => 'next@example.test', 'password' => 'Password1'])
            ->assertBadRequest();
    }

    #[Test]
    public function theRateLimitHeadersDescribeThePerAccountBudget(): void
    {
        // With two limits the headers must show the one closest to refusing, which for one person is per account.
        $this->withMiddleware(ThrottleRequests::class);

        $response = $this->postJson("{$this->resource}/sign-in", ['identifier' => 'headers@example.test', 'password' => 'Password1'])->assertBadRequest();

        self::assertSame((string) config('custom.rate_limits.sensitive'), $response->headers->get('X-RateLimit-Limit'));
        self::assertSame((string) (config('custom.rate_limits.sensitive') - 1), $response->headers->get('X-RateLimit-Remaining'));
    }

    #[Test]
    public function aNonStringIdentifierIsAValidationErrorEvenWhenThrottled(): void
    {
        // The limiter reads the identifier before validation runs; casting an array used to 500 here.
        $this->withMiddleware(ThrottleRequests::class);

        $this->postJson("{$this->resource}/sign-in", ['identifier' => ['someone@example.test'], 'password' => 'Password1'])
            ->assertBadRequest()
            ->assertJson(['success' => false]);
    }

}
