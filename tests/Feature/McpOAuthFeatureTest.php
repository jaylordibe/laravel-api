<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\SingleUseAuthCodeRepository;
use App\Repositories\SingleUseRefreshTokenRepository;
use App\Utils\McpSignInUtil;
use App\Utils\McpTokenUtil;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Bridge\AuthCodeRepository;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\SignsInMcpClients;
use Tests\TestCase;

/**
 * The MCP OAuth sign-in (routes/oauth.php, routes/ai.php): a client given only /api/mcp discovers where
 * to sign in, registers itself, sends the user through the API's own sign-in and consent pages, and calls
 * tools as that user until the user's sessions end. Refusals and the n8n request shapes are raw HTTP,
 * because a client library will not send them. Live interoperability (the MCP client SDK, Claude Code,
 * n8n) is verified separately against a running server; see docs/mcp.md.
 *
 * Not asserted: the `oauth`, `sign-in` and `mcp` rate limits. Tests\TestCase disables ThrottleRequests for
 * the whole suite; the budgets are `custom.rate_limits.*`. CSRF is not observable either: Laravel skips
 * the check while running tests, so the tests assert the route sits behind it and the forms carry the
 * token.
 */
class McpOAuthFeatureTest extends TestCase
{

    use SignsInMcpClients;

    /**
     * What n8n's "MCP OAuth2 API" credential registers (n8n source, packages/cli/src/oauth/oauth.service.ts):
     * its own callback, its own name, no grant_types and no token_endpoint_auth_method.
     *
     * @var string
     */
    private string $n8nCallback = 'https://n8n.example.com/rest/oauth2-credential/callback';

    /**
     * A known issuer, so the metadata assertions are independent of the environment's APP_URL.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['mcp.authorization_server' => 'https://api.example.com']);
    }

    /**
     * POST tools/list to the MCP endpoint with a bearer token.
     *
     * @param string $accessToken
     *
     * @return TestResponse
     */
    private function callMcp(string $accessToken): TestResponse
    {
        $this->forgetAuthenticatedUsers();

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json, text/event-stream',
        ])->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => (object) []]);
    }

    /**
     * Call get_current_user with a bearer token.
     *
     * @param string $accessToken
     *
     * @return TestResponse
     */
    private function getCurrentUser(string $accessToken): TestResponse
    {
        $this->forgetAuthenticatedUsers();

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json, text/event-stream',
        ])->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'get_current_user', 'arguments' => (object) []],
        ])->assertOk();
    }

    /**
     * A REST request with a bearer token.
     *
     * @param string $method
     * @param string $uri
     * @param string $accessToken
     * @param array<string, mixed> $data
     *
     * @return TestResponse
     */
    private function rest(string $method, string $uri, string $accessToken, array $data = []): TestResponse
    {
        $this->forgetAuthenticatedUsers();

        return $this->withHeaders(['Authorization' => 'Bearer ' . $accessToken])->json($method, $uri, $data);
    }

    #[Test]
    public function refusesAnUnauthenticatedCallWithAChallengeNamingTheResourceMetadata(): void
    {
        $response = $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();

        self::assertStringContainsString(
            'resource_metadata="' . url('/.well-known/oauth-protected-resource/api/mcp') . '"',
            (string) $response->headers->get('WWW-Authenticate')
        );
    }

    #[Test]
    public function servesProtectedResourceMetadataNamingThisEndpointAndItsAuthorizationServer(): void
    {
        foreach (['/.well-known/oauth-protected-resource/api/mcp', '/.well-known/oauth-protected-resource'] as $path) {
            $this->getJson($path)
                ->assertOk()
                ->assertExactJson([
                    'resource' => 'https://api.example.com/api/mcp',
                    'authorization_servers' => ['https://api.example.com'],
                    'scopes_supported' => ['mcp:use'],
                    'bearer_methods_supported' => ['header'],
                ]);
        }

        // No other resource exists.
        $this->getJson('/.well-known/oauth-protected-resource/foo/bar')->assertNotFound();
    }

    #[Test]
    public function pinsEveryAdvertisedUrlToTheIssuerWhateverTheHostHeaderSays(): void
    {
        foreach (['/.well-known/oauth-authorization-server', '/.well-known/oauth-protected-resource/api/mcp'] as $path) {
            $body = $this->withHeaders(['Host' => 'evil.example', 'X-Forwarded-Host' => 'evil.example'])->getJson($path)->assertOk()->json();
            $urls = array_filter(array_merge(...array_map(fn (mixed $value): array => (array) $value, array_values($body))), fn (mixed $value): bool => is_string($value) && str_starts_with($value, 'http'));

            self::assertNotEmpty($urls);
            foreach ($urls as $url) {
                self::assertStringStartsWith('https://api.example.com', $url, $path);
            }
        }
    }

    #[Test]
    public function servesAuthorizationServerMetadataWithPkceS256AndRegistration(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('issuer', 'https://api.example.com')
            ->assertJsonPath('authorization_endpoint', 'https://api.example.com/oauth/authorize')
            ->assertJsonPath('token_endpoint', 'https://api.example.com/oauth/token')
            ->assertJsonPath('registration_endpoint', 'https://api.example.com/oauth/register')
            ->assertJsonPath('token_endpoint_auth_methods_supported', ['none'])
            ->assertJsonPath('code_challenge_methods_supported', ['S256'])
            ->assertJsonPath('response_types_supported', ['code']);
    }

    #[Test]
    public function connectsAClientAndToolsActAsTheSignedInUser(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('system_admin', 'api'));

        $tokens = $this->signInMcpClient($user->email);

        $this->getCurrentUser($tokens['access_token'])->assertJsonPath('result.structuredContent', [
            'id' => (string) $user->id,
            'email' => $user->email,
            'roles' => ['system_admin'],
        ]);
    }

    #[Test]
    public function identifiesAUserWithNoRoleAsHavingNone(): void
    {
        $user = User::factory()->create();
        $tokens = $this->signInMcpClient($user->email);

        $this->getCurrentUser($tokens['access_token'])->assertJsonPath('result.structuredContent.roles', []);
    }

    #[Test]
    public function acceptsTheUsernameAsTheSignInIdentifier(): void
    {
        $user = User::factory()->create();
        $tokens = $this->signInMcpClient($user->username);

        $this->getCurrentUser($tokens['access_token'])->assertJsonPath('result.structuredContent.id', (string) $user->id);
    }

    #[Test]
    public function renewsAccessWithARotatingRefreshToken(): void
    {
        $tokens = $this->signInMcpClient(User::factory()->create()->email);

        $renewed = $this->refreshTokens(['refresh_token' => $tokens['refresh_token'], 'client_id' => $tokens['client_id']])->assertOk();

        $this->callMcp((string) $renewed->json('access_token'))->assertOk();
        self::assertNotSame($tokens['refresh_token'], $renewed->json('refresh_token'));
        // The used refresh token is spent.
        $this->refreshTokens(['refresh_token' => $tokens['refresh_token'], 'client_id' => $tokens['client_id']])->assertStatus(400);
    }

    #[Test]
    public function connectsN8nsCredentialWithoutAClientIdUsingPkceAndResource(): void
    {
        $user = User::factory()->create();
        $registered = $this->postJson('/oauth/register', [
            'client_name' => 'n8n',
            'redirect_uris' => [$this->n8nCallback],
            'response_types' => ['code'],
            'scope' => 'mcp:use',
        ])->assertCreated();

        $registered->assertJsonPath('token_endpoint_auth_method', 'none');
        self::assertNull($registered->json('client_secret'));
        $clientId = (string) $registered->json('client_id');
        $pkce = $this->pkcePair();
        $resource = url('/api/mcp');

        $this->newBrowser();
        $url = $this->authorizationUrl($clientId, $pkce['challenge'], ['resource' => $resource], $this->n8nCallback);
        $redirect = $this->decide($this->signInToConsent($url, $user->email), $clientId);
        $location = (string) $redirect->headers->get('Location');
        self::assertStringStartsWith($this->n8nCallback . '?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        self::assertSame('state-123', $query['state']);

        $tokens = $this->exchangeCode([
            'code' => $query['code'],
            'redirect_uri' => $this->n8nCallback,
            'client_id' => $clientId,
            'code_verifier' => $pkce['verifier'],
            'resource' => $resource,
        ])->assertOk()->assertJsonPath('token_type', 'Bearer');

        $this->callMcp((string) $tokens->json('access_token'))->assertOk();
        $this->refreshTokens([
            'refresh_token' => (string) $tokens->json('refresh_token'),
            'client_id' => $clientId,
            'resource' => $resource,
        ])->assertOk();
    }

    #[Test]
    public function decliningConsentSendsAccessDeniedAndNoCode(): void
    {
        $user = User::factory()->create();
        $clientId = $this->registerMcpClient();
        $url = $this->authorizationUrl($clientId, $this->pkcePair()['challenge']);

        $query = $this->callbackQuery($this->decide($this->signInToConsent($url, $user->email), $clientId, false));

        self::assertSame('access_denied', $query['error']);
        self::assertArrayNotHasKey('code', $query);
    }

    /**
     * Sign-in attempts that must be refused, and the message each gets.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function refusedSignIns(): array
    {
        return [
            'a wrong password' => ['wrong-password', 'Invalid username or password'],
            'an unknown account' => ['unknown', 'Invalid username or password'],
            'an unverified account' => ['unverified', 'Email not yet verified. Please check your email and verify.'],
            'a deleted account' => ['deleted', 'Invalid username or password'],
        ];
    }

    #[Test]
    #[DataProvider('refusedSignIns')]
    public function refusesASignInLeavingTheClientUnconnected(string $case, string $message): void
    {
        $user = match ($case) {
            'unverified' => User::factory()->unverified()->create(),
            default => User::factory()->create(),
        };
        $identifier = $case === 'unknown' ? 'nobody@example.com' : $user->email;
        $password = $case === 'wrong-password' ? 'not-the-password' : 'password';

        if ($case === 'deleted') {
            $user->delete();
        }

        $clientId = $this->registerMcpClient();
        $url = $this->authorizationUrl($clientId, $this->pkcePair()['challenge']);
        $this->browser('GET', $url)->assertRedirect(route('mcp.oauth.login'));

        $this->browser('POST', '/oauth/login', ['identifier' => $identifier, 'password' => $password])
            ->assertRedirect(route('mcp.oauth.login'));
        self::assertStringContainsString(e($message), (string) $this->browser('GET', '/oauth/login')->getContent());

        // Still not signed in: the authorization request is sent back to the sign-in page.
        $this->browser('GET', $url)->assertRedirect(route('mcp.oauth.login'));
        self::assertSame(0, DB::table('oauth_auth_codes')->count());
    }

    /**
     * Redirect URIs a client must not register.
     *
     * @return array<string, array{0: string}>
     */
    public static function forbiddenRedirects(): array
    {
        return [
            'plain http to a public host' => ['http://n8n.example.com/callback'],
            'a custom scheme' => ['cursor://callback'],
            'a fragment' => ['https://claude.ai/callback#x'],
        ];
    }

    #[Test]
    #[DataProvider('forbiddenRedirects')]
    public function refusesToRegisterAForbiddenRedirect(string $redirectUri): void
    {
        $this->postJson('/oauth/register', ['client_name' => 'Bad', 'redirect_uris' => [$redirectUri]])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');
    }

    #[Test]
    public function narrowsHttpsRedirectsToTheConfiguredOrigins(): void
    {
        config(['custom.mcp.oauth.redirect_domains' => 'https://claude.ai']);

        $this->postJson('/oauth/register', ['client_name' => 'Other', 'redirect_uris' => [$this->n8nCallback]])->assertStatus(400);
        $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])->assertCreated();
        $this->postJson('/oauth/register', ['client_name' => 'Local', 'redirect_uris' => [$this->loopbackRedirect]])->assertCreated();
    }

    /**
     * Authorization request variants that must be refused, with the OAuth error each gets.
     *
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function refusedAuthorizationRequests(): array
    {
        return [
            'PKCE plain' => [['code_challenge_method' => 'plain'], 'invalid_request'],
            'no code challenge method' => [['code_challenge_method' => ''], 'invalid_request'],
            'another resource' => [['resource' => 'https://other.example.com/mcp'], 'invalid_target'],
        ];
    }

    /**
     * @param array<string, string> $extra
     */
    #[Test]
    #[DataProvider('refusedAuthorizationRequests')]
    public function refusesAnAuthorizationRequestWithoutRedirecting(array $extra, string $error): void
    {
        $clientId = $this->registerMcpClient();

        $response = $this->browser('GET', $this->authorizationUrl($clientId, $this->pkcePair()['challenge'], $extra));

        $response->assertStatus(400)->assertJsonPath('error', $error);
        self::assertNull($response->headers->get('Location'));
    }

    #[Test]
    public function refusesAnUnregisteredRedirectWithoutRedirectingToIt(): void
    {
        $clientId = $this->registerMcpClient();

        $response = $this->browser('GET', $this->authorizationUrl($clientId, $this->pkcePair()['challenge'], [], 'https://evil.example/callback'));

        self::assertGreaterThanOrEqual(400, $response->getStatusCode());
        self::assertStringNotContainsString('evil.example', (string) $response->headers->get('Location'));
    }

    #[Test]
    public function acceptsA127LoopbackRedirectOnAnotherPortButMatchesLocalhostExactly(): void
    {
        $user = User::factory()->create();
        $clientId = $this->registerMcpClient(['redirect_uris' => ['http://127.0.0.1:9876/callback', $this->loopbackRedirect]]);

        // RFC 8252 §7.3: the port of an IP loopback redirect may change between sign-ins.
        $consent = $this->signInToConsent(
            $this->authorizationUrl($clientId, $this->pkcePair()['challenge'], [], 'http://127.0.0.1:4321/callback'),
            $user->email
        );
        self::assertStringStartsWith('http://127.0.0.1:4321/callback?', (string) $this->decide($consent, $clientId)->headers->get('Location'));

        // league/oauth2-server extends that only to IP literals, so a `localhost` redirect must match exactly.
        $this->newBrowser();
        $response = $this->browser('GET', $this->authorizationUrl($clientId, $this->pkcePair()['challenge'], [], 'http://localhost:4321/callback'));
        self::assertGreaterThanOrEqual(400, $response->getStatusCode());
    }

    #[Test]
    public function refusesAWrongCodeVerifierAndARedeemedCode(): void
    {
        $user = User::factory()->create();
        $clientId = $this->registerMcpClient();
        $pkce = $this->pkcePair();
        $consent = $this->signInToConsent($this->authorizationUrl($clientId, $pkce['challenge']), $user->email);
        $code = $this->callbackQuery($this->decide($consent, $clientId))['code'];
        $fields = ['code' => $code, 'redirect_uri' => $this->loopbackRedirect, 'client_id' => $clientId];

        // The first redemption attempt spends the code, even one whose verifier then fails.
        $this->exchangeCode([...$fields, 'code_verifier' => $this->pkcePair()['verifier']])->assertStatus(400);
        $this->exchangeCode([...$fields, 'code_verifier' => $pkce['verifier']])->assertStatus(400);
    }

    #[Test]
    public function aRedeemedCodeCannotBeRedeemedAgain(): void
    {
        $user = User::factory()->create();
        $clientId = $this->registerMcpClient();
        $pkce = $this->pkcePair();
        $consent = $this->signInToConsent($this->authorizationUrl($clientId, $pkce['challenge']), $user->email);
        $code = $this->callbackQuery($this->decide($consent, $clientId))['code'];
        $fields = ['code' => $code, 'redirect_uri' => $this->loopbackRedirect, 'client_id' => $clientId, 'code_verifier' => $pkce['verifier']];

        $this->exchangeCode($fields)->assertOk();
        $this->exchangeCode($fields)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    #[Test]
    public function anMcpTokenIsRefusedByTheRestApi(): void
    {
        $tokens = $this->signInMcpClient(User::factory()->create()->email);

        $this->rest('GET', '/api/users/auth', $tokens['access_token'])->assertUnauthorized();
        $this->rest('POST', '/api/auth/sign-out-all', $tokens['access_token'])->assertUnauthorized();
    }

    #[Test]
    public function aRestSignInTokenIsRefusedByMcp(): void
    {
        $user = User::factory()->create();
        $restToken = $this->login($user->email);

        $this->rest('GET', '/api/users/auth', $restToken)->assertOk();
        $this->callMcp($restToken)->assertUnauthorized();
    }

    /**
     * Assert the MCP client can no longer call the endpoint or renew its access.
     *
     * @param array{access_token: string, refresh_token: string, client_id: string} $tokens
     *
     * @return void
     */
    private function assertAccessEnded(array $tokens): void
    {
        $response = $this->callMcp($tokens['access_token'])->assertUnauthorized();
        // The refused client is pointed back at sign-in.
        self::assertStringContainsString('resource_metadata=', (string) $response->headers->get('WWW-Authenticate'));
        $this->refreshTokens(['refresh_token' => $tokens['refresh_token'], 'client_id' => $tokens['client_id']])->assertStatus(400);
    }

    #[Test]
    public function accessEndsAfterSignOutEverywhere(): void
    {
        $user = User::factory()->create();
        $tokens = $this->signInMcpClient($user->email);

        $this->rest('POST', '/api/auth/sign-out-all', $this->login($user->email))->assertOk();

        $this->assertAccessEnded($tokens);
    }

    #[Test]
    public function accessEndsAfterTheUserChangesTheirPassword(): void
    {
        $user = User::factory()->create();
        $tokens = $this->signInMcpClient($user->email);

        $this->rest('PUT', '/api/users/auth/password', $this->login($user->email), [
            'currentPassword' => 'password',
            'password' => 'a-new-password-1',
            'passwordConfirmation' => 'a-new-password-1',
        ])->assertOk();

        $this->assertAccessEnded($tokens);
    }

    #[Test]
    public function accessEndsAfterAnAdministratorResetsThePassword(): void
    {
        $user = User::factory()->create();
        $tokens = $this->signInMcpClient($user->email);

        $this->rest('PUT', "/api/users/{$user->id}/password", $this->loginSystemAdminUser(), [
            'password' => 'a-new-password-1',
            'passwordConfirmation' => 'a-new-password-1',
        ])->assertOk();

        $this->assertAccessEnded($tokens);
    }

    #[Test]
    public function accessEndsWhenTheAccountIsDeleted(): void
    {
        $user = User::factory()->create();
        $tokens = $this->signInMcpClient($user->email);

        $this->rest('DELETE', "/api/users/{$user->id}", $this->loginSystemAdminUser())->assertOk();

        $this->assertAccessEnded($tokens);
    }

    #[Test]
    public function accessEndsWhenTheAccessTokenExpires(): void
    {
        // The guard checks the token's own `exp`, fixed at issue, so the token is issued to expire at once.
        Passport::tokensExpireIn(now()->addSecond());
        $tokens = $this->signInMcpClient(User::factory()->create()->email);
        sleep(2);

        $this->callMcp($tokens['access_token'])->assertUnauthorized();
    }

    #[Test]
    public function aCodeIssuedBeforeSignOutEverywhereCannotBeRedeemedAfterIt(): void
    {
        $user = User::factory()->create();
        $clientId = $this->registerMcpClient();
        $pkce = $this->pkcePair();
        $consent = $this->signInToConsent($this->authorizationUrl($clientId, $pkce['challenge']), $user->email);
        $code = $this->callbackQuery($this->decide($consent, $clientId))['code'];

        $this->rest('POST', '/api/auth/sign-out-all', $this->login($user->email))->assertOk();

        $this->exchangeCode([
            'code' => $code,
            'redirect_uri' => $this->loopbackRedirect,
            'client_id' => $clientId,
            'code_verifier' => $pkce['verifier'],
        ])->assertStatus(400);
    }

    #[Test]
    public function signingInAgainRestoresAccessWithANewToken(): void
    {
        $user = User::factory()->create();
        $this->signInMcpClient($user->email);
        $this->rest('POST', '/api/auth/sign-out-all', $this->login($user->email))->assertOk();
        // A sign-in in the same second as the revocation counts as before it.
        $this->travel(1)->seconds();

        $tokens = $this->signInMcpClient($user->email);

        $this->callMcp($tokens['access_token'])->assertOk();
    }

    #[Test]
    public function thePagesCannotBeFramedAndLoadNothingFromElsewhere(): void
    {
        $user = User::factory()->create();
        $clientId = $this->registerMcpClient(['redirect_uris' => [$this->n8nCallback]]);
        $url = $this->authorizationUrl($clientId, $this->pkcePair()['challenge'], [], $this->n8nCallback);

        $this->browser('GET', $url);
        $login = $this->browser('GET', '/oauth/login')->assertOk();
        $consent = $this->signInToConsent($url, $user->email);

        foreach ([$login, $consent] as $page) {
            self::assertSame('DENY', $page->headers->get('X-Frame-Options'));
            $csp = (string) $page->headers->get('Content-Security-Policy');
            self::assertStringContainsString("frame-ancestors 'none'", $csp);
            self::assertStringContainsString("default-src 'none'", $csp);
            self::assertStringContainsString("script-src 'none'", $csp);
            self::assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        }

        // The consent post ends at the client: its origin is allowed, no other.
        self::assertStringContainsString("form-action 'self' https://n8n.example.com", (string) $consent->headers->get('Content-Security-Policy'));
    }

    #[Test]
    public function theConsentPageShowsTheClientNameEscapedAndTheRegisteredRedirect(): void
    {
        $user = User::factory()->create();
        $clientId = $this->registerMcpClient(['client_name' => '<script>alert("x")</script>', 'redirect_uris' => [$this->n8nCallback]]);

        $consent = $this->signInToConsent(
            $this->authorizationUrl($clientId, $this->pkcePair()['challenge'], [], $this->n8nCallback),
            $user->email
        );

        $html = (string) $consent->getContent();
        self::assertStringNotContainsString('<script>alert', $html);
        self::assertStringContainsString(e('<script>alert("x")</script>'), $html);
        self::assertStringContainsString($this->n8nCallback, $html);
        self::assertStringContainsString($user->email, $html);
    }

    #[Test]
    public function theFormsSitBehindCsrfProtectionAndCarryTheToken(): void
    {
        // The middleware groups are attached to the router when the HTTP kernel is built.
        $this->app->make(Kernel::class);

        foreach (['mcp.oauth.login.submit', 'passport.authorizations.approve', 'passport.authorizations.deny'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            self::assertNotNull($route, $name);
            self::assertContains(PreventRequestForgery::class, app('router')->gatherRouteMiddleware($route), $name);
        }

        $user = User::factory()->create();
        $clientId = $this->registerMcpClient();
        $url = $this->authorizationUrl($clientId, $this->pkcePair()['challenge']);
        $this->browser('GET', $url);
        self::assertStringContainsString('name="_token"', (string) $this->browser('GET', '/oauth/login')->getContent());
        self::assertSame(2, substr_count((string) $this->signInToConsent($url, $user->email)->getContent(), 'name="_token"'));
    }

    #[Test]
    public function asksForThePasswordAndConsentAgainOnTheNextAuthorization(): void
    {
        $user = User::factory()->create();
        $clientId = $this->registerMcpClient();
        $url = $this->authorizationUrl($clientId, $this->pkcePair()['challenge']);
        $this->decide($this->signInToConsent($url, $user->email), $clientId);

        // Same browser, same client, a token already granted: still the sign-in page, then consent again.
        $this->browser('GET', $url)->assertRedirect(route('mcp.oauth.login'));
        $this->signInToConsent($url, $user->email)->assertSee('Allow access?');
    }

    #[Test]
    public function refusesAnUnauthenticatedGetOrDeleteWithTheSameChallenge(): void
    {
        foreach (['GET', 'DELETE'] as $method) {
            $response = $this->json($method, '/api/mcp')->assertUnauthorized();
            self::assertStringContainsString('resource_metadata=', (string) $response->headers->get('WWW-Authenticate'), $method);
        }
    }

    #[Test]
    public function aWildcardScopeStillYieldsAnMcpTokenThatRestRefuses(): void
    {
        $user = User::factory()->create();
        $clientId = $this->registerMcpClient();
        $pkce = $this->pkcePair();
        $url = $this->authorizationUrl($clientId, $pkce['challenge'], ['scope' => '*']);
        $code = $this->callbackQuery($this->decide($this->signInToConsent($url, $user->email), $clientId))['code'];

        $tokens = $this->exchangeCode([
            'code' => $code,
            'redirect_uri' => $this->loopbackRedirect,
            'client_id' => $clientId,
            'code_verifier' => $pkce['verifier'],
        ])->assertOk();

        // Usable on MCP (which requires `mcp:use`), refused on REST.
        $this->rest('GET', '/api/users/auth', (string) $tokens->json('access_token'))->assertUnauthorized();
        $this->callMcp((string) $tokens->json('access_token'))->assertOk();
    }

    #[Test]
    public function aTokenFromTheMcpSignInIsAnMcpTokenWhateverItsScopes(): void
    {
        $clientId = $this->registerMcpClient();
        $personalClientId = (string) DB::table('oauth_clients')->whereJsonContains('grant_types', 'personal_access')->value('id');

        // Scope-less, from an MCP client: still refused on REST and not usable on MCP.
        $scopeless = new AccessToken(['oauth_client_id' => $clientId, 'oauth_scopes' => []]);
        self::assertTrue(McpTokenUtil::isMcpToken($scopeless));
        self::assertFalse(McpTokenUtil::isUsableOnMcp($scopeless));

        // A REST personal access token is neither.
        $personal = new AccessToken(['oauth_client_id' => $personalClientId, 'oauth_scopes' => []]);
        self::assertFalse(McpTokenUtil::isMcpToken($personal));
        self::assertFalse(McpTokenUtil::isUsableOnMcp($personal));
    }

    /**
     * Revocations a sign-in left open on the consent page must not survive.
     *
     * @return array<string, array{0: string}>
     */
    public static function revocations(): array
    {
        return [
            'sign-out everywhere' => ['sign-out-all'],
            'a password change' => ['password'],
        ];
    }

    #[Test]
    #[DataProvider('revocations')]
    public function aSignInLeftOnTheConsentPageAcrossARevocationCannotApprove(string $revocation): void
    {
        $user = User::factory()->create();
        $clientId = $this->registerMcpClient();
        $consent = $this->signInToConsent($this->authorizationUrl($clientId, $this->pkcePair()['challenge']), $user->email);

        $this->travel(1)->seconds();
        $this->forgetAuthenticatedUsers();
        $this->freshRequestState();
        $restToken = $this->login($user->email);
        if ($revocation === 'sign-out-all') {
            $this->rest('POST', '/api/auth/sign-out-all', $restToken)->assertOk();
        } else {
            $this->rest('PUT', '/api/users/auth/password', $restToken, [
                'currentPassword' => 'password',
                'password' => 'a-new-password-1',
                'passwordConfirmation' => 'a-new-password-1',
            ])->assertOk();
        }

        $response = $this->decide($consent, $clientId);

        $response->assertForbidden();
        self::assertNull($response->headers->get('Location'));
        self::assertSame(0, DB::table('oauth_auth_codes')->where('user_id', $user->id)->where('revoked', false)->count());
    }

    #[Test]
    public function aSignInOlderThanTheWindowCannotApprove(): void
    {
        $user = User::factory()->create();
        $clientId = $this->registerMcpClient();
        $consent = $this->signInToConsent($this->authorizationUrl($clientId, $this->pkcePair()['challenge']), $user->email);

        $this->travel(McpSignInUtil::SIGN_IN_WINDOW_MINUTES + 1)->minutes();

        $this->decide($consent, $clientId)->assertForbidden();
    }

    #[Test]
    public function aCodeOrRefreshTokenIsClaimedByExactlyOneRedemption(): void
    {
        $tokens = $this->signInMcpClient(User::factory()->create()->email);
        $refreshTokenId = (string) DB::table('oauth_refresh_tokens')->where('revoked', false)->value('id');
        $repository = app(RefreshTokenRepository::class);

        self::assertInstanceOf(SingleUseRefreshTokenRepository::class, $repository);
        self::assertFalse($repository->isRefreshTokenRevoked($refreshTokenId));
        self::assertTrue($repository->isRefreshTokenRevoked($refreshTokenId));
        self::assertInstanceOf(SingleUseAuthCodeRepository::class, app(AuthCodeRepository::class));
        self::assertNotSame('', $tokens['access_token']);
    }

    #[Test]
    public function everyMcpAndOAuthRouteIsRateLimited(): void
    {
        $this->app->make(Kernel::class);
        $router = app('router');
        $expected = [
            'api/mcp' => 'throttle:mcp',
            '.well-known/oauth-authorization-server' => 'throttle:oauth',
            '.well-known/oauth-protected-resource' => 'throttle:oauth',
            '.well-known/oauth-protected-resource/{path}' => 'throttle:oauth',
            'oauth/register' => 'throttle:oauth',
            'oauth/authorize' => 'throttle:oauth',
            'oauth/token' => 'throttle:oauth',
            'oauth/login' => 'throttle:oauth',
        ];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (!array_key_exists($route->uri(), $expected)) {
                continue;
            }
            $limiter = $route->getName() === 'mcp.oauth.login.submit' ? 'throttle:sign-in' : $expected[$route->uri()];
            self::assertContains($limiter, $route->gatherMiddleware(), "{$route->uri()} {$route->getName()}");
        }

        // The sign-in post shares REST sign-in's budget instead of the general OAuth one.
        $signIn = $router->getRoutes()->getByName('mcp.oauth.login.submit');
        self::assertContains('throttle:oauth', $signIn->excludedMiddleware());
    }

}
