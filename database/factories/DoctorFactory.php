<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Specialist;
use App\Models\FinanceAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Doctor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Sample Arabic first names and last names
        $arabicFirstNames = ['أحمد', 'محمد', 'علي', 'فاطمة', 'زينب', 'عائشة', 'خالد', 'سارة', 'يوسف', 'مريم'];
        $arabicLastNames = ['الهاشمي', 'التميمي', 'القحطاني', 'المالكي', 'الغامدي', 'الشهري', 'العمري', 'الزهراني', 'الحربي', 'المطيري'];

        $firstName = $this->faker->randomElement($arabicFirstNames);
        $lastName = $this->faker->randomElement($arabicLastNames);
        $fullName = 'د. ' . $firstName . ' ' . $lastName; // "Dr. FirstName LastName"

        return [
            'name' => $fullName,
            'phone' => $this->faker->numerify('09########'), // Sudanese phone format example
            'cash_percentage' => $this->faker->randomFloat(2, 5, 20),    // e.g., 5.00 to 20.00
            'company_percentage' => $this->faker->randomFloat(2, 10, 25),
            'static_wage' => $this->faker->randomFloat(2, 500, 5000),
            'lab_percentage' => $this->faker->randomFloat(2, 5, 15),
            
            // Assumes Specialist and FinanceAccount factories/records exist
            // Use ::factory() if you want to create them on the fly,
            // or ::inRandomOrder()->first()->id if you want to pick existing ones.
            'specialist_id' => Specialist::factory(), // Creates a new Specialist
            // Or, if you seed Specialists separately:
            // 'specialist_id' => Specialist::inRandomOrder()->first()?->id ?? Specialist::factory(),

            'start' => $this->faker->numberBetween(1000, 9999), // Example for 'start' column
            'image' => null, // Or $this->faker->imageUrl(640, 480, 'people') if you want placeholder images

            // Ensure FinanceAccount records exist or use factories
            'finance_account_id' => null, // Or pick existing
            'finance_account_id_insurance' => null, // Or pick existing

            'calc_insurance' => $this->faker->boolean(70), // 70% chance of being true
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}