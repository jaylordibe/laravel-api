<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * `app:check-role-permissions` runs after the permission sync on every deploy. A check that only
 * ever passes proves nothing, so each drift it exists to catch is driven here and must fail it.
 */
class CheckRolePermissionsCommandFeatureTest extends TestCase
{

    #[Test]
    public function itPassesWhenTheDatabaseMatchesTheMapping(): void
    {
        $this->artisan('app:check-role-permissions')->assertSuccessful();
    }

    #[Test]
    public function itFailsWhenARoleLacksAMappedPermission(): void
    {
        Role::findByName(UserRole::APP_ADMIN->value, UserPermission::getApiGuardName())
            ->revokePermissionTo(UserPermission::DELETE_USER->value);

        $this->artisan('app:check-role-permissions')
            ->expectsOutputToContain('Role app_admin lacks delete_user.')
            ->assertFailed();
    }

    #[Test]
    public function itFailsWhenARoleHoldsAnUnmappedPermission(): void
    {
        Permission::create(['name' => 'retired_permission', 'guard_name' => UserPermission::getApiGuardName()]);
        Role::findByName(UserRole::APP_ADMIN->value, UserPermission::getApiGuardName())
            ->givePermissionTo('retired_permission');

        $this->artisan('app:check-role-permissions')
            ->expectsOutputToContain('Role app_admin holds retired_permission, which its mapping does not grant.')
            ->assertFailed();
    }

    #[Test]
    public function itFailsWhenAMappedPermissionIsMissingFromTheDatabase(): void
    {
        Permission::findByName(UserPermission::READ_ACTIVITY_LOG->value, UserPermission::getApiGuardName())->delete();

        $this->artisan('app:check-role-permissions')
            ->expectsOutputToContain('Permission read_activity_log is missing from the database.')
            ->assertFailed();
    }

}
