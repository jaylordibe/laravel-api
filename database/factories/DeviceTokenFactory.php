<?php

namespace Database\Factories;

use App\Constants\AppPlatformConstant;
use App\Constants\DeviceOsConstant;
use App\Constants\DeviceTypeConstant;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceToken>
 */
class DeviceTokenFactory extends Factory
{

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->create()->id,
            'token' => fake()->sha256(),
            'app_platform' => fake()->randomElement(AppPlatformConstant::asList()),
            'device_type' => fake()->randomElement(DeviceTypeConstant::asList()),
            'device_os' => fake()->randomElement(DeviceOsConstant::asList()),
            'device_os_version' => fake()->numerify('##.##.##')
        ];
    }

}
