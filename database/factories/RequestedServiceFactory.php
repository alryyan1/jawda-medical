<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RequestedService>
 */

namespace Database\Factories;

use App\Models\RequestedService;
use App\Models\DoctorVisit;
use App\Models\Service;
use App\Models\User;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequestedServiceFactory extends Factory
{
    protected $model = RequestedService::class;

    public function definition(): array
    {
        $service = Service::where('activate', true)->inRandomOrder()->first() ?? Service::factory()->create(['activate' => true]);
        $price = $service->price;
        $count = $this->faker->numberBetween(1, 2);
        $isPaid = $this->faker->boolean(60); // 60% chance of being paid
        $amountPaid = $isPaid ? ($price * $count) : $this->faker->randomFloat(2, 0, $price * $count * 0.8); // Pay up to 80% if not fully paid

        return [
            // doctorvisits_id will be set when creating via DoctorVisit relationship
            'service_id' => $service->id,
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'user_deposited' => $isPaid ? (User::inRandomOrder()->first()?->id ?? User::factory()) : null,
            'doctor_id' => Doctor::inRandomOrder()->first()?->id ?? Doctor::factory(), // Doctor performing this specific service
            'price' => $price,
            'amount_paid' => $amountPaid,
            'endurance' => $this->faker->boolean(30) ? $this->faker->randomFloat(2, 0, $price * 0.5) : 0,
            'is_paid' => $isPaid,
            'discount' => 0, // For simplicity, can add logic for discounts
            'discount_per' => 0,
            'bank' => $isPaid ? $this->faker->boolean : false,
            'count' => $count,
            'doctor_note' =>'',
            'nurse_note' =>'',
            'done' => $isPaid ? $this->faker->boolean(80) : $this->faker->boolean(30), // More likely done if paid
            'approval' => true, // Default to approved
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}