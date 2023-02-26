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
            'username' => Str::before(env('SYSTEM_ADMIN_EMAIL'), '@'),
            'email' => env('SYSTEM_ADMIN_EMAIL'),
            'email_verified_at' => Carbon::now(),
            'password' => Hash::make(env('SYSTEM_ADMIN_PASSWORD')),
            'role' => UserRoleConstant::SYSTEM_ADMIN
        ]);
    }

}
