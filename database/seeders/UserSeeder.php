<?php

namespace Database\Seeders;

use App\Constants\UserRoleConstant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create system admin user
        User::insert([
            'first_name' => 'System',
            'last_name' => 'Admin',
            'username' => Str::before(config('custom.sysad_email'), '@'),
            'email' => config('custom.sysad_email'),
            'email_verified_at' => Carbon::now(),
            'password' => Hash::make(config('custom.sysad_password')),
            'role' => UserRoleConstant::SYSTEM_ADMIN
        ]);
    }

}
