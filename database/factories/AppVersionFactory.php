<?php

namespace Database\Factories;

use App\Constants\AppPlatformConstant;
use App\Models\AppVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppVersion>
 */
class AppVersionFactory extends Factory
{

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version' => fake()->unique()->numerify('##.##.##'),
            'description' => fake()->text(),
            'platform' => fake()->randomElement(AppPlatformConstant::asList()),
            'release_date' => now(),
            'download_url' => fake()->url(),
            'force_update' => fake()->boolean()
        ];
    }

}
