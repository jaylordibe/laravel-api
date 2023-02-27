<?php

namespace Database\Seeders;

use App\Constants\RoleConstant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
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
        $user = User::create([
            'first_name' => 'System',
            'last_name' => 'Admin',
            'username' => Str::before(config('custom.sysad_email'), '@'),
            'email' => config('custom.sysad_email'),
            'email_verified_at' => Carbon::now(),
            'password' => Hash::make(config('custom.sysad_password'))
        ]);

        // Assign user role
        $systemAdminRole = Role::findByName(RoleConstant::SYSTEM_ADMIN, 'api');
        $user->assignRole($systemAdminRole);
    }

}
