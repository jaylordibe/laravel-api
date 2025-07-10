<?php

namespace Database\Factories;

use App\Enums\AppPlatform;
use App\Enums\DeviceOs;
use App\Enums\DeviceType;
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
            'app_platform' => fake()->randomElement(AppPlatform::cases())->value,
            'device_type' => fake()->randomElement(DeviceType::cases())->value,
            'device_os' => fake()->randomElement(DeviceOs::cases())->value,
            'device_os_version' => fake()->numerify('##.##.##')
        ];
    }

}
