<?php

namespace Database\Seeders;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create system admin user
        $systemAdminUser = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => Str::before(config('custom.sysad_email'), '@'),
            'email' => config('custom.sysad_email'),
            'email_verified_at' => now(),
            'password' => Hash::make(config('custom.sysad_password'))
        ]);
        $systemAdminRole = Role::findByName(UserRole::SYSTEM_ADMIN->value, UserPermission::getApiGuardName());
        $systemAdminUser->assignRole($systemAdminRole);

        // Create app admin user
        $appAdminUser = User::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => Str::before(config('custom.appad_email'), '@'),
            'email' => config('custom.appad_email'),
            'email_verified_at' => now(),
            'password' => Hash::make(config('custom.appad_password'))
        ]);
        $appAdminRole = Role::findByName(UserRole::APP_ADMIN->value, UserPermission::getApiGuardName());
        $appAdminUser->assignRole($appAdminRole);

        $this->createTestUsers();
    }

    /**
     * Create test users for each user role except system and app admin.
     */
    private function createTestUsers(): void
    {
        $userRoles = UserRole::cases();

        foreach ($userRoles as $userRole) {
            if ($userRole === UserRole::SYSTEM_ADMIN || $userRole === UserRole::APP_ADMIN) {
                continue; // Skip system and app admin roles for test users
            }

            $nameParts = explode('_', $userRole->value);
            $username = str_replace('_', '', $userRole->value);

            $user = User::factory()->create([
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? 'User',
                'username' => $username,
                'email' => "{$username}@" . config('custom.app_domain'),
                'email_verified_at' => now(),
                'password' => Hash::make('@password1!')
            ]);

            $role = Role::findByName($userRole->value, UserPermission::getApiGuardName());
            $user->assignRole($role);
        }
    }

}
