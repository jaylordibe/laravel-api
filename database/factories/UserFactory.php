<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\User;
use App\Utils\AppUtil;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Random\RandomException;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     * @throws RandomException
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->lastName(),
            'last_name' => fake()->lastName(),
            'username' => AppUtil::generateUniqueToken() . fake()->unique()->userName(),
            'email' => AppUtil::generateUniqueToken() . fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone_number' => fake()->phoneNumber(),
            'gender' => fake()->randomElement(Gender::cases()),
            'birthdate' => now()->subYears(random_int(1, 20)),
            'timezone' => fake()->timezone(),
            'profile_image' => fake()->imageUrl()
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Assign a specific role.
     *
     * Usage:
     * User::factory()->withRole(UserRole::APP_ADMIN)->create();
     */
    public function withRole(UserRole $role): static
    {
        return $this->afterCreating(function (User $user) use ($role) {
            $user->assignRole($role);
        });
    }

}
