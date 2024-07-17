<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
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
            'address' => fake()->address(),
            'village_or_barangay' => fake()->streetAddress(),
            'city_or_municipality' => fake()->city(),
            'state_or_province' => fake()->address(),
            'zip_or_postal_code' => fake()->postcode(),
            'country' => fake()->country()
        ];
    }

}
