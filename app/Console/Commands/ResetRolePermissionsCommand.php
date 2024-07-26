<?php

namespace App\Console\Commands;

use App\Constants\PermissionConstant;
use App\Constants\RoleConstant;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ResetRolePermissionsCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-role-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset role permissions';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info(PHP_EOL . "Resetting role permissions..." . PHP_EOL);

        // Create permissions
        $permissions = PermissionConstant::asList();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => PermissionConstant::getApiGuard()]);
        }

        // Create roles and assign existing permissions
        $roles = RoleConstant::asList();

        foreach ($roles as $role) {
            $rolePermissions = PermissionConstant::fromRole($role);
            Role::firstOrCreate(['name' => $role, 'guard_name' => PermissionConstant::getApiGuard()])->givePermissionTo($rolePermissions);
        }

        $this->info(PHP_EOL . "Done resetting role permissions" . PHP_EOL);
    }

}
