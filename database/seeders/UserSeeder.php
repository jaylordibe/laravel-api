<?php

namespace Database\Seeders;

use App\Constants\UserRoleConstant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{

    public function __construct()
    {
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->createSystemAdminUser();
    }

    /**
     * Create system admin user.
     * @return void
     */
    private function createSystemAdminUser(): void
    {
        $systemAdminInfo = [
            'first_name' => UserRoleConstant::SYSTEM_ADMIN,
            'middle_name' => '',
            'last_name' => 'User',
            'email' => env('SYSTEM_ADMIN_EMAIL'),
            'username' => 'systemadmin',
            'password' => Hash::make(env('SYSTEM_ADMIN_PASSWORD')),
            'role' => UserRoleConstant::SYSTEM_ADMIN,
            'phone_number' => '',
            'address' => '',
            'profile_image' => 'https://i.imgur.com/UJ0N2SN.jpg',
            'email_verified_at' => Carbon::now()
        ];
        User::insert($systemAdminInfo);
    }
}
