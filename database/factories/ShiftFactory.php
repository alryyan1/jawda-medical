<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(), // This will create a new user if none exists
            'total' => 0,
            'bank' => 0,
            'expenses' => 0,
            'touched' => false,
            'closed_at' => null,
            'is_closed' => true, // Default to closed
            'pharmacy_entry' => $this->faker->boolean(10),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Configure the shift to be open.
     */
    public function openState(): Factory
    {
        return $this->state(fn(array $attributes) => ['is_closed' => false, 'closed_at' => null]);
    }

    /**
     * Configure the shift to be open and created today.
     */
    public function openTodayState(): Factory
    {
        return $this->state(fn(array $attributes) => [
            'is_closed' => false,
            'closed_at' => null,
            'created_at' => Carbon::today()->startOfDay()
        ]);
    }

    /**
     * Configure the shift with a specific user.
     */
    public function forUser(User $user): Factory
    {
        return $this->state(fn(array $attributes) => [
            'user_id' => $user->id
        ]);
    }
}
