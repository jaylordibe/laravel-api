<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Utils\AuthUtil;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;
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
     * The signed token carries the configured lifetime, and the response reports it.
     *
     * Asserted on the JWT's own `exp - iat` rather than by travelling in time: Passport issues and
     * validates with the native clock, which `travel()` does not move, so a time-travel test would
     * pass without proving anything.
     */
    #[Test]
    public function signInTokenLivesForTheConfiguredLifetime(): void
    {
        $lifetimeSeconds = (int) AuthUtil::personalAccessTokenLifetime()->totalSeconds;

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

    /**
     * The provider refuses to boot on an invalid lifetime instead of falling back to a default.
     */
    #[Test]
    public function anInvalidLifetimeStopsTheApplicationBooting(): void
    {
        config()->set('custom.auth.token_ttl_minutes', 'abc');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('AUTH_TOKEN_TTL_MINUTES');

        (new AppServiceProvider($this->app))->boot();
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
     * Passport's `/oauth/*` routes stay unregistered: tokens come from sign-in, and the browser
     * authorization and device-code flows are unusable surface on an API with no session login.
     *
     * Checked against the route table by Passport's `passport.` route-name prefix rather than a list
     * of paths or the `oauth` URI prefix: the name is fixed by Passport's route group, while the URI
     * prefix follows `passport.path`, which a fork may change.
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
     * Sign in with no user left over from an earlier request in this test, which would otherwise
     * trip the `guest` middleware on the sign-in route.
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

}
