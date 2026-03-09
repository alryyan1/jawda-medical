<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\Room;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Admission>
 */
class AdmissionFactory extends Factory
{
    protected $model = Admission::class;

    public function definition(): array
    {
        $ward = Ward::first() ?? Ward::create([
            'name' => 'Test Ward',
            'status' => true,
        ]);
        $room = Room::firstWhere('ward_id', $ward->id) ?? Room::create([
            'ward_id' => $ward->id,
            'room_number' => (string) $this->faker->numberBetween(100, 199),
            'room_type' => 'normal',
            'capacity' => 4,
            'status' => true,
            'price_per_day' => 200,
        ]);
        $bed = Bed::firstWhere('room_id', $room->id) ?? Bed::create([
            'room_id' => $room->id,
            'bed_number' => (string) $this->faker->numberBetween(1, 10),
            'status' => 'available',
        ]);
        $patient = Patient::first() ?? Patient::factory()->create();
        $user = User::first() ?? User::factory()->create();

        return [
            'patient_id' => $patient->id,
            'ward_id' => $ward->id,
            'bed_id' => $bed->id,
            'user_id' => $user->id,
            'admission_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'admission_reason' => $this->faker->sentence(),
            'diagnosis' => $this->faker->sentence(),
            'status' => 'admitted',
        ];
    }
}
