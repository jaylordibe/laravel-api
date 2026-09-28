<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
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

        $response->assertOk()->assertJsonStructure(['token']);
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

}
