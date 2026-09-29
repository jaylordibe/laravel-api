<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use Database\Seeders\PermissionsSeeder;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * DEPLOYMENT.md re-runs PermissionsSeeder on every upgrade, against a database that already has
 * roles and permissions (the test database was seeded once already). It must converge, not duplicate.
 */
class PermissionsSeederFeatureTest extends TestCase
{

    /**
     * The permission names a role holds, sorted.
     *
     * @param UserRole $userRole
     *
     * @return array<int, string>
     */
    private function permissionsOf(UserRole $userRole): array
    {
        return Role::findByName($userRole->value, UserPermission::getApiGuardName())
            ->permissions()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }

    #[Test]
    public function reRunningTheSeederCreatesNoDuplicates(): void
    {
        $this->seed(PermissionsSeeder::class);
        $this->seed(PermissionsSeeder::class);

        self::assertSame(count(UserPermission::cases()), Permission::query()->count());
        self::assertSame(count(UserRole::cases()), Role::query()->count());
    }

    #[Test]
    public function reRunningTheSeederRestoresARolesMissingPermissions(): void
    {
        $role = Role::findByName(UserRole::APP_ADMIN->value, UserPermission::getApiGuardName());
        $role->revokePermissionTo(UserPermission::DELETE_USER->value);

        $this->seed(PermissionsSeeder::class);

        foreach (UserRole::cases() as $userRole) {
            $expected = collect(UserPermission::fromUserRole($userRole))
                ->map(fn (UserPermission $permission): string => $permission->value)
                ->sort()
                ->values()
                ->all();
            self::assertSame($expected, $this->permissionsOf($userRole), "{$userRole->value} must hold exactly its permissions.");
        }
    }

}
