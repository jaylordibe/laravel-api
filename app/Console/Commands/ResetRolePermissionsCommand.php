<?php

namespace App\Console\Commands;

use App\Enums\UserPermission;
use App\Enums\UserRole;
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
        foreach (UserPermission::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => UserPermission::getApiGuardName()]);
        }

        // Delete old permissions that are not in the list
        $permissionNames = collect(UserPermission::cases())->map(fn(UserPermission $userPermission) => $userPermission->value)->toArray();
        Permission::whereNotIn('name', $permissionNames)->where('guard_name', UserPermission::getApiGuardName())->delete();

        // Create roles and assign existing permissions
        foreach (UserRole::cases() as $userRole) {
            Role::firstOrCreate(['name' => $userRole->value, 'guard_name' => UserPermission::getApiGuardName()])->givePermissionTo(UserPermission::fromUserRole($userRole));
        }

        // Delete old roles that are not in the list
        $roleNames = collect(UserRole::cases())->map(fn(UserRole $userRole) => $userRole->value)->toArray();
        Role::whereNotIn('name', $roleNames)->where('guard_name', UserPermission::getApiGuardName())->delete();

        $this->info(PHP_EOL . "Done resetting role permissions" . PHP_EOL);
    }

}
