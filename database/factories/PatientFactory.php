<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Patient>
 */
// database/factories/PatientFactory.php
namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use App\Models\Shift;
use App\Models\Company;
use App\Models\Doctor; // For patient's primary doctor if any
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        $gender = $this->faker->randomElement(['male', 'female']);
        $arabicMaleFirstNames = ['طارق', 'ياسر', 'عمر', 'حسن', 'إبراهيم'];
        $arabicFemaleFirstNames = ['نور', 'ليلى', 'هناء', 'إيمان', 'دعاء'];
        $arabicLastNames = ['عبدالله', 'محمود', 'إسماعيل', 'صالح', 'الخطيب'];

        $firstName = $gender === 'male' ? $this->faker->randomElement($arabicMaleFirstNames) : $this->faker->randomElement($arabicFemaleFirstNames);
        $lastName = $this->faker->randomElement($arabicLastNames);

        // Generate age parts
        $ageYear = $this->faker->numberBetween(0, 80);
        $ageMonth = ($ageYear == 0) ? $this->faker->numberBetween(0, 11) : (($ageYear < 5) ? $this->faker->numberBetween(0,11) : null);
        $ageDay = ($ageYear == 0 && $ageMonth == 0) ? $this->faker->numberBetween(1, 28) : (($ageYear < 1) ? $this->faker->numberBetween(0,28) : null);


        return [
            'name' => $firstName . ' ' . $lastName,
            'shift_id' => Shift::open()->inRandomOrder()->first()?->id ?? Shift::factory()->openState()->create()->id,
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory()->create()->id, // User who registered
            'doctor_id' => $this->faker->boolean(30) ? (Doctor::inRandomOrder()->first()?->id ?? null) : null, // Optional primary doctor
            'phone' => $this->faker->unique()->numerify('09########'),
            'gender' => $gender,
            'age_day' => $ageDay,
            'age_month' => $ageMonth,
            'age_year' => $ageYear,
            'company_id' => $this->faker->boolean(40) ? (Company::inRandomOrder()->first()?->id ?? null) : null,
            'address' => $this->faker->address,
            'is_lab_paid' => $this->faker->boolean,
            'lab_paid' => $this->faker->boolean ? $this->faker->numberBetween(0, 500) : 0,
            'result_is_locked' => $this->faker->boolean(10),
            'sample_collected' => $this->faker->boolean,
            'visit_number' => $this->faker->numberBetween(1, 5),
            'result_auth' => true,
            'auth_date' => now(),
            'present_complains' => $this->faker->sentence,
            // Fill other NOT NULL fields with defaults
            'history_of_present_illness' => $this->faker->paragraph(1),
            'procedures' => $this->faker->words(3, true),
            'provisional_diagnosis' => $this->faker->sentence(3),
            'bp' => $this->faker->numberBetween(90, 140) . '/' . $this->faker->numberBetween(60, 90),
            'temp' => $this->faker->randomFloat(1, 36.0, 39.5),
            'weight' => $this->faker->randomFloat(1, 5, 120),
            'height' => $this->faker->randomFloat(2, 0.5, 2.0), // in meters, adjust if cm
            'discount' => 0,
            'drug_history' => '', 'family_history' => '', 'rbs' => '', 'doctor_finish' => $this->faker->boolean,
            'care_plan' => '', 'doctor_lab_request_confirm' => false, 'doctor_lab_urgent_confirm' => false,
            'general_examination_notes' => '', 'patient_medical_history' => '', 'social_history' => '',
            'allergies' => '', 'general' => '', 'skin' => '', 'head' => '', 'eyes' => '', 'ear' => '',
            'nose' => '', 'mouth' => '', 'throat' => '', 'neck' => '', 'respiratory_system' => '',
            'cardio_system' => '', 'git_system' => '', 'genitourinary_system' => '', 'nervous_system' => '',
            'musculoskeletal_system' => '', 'neuropsychiatric_system' => '', 'endocrine_system' => '',
            'peripheral_vascular_system' => '', 'referred' => '', 'discount_comment' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}