<?php

namespace Database\Seeders;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        foreach (UserPermission::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => UserPermission::getApiGuardName()]);
        }

        // Create roles and assign existing permissions
        foreach (UserRole::cases() as $userRole) {
            Role::firstOrCreate(['name' => $userRole->value, 'guard_name' => UserPermission::getApiGuardName()])->givePermissionTo(UserPermission::fromUserRole($userRole));
        }
    }

}
