<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DoctorVisit>
 */


namespace Database\Factories;

use App\Models\DoctorVisit;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\User;
use App\Models\Shift;        // General clinic shift
use App\Models\DoctorShift; // Specific doctor's working session
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class DoctorVisitFactory extends Factory
{
    protected $model = DoctorVisit::class;

    public function definition(): array
    {
        $visitDate = Carbon::today(); // Default to today for active shifts
        $statusOptions = ['waiting', 'with_doctor', 'lab_pending', 'completed'];

        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::inRandomOrder()->first()?->id ?? Doctor::factory(),
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(), // Receptionist/creator
            
            // This will be overridden if created via DoctorShift->doctorVisits()->create()
            'shift_id' => Shift::open()->today()->first()?->id ?? Shift::factory()->openTodayState()->create()->id,
            'doctor_shift_id' => null, // Can be set by the seeder logic later

            'visit_date' => $visitDate,
            'visit_time' => $this->faker->time('H:i:s'),
            'status' => $this->faker->randomElement($statusOptions),
            'visit_type' => $this->faker->randomElement(['New', 'Follow-up', 'Consultation']),
            'queue_number' => $this->faker->optional()->numberBetween(1, 100),
            'reason_for_visit' => $this->faker->sentence,
            'visit_notes' => $this->faker->optional()->paragraph,
            'is_new' => $this->faker->boolean(80),
            'number' => $this->faker->numberBetween(1,10), // Clarify purpose of 'number'
            'only_lab' => $this->faker->boolean(10),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Configure the factory to create a visit for a specific DoctorShift.
     */
    public function forDoctorShift(DoctorShift $doctorShift): Factory
    {
        return $this->state(function (array $attributes) use ($doctorShift) {
            return [
                'doctor_id' => $doctorShift->doctor_id,
                'shift_id' => $doctorShift->shift_id, // General shift from doctor's shift
                'doctor_shift_id' => $doctorShift->id,
                'visit_date' => Carbon::parse($doctorShift->start_time)->toDateString(), // Visit date from shift start
                'visit_time' => Carbon::parse($doctorShift->start_time)->addMinutes($this->faker->numberBetween(0, 240))->format('H:i:s'),
            ];
        });
    }
}