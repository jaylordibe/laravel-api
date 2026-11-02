<?php

namespace Tests;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Drives the MCP OAuth sign-in (routes/oauth.php, routes/ai.php) the way an MCP client and a browser do:
 * the client registers and exchanges the code; the browser follows the redirects with its session cookie
 * and submits the sign-in and consent forms. Each browser step forgets the guards first, so it is the
 * session cookie — not state left in memory by the previous request — that carries the sign-in.
 *
 * @mixin TestCase
 */
trait SignsInMcpClients
{

    /**
     * The loopback redirect a native client such as Claude Code registers.
     *
     * @var string
     */
    protected string $loopbackRedirect = 'http://localhost:9876/callback';

    /**
     * The browser's cookies, name => (already encrypted) value.
     *
     * @var array<string, string>
     */
    private array $browserCookies = [];

    /**
     * Register an MCP client and return its client id.
     *
     * @param array<string, mixed> $metadata
     *
     * @return string
     */
    protected function registerMcpClient(array $metadata = []): string
    {
        $response = $this->postJson('/oauth/register', [
            'client_name' => 'MCP test client',
            'redirect_uris' => [$this->loopbackRedirect],
            ...$metadata,
        ])->assertCreated();

        return (string) $response->json('client_id');
    }

    /**
     * A PKCE S256 verifier and challenge.
     *
     * @return array{verifier: string, challenge: string}
     */
    protected function pkcePair(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return ['verifier' => $verifier, 'challenge' => $challenge];
    }

    /**
     * The authorization URL a client opens in the browser.
     *
     * @param string $clientId
     * @param string $challenge
     * @param array<string, string> $extra
     * @param string|null $redirectUri
     *
     * @return string
     */
    protected function authorizationUrl(string $clientId, string $challenge, array $extra = [], ?string $redirectUri = null): string
    {
        return '/oauth/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri ?? $this->loopbackRedirect,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'state' => 'state-123',
            'scope' => 'mcp:use',
            ...$extra,
        ]);
    }

    /**
     * A browser request: the browser's cookies go out, the response's cookies are kept.
     *
     * @param string $method
     * @param string $uri
     * @param array<string, mixed> $data
     *
     * @return TestResponse
     */
    protected function browser(string $method, string $uri, array $data = []): TestResponse
    {
        $this->freshRequestState();
        // The browser's own cookies, exactly as the server set them (already encrypted).
        $response = $this->call($method, $uri, $data, $this->browserCookies);

        foreach ($response->headers->getCookies() as $cookie) {
            /** @var Cookie $cookie */
            if ($cookie->isCleared()) {
                unset($this->browserCookies[$cookie->getName()]);
            } else {
                $this->browserCookies[$cookie->getName()] = (string) $cookie->getValue();
            }
        }

        return $response;
    }

    /**
     * Server-side state as a separate HTTP request would find it.
     *
     * Sessions are in the database (the configured default, and what a deployment uses), with a fresh store
     * per request. The suite's `array` driver keeps sessions inside the store object and merges one
     * request's attributes into the next, which would carry a sign-in across requests whatever cookie the
     * browser sends. Guards hold the store they were built with, routes keep the controller (and the guard
     * injected into it) from the previous request, and the redirector keeps its store, so all of them are
     * rebuilt after it.
     *
     * @return void
     */
    protected function freshRequestState(): void
    {
        config(['session.driver' => 'database']);
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app->forgetInstance('redirect');
        $this->app['auth']->forgetGuards();
        foreach ($this->app['router']->getRoutes() as $route) {
            $route->flushController();
        }
    }

    /**
     * Start a fresh browser (no cookies).
     *
     * @return void
     */
    protected function newBrowser(): void
    {
        $this->browserCookies = [];
    }

    /**
     * Open the authorization URL, sign in, and stop on the consent page.
     *
     * @param string $authorizationUrl
     * @param string $identifier
     * @param string $password
     *
     * @return TestResponse the consent page
     */
    protected function signInToConsent(string $authorizationUrl, string $identifier, string $password = 'password'): TestResponse
    {
        $this->browser('GET', $authorizationUrl)->assertRedirect(route('mcp.oauth.login'));
        $signedIn = $this->browser('POST', '/oauth/login', ['identifier' => $identifier, 'password' => $password]);
        // Back to the authorization request it came from (the framework may reorder its query string).
        self::assertSame('/oauth/authorize', parse_url((string) $signedIn->headers->get('Location'), PHP_URL_PATH));

        return $this->browser('GET', $authorizationUrl)->assertOk();
    }

    /**
     * Answer the consent page.
     *
     * @param TestResponse $consentPage
     * @param string $clientId
     * @param bool $approve
     *
     * @return TestResponse the redirect to the client
     */
    protected function decide(TestResponse $consentPage, string $clientId, bool $approve = true): TestResponse
    {
        preg_match('/name="auth_token" value="([^"]+)"/', (string) $consentPage->getContent(), $matches);
        self::assertNotEmpty($matches[1] ?? null, 'The consent page carries no auth_token.');
        $fields = ['state' => 'state-123', 'client_id' => $clientId, 'auth_token' => $matches[1]];

        return $approve
            ? $this->browser('POST', '/oauth/authorize', $fields)
            : $this->browser('DELETE', '/oauth/authorize', $fields);
    }

    /**
     * The query parameters of a redirect to the client.
     *
     * @param TestResponse $redirect
     *
     * @return array<string, string>
     */
    protected function callbackQuery(TestResponse $redirect): array
    {
        $location = (string) $redirect->headers->get('Location');
        self::assertStringStartsWith($this->loopbackRedirect, $location, "Not sent to the client: {$location}");
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    /**
     * Exchange a code at the token endpoint.
     *
     * @param array<string, string> $fields
     *
     * @return TestResponse
     */
    protected function exchangeCode(array $fields): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->post('/oauth/token', ['grant_type' => 'authorization_code', ...$fields]);
    }

    /**
     * Use a refresh token at the token endpoint.
     *
     * @param array<string, string> $fields
     *
     * @return TestResponse
     */
    protected function refreshTokens(array $fields): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->post('/oauth/token', ['grant_type' => 'refresh_token', ...$fields]);
    }

    /**
     * The whole flow for a user: register, sign in, approve, exchange. Returns the token response.
     *
     * @param string $identifier
     * @param string $password
     *
     * @return array{access_token: string, refresh_token: string, client_id: string}
     */
    protected function signInMcpClient(string $identifier, string $password = 'password'): array
    {
        $this->newBrowser();
        $clientId = $this->registerMcpClient();
        $pkce = $this->pkcePair();
        $consent = $this->signInToConsent($this->authorizationUrl($clientId, $pkce['challenge']), $identifier, $password);
        $query = $this->callbackQuery($this->decide($consent, $clientId));

        $tokens = $this->exchangeCode([
            'code' => $query['code'],
            'redirect_uri' => $this->loopbackRedirect,
            'client_id' => $clientId,
            'code_verifier' => $pkce['verifier'],
        ])->assertOk();

        $this->newBrowser();
        $this->forgetAuthenticatedUsers();

        return [
            'access_token' => (string) $tokens->json('access_token'),
            'refresh_token' => (string) $tokens->json('refresh_token'),
            'client_id' => $clientId,
        ];
    }

}
