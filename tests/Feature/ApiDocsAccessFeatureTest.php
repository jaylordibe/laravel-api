<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers App\Http\Middleware\RestrictApiDocsAccess, which gates both Scramble routes.
 *
 * The OpenAPI document is the one artefact that describes the whole API in one place — every
 * endpoint, every parameter, and (via the `security_strategy` in config/scramble.php) exactly which
 * routes answer without a token. Whether it is reachable is a security decision, so the rules are
 * pinned here rather than left to whoever next edits the config.
 *
 * The suite runs with APP_ENV=testing, which is neither `local` nor `production`, so by default
 * these exercise the same branch a staging or review-app deployment takes. The `local` and
 * `production` branches are reached by overriding the detected environment for one test.
 *
 * The rate limiter in front of the gate is not asserted here: Tests\TestCase disables
 * ThrottleRequests for the whole suite so parallel runs do not collide.
 */
class ApiDocsAccessFeatureTest extends TestCase
{

    /**
     * The docs UI route.
     *
     * @var string
     */
    private string $uiRoute = '/docs/api';

    /**
     * The generated OpenAPI document route.
     *
     * @var string
     */
    private string $documentRoute = '/docs/api.json';

    /**
     * The configured username used across these tests.
     *
     * @var string
     */
    private string $username = 'docs-reader';

    /**
     * The configured password used across these tests.
     *
     * @var string
     */
    private string $password = 'correct-horse-battery-staple';

    /**
     * Turn the docs on with a known credential, as a configured staging environment would be.
     *
     * @return void
     */
    private function enableDocs(): void
    {
        config([
            'custom.api_docs.enabled' => true,
            'custom.api_docs.username' => $this->username,
            'custom.api_docs.password' => $this->password
        ]);
    }

    /**
     * Pretend the application booted in the given environment.
     *
     * @param string $environment
     *
     * @return void
     */
    private function setEnvironment(string $environment): void
    {
        $this->app->detectEnvironment(fn (): string => $environment);
    }

    /**
     * Build an HTTP Basic Authorization header.
     *
     * @param string $username
     * @param string $password
     *
     * @return array<string, string>
     */
    private function basicAuth(string $username, string $password): array
    {
        return ['Authorization' => 'Basic ' . base64_encode($username . ':' . $password)];
    }

    #[Test]
    public function aDeployedEnvironmentDeniesTheDocsUntilTheyAreDeliberatelyEnabled(): void
    {
        // The shipped default. Nothing an operator forgets to set can open this.
        config(['custom.api_docs.enabled' => false]);

        $this->get($this->uiRoute)->assertForbidden();
        $this->get($this->documentRoute)->assertForbidden();
    }

    #[Test]
    public function anEnabledFlagWithNoCredentialStillDenies(): void
    {
        // The failure this guards against: an environment that sets the flag and forgets the
        // credential must not accept the empty username and password a bare request carries.
        config([
            'custom.api_docs.enabled' => true,
            'custom.api_docs.username' => null,
            'custom.api_docs.password' => null
        ]);

        $this->get($this->uiRoute)->assertForbidden();
    }

    #[Test]
    public function halfAConfiguredCredentialIsTreatedAsNoCredential(): void
    {
        config([
            'custom.api_docs.enabled' => true,
            'custom.api_docs.username' => $this->username,
            'custom.api_docs.password' => ''
        ]);

        $this->get($this->uiRoute)->assertForbidden();
    }

    #[Test]
    public function anEnabledEnvironmentChallengesARequestThatCarriesNoCredential(): void
    {
        $this->enableDocs();

        $response = $this->get($this->uiRoute);

        $response->assertUnauthorized();

        // The header is what turns the 401 into the browser's own login prompt, and what makes the
        // UI's fetch of the JSON document authenticate without prompting a second time.
        self::assertStringStartsWith(
            'Basic realm="API Docs"',
            (string) $response->headers->get('WWW-Authenticate')
        );
    }

    #[Test]
    public function aWrongPasswordIsRejected(): void
    {
        $this->enableDocs();

        $this->get($this->uiRoute, $this->basicAuth($this->username, 'wrong-password'))
            ->assertUnauthorized();
    }

    #[Test]
    public function aWrongUsernameIsRejected(): void
    {
        $this->enableDocs();

        $this->get($this->uiRoute, $this->basicAuth('someone-else', $this->password))
            ->assertUnauthorized();
    }

    #[Test]
    public function theCorrectCredentialOpensBothRoutes(): void
    {
        // Scramble applies one middleware array to both routes, so this also pins that the JSON
        // document — the half that actually carries the contract — is gated identically.
        $this->enableDocs();

        $this->get($this->uiRoute, $this->basicAuth($this->username, $this->password))->assertOk();
        $this->get($this->documentRoute, $this->basicAuth($this->username, $this->password))->assertOk();
    }

    #[Test]
    public function productionDeniesEvenWithTheFlagOnAndTheCorrectCredential(): void
    {
        // The rule that makes a leaked staging credential worthless. Production is not a
        // configuration decision, so no combination of settings reaches the document there.
        $this->enableDocs();
        $this->setEnvironment('production');

        $this->get($this->uiRoute, $this->basicAuth($this->username, $this->password))
            ->assertForbidden();
        $this->get($this->documentRoute, $this->basicAuth($this->username, $this->password))
            ->assertForbidden();
    }

    #[Test]
    public function localIsOpenWithoutAnyCredential(): void
    {
        // Day-to-day development sees no prompt, and needs no settings to see none.
        config(['custom.api_docs.enabled' => false]);
        $this->setEnvironment('local');

        $this->get($this->uiRoute)->assertOk();
    }

}
