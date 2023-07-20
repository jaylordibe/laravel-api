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
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => Str::before(config('custom.sysad_email'), '@'),
            'email' => config('custom.sysad_email'),
            'email_verified_at' => Carbon::now(),
            'password' => Hash::make(config('custom.sysad_password'))
        ]);

        // Assign user role
        $systemAdminRole = Role::findByName(RoleConstant::SYSTEM_ADMIN, 'api');
        $user->assignRole($systemAdminRole);

        // Create app admin user
        $user = User::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => Str::before(config('custom.appad_email'), '@'),
            'email' => config('custom.appad_email'),
            'email_verified_at' => Carbon::now(),
            'password' => Hash::make(config('custom.appad_password'))
        ]);

        // Assign user role
        $systemAdminRole = Role::findByName(RoleConstant::APP_ADMIN, 'api');
        $user->assignRole($systemAdminRole);
    }

}
