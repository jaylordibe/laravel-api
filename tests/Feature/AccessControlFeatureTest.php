<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Who may reach what: no token is always 401, a plain user never reaches an admin action, and a
 * permission that is missing from the database denies rather than errors.
 */
class AccessControlFeatureTest extends TestCase
{

    /**
     * Every route behind `auth:api`, read from the router so a new route is covered automatically.
     *
     * @return array<int, array{string, string}>
     */
    private function authenticatedRoutes(): array
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => in_array('auth:api', $route->gatherMiddleware(), true))
            ->flatMap(fn (Route $route): array => collect($route->methods())
                ->reject(fn (string $method): bool => $method === 'HEAD')
                ->map(fn (string $method): array => [$method, '/' . preg_replace('/\{[^}]+}/', '1', $route->uri())])
                ->all())
            ->values()
            ->all();
    }

    #[Test]
    public function everyAuthenticatedRouteRejectsARequestWithoutAToken(): void
    {
        $routes = $this->authenticatedRoutes();
        self::assertGreaterThan(20, count($routes), 'The route sweep found too few routes to be meaningful.');

        foreach ($routes as [$method, $uri]) {
            $response = $this->json($method, $uri);

            self::assertSame(401, $response->status(), "{$method} {$uri} must require a token.");
            self::assertSame(['message'], array_keys($response->json()), "{$method} {$uri} must use the 401 envelope.");
        }
    }

    #[Test]
    public function everyApiRouteRequiresATokenUnlessItIsDeclaredPublic(): void
    {
        // The deliberate public surface. Adding a route outside the auth group fails here until it is
        // listed, so exposing an endpoint is always a decision, never an accident of placement.
        $publicRoutes = [
            'GET api/health/ready',
            'GET api/app-versions/latest',
            'POST api/auth/sign-in',
            'POST api/users/sign-up',
            'GET api/email/verify/{id}',
            'POST api/email/verification-notification',
        ];

        $unauthenticated = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/'))
            ->reject(fn (Route $route): bool => in_array('auth:api', $route->gatherMiddleware(), true))
            ->flatMap(fn (Route $route): array => collect($route->methods())
                ->reject(fn (string $method): bool => $method === 'HEAD')
                ->map(fn (string $method): string => "{$method} {$route->uri()}")
                ->all())
            ->sort()
            ->values()
            ->all();

        self::assertSame(collect($publicRoutes)->sort()->values()->all(), $unauthenticated);
    }

    #[Test]
    public function anInvalidTokenIsRejected(): void
    {
        $this->withToken('not-a-real-token')->getJson('/api/users/auth')->assertUnauthorized();
    }

    #[Test]
    public function aPlainUserCannotReachAnyAdminUserAction(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        Passport::actingAs($user);
        $validCreate = [
            'firstName' => 'Mallory',
            'lastName' => 'Example',
            'phoneNumber' => '5550100',
            'email' => 'mallory@example.com',
            'password' => 'password',
            'passwordConfirmation' => 'password',
            'role' => UserRole::SYSTEM_ADMIN->value
        ];
        $validUpdate = [
            'firstName' => 'Changed',
            'lastName' => 'Changed',
            'phoneNumber' => '5550100',
            'birthdate' => now()->subYears(30)->toISOString()
        ];
        $requests = [
            ['POST', '/api/users', $validCreate],
            ['GET', '/api/users', []],
            ['GET', "/api/users/{$otherUser->id}", []],
            ['PUT', "/api/users/{$otherUser->id}", $validUpdate],
            ['DELETE', "/api/users/{$otherUser->id}", []],
        ];
        $before = $otherUser->refresh()->getRawOriginal();

        foreach ($requests as [$method, $uri, $payload]) {
            $this->json($method, $uri, $payload)->assertForbidden()->assertExactJson(['message' => 'Request is forbidden']);
        }

        self::assertSame($before, $otherUser->refresh()->getRawOriginal());
        self::assertFalse(User::withTrashed()->where('email', 'mallory@example.com')->exists());
    }

    #[Test]
    public function aPlainUserCannotChangeAnotherUsersPassword(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        Passport::actingAs($user);

        $this->putJson("/api/users/{$otherUser->id}/password", [
            'currentPassword' => 'password',
            'password' => 'taken-over',
            'passwordConfirmation' => 'taken-over'
        ])->assertBadRequest()->assertExactJson(['success' => false, 'message' => 'Unauthorized to update password.']);

        self::assertTrue(Hash::check('password', $otherUser->refresh()->password));
        self::assertFalse(Hash::check('taken-over', $otherUser->password));
    }

    #[Test]
    public function aPermissionMissingFromTheDatabaseDeniesInsteadOfErroring(): void
    {
        // A deploy that ships a new permission before its seeder has run must fail closed: a Gate that
        // throws turns every guarded endpoint into a 500 instead of a 403.
        /** @var User $admin */
        $admin = User::factory()->withRole(UserRole::SYSTEM_ADMIN)->create();
        Passport::actingAs($admin);
        Permission::findByName(UserPermission::READ_USER->value, UserPermission::getApiGuardName())->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->getJson('/api/users')->assertForbidden()->assertExactJson(['message' => 'Request is forbidden']);
    }

    #[Test]
    public function bothAdminRolesCanReachTheAdminUserActions(): void
    {
        foreach ([UserRole::SYSTEM_ADMIN, UserRole::APP_ADMIN] as $role) {
            /** @var User $admin */
            $admin = User::factory()->withRole($role)->create();
            Passport::actingAs($admin);

            $this->getJson('/api/users')->assertOk();
        }
    }

}
