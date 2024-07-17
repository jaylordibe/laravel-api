<?php

namespace Database\Factories;

use App\Constants\AppVersionPlatformConstant;
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
            'version' => $this->faker->unique()->numerify('##.##.##'),
            'description' => $this->faker->text(),
            'platform' => $this->faker->randomElement(AppVersionPlatformConstant::asList()),
            'release_date' => now(),
            'download_url' => $this->faker->url(),
            'force_update' => $this->faker->boolean()
        ];
    }

}
