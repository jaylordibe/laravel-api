<?php

namespace Database\Seeders;

use App\Constants\PermissionConstant;
use App\Constants\RoleConstant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        $permissions = PermissionConstant::asList();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // Create roles and assign existing permissions
        $roles = RoleConstant::asList();

        foreach ($roles as $role) {
            $rolePermissions = PermissionConstant::fromRole($role);
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'api'])->givePermissionTo($rolePermissions);
        }
    }

}
