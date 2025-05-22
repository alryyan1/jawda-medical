<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'doctor_id' => null, // This will be null by default, can be set manually when needed
            'is_nurse' => fake()->boolean(20), // 20% chance of being a nurse
            'user_money_collector_type' => fake()->randomElement(['lab', 'company', 'clinic', 'all']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Configure the model factory to create a nurse user.
     */
    public function nurse(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_nurse' => true,
            ];
        });
    }

    /**
     * Configure the model factory to set a specific money collector type.
     */
    public function moneyCollectorType(string $type): static
    {
        return $this->state(function (array $attributes) use ($type) {
            return [
                'user_money_collector_type' => $type,
            ];
        });
    }

    /**
     * Configure the model factory to associate with a doctor.
     */
    public function forDoctor($doctorId): static
    {
        return $this->state(function (array $attributes) use ($doctorId) {
            return [
                'doctor_id' => $doctorId,
            ];
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
