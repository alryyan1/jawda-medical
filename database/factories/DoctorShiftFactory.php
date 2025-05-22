<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DoctorShift>
 */// database/factories/DoctorShiftFactory.php
namespace Database\Factories;

use App\Models\DoctorShift;
use App\Models\Doctor;
use App\Models\Shift; // General clinic shift
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class DoctorShiftFactory extends Factory
{
    protected $model = DoctorShift::class;

    public function definition(): array
    {
        $startTime = $this->faker->optional(0.8, null)?->dateTimeThisMonth(); // 80% chance of having a start time
        $endTime = null;
        $status = false;

        if ($startTime) {
            // 70% chance of still being open if started
            $status = $this->faker->boolean(70);
            if (!$status) { // If closed, set an end time
                $endTime = Carbon::instance($startTime)->addHours($this->faker->numberBetween(2, 8));
            }
        }

        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'shift_id' => Shift::open()->inRandomOrder()->first()?->id ?? Shift::factory()->openState(), // Prefers an existing open general shift
            'doctor_id' => Doctor::inRandomOrder()->first()?->id ?? Doctor::factory(),
            'status' => $status,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_cash_revenue_prooved' => $this->faker->boolean(20),
            'is_cash_reclaim_prooved' => $this->faker->boolean(10),
            'is_company_revenue_prooved' => $this->faker->boolean(15),
            'is_company_reclaim_prooved' => $this->faker->boolean(5),
            'created_at' => $startTime ?? now(), // created_at can be same as start_time
            'updated_at' => $endTime ?? $startTime ?? now(),
        ];
    }

    /**
     * Indicate that the doctor shift is currently active for today.
     */
    public function activeToday(): Factory
    {
        return $this->state(function (array $attributes) {
            // Ensure general shift for today exists and is open
            $generalShift = Shift::open()->today()->first() ?? Shift::factory()->openTodayState()->create();

            return [
                'status' => true,
                'start_time' => Carbon::today()->addHours($this->faker->numberBetween(7, 10))->addMinutes($this->faker->numberBetween(0,59)), // Started earlier today
                'end_time' => null,
                'shift_id' => $generalShift->id, // Link to an open general shift for today
            ];
        });
    }
}