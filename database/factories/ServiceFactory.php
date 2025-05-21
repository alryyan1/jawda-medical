<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Service::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Sample Arabic medical service names
        $arabicMedicalServices = [
            // General Clinic
            'كشف عام', 'استشارة طبية', 'متابعة حالة', 'فحص طبي شامل', 'شهادة لياقة صحية',
            // Specialized
            'فحص نظر', 'تنظيف أسنان', 'جلسة علاج طبيعي', 'تخطيط قلب', 'فحص جلدية بالمنظار',
            // Procedures
            'إزالة غرز', 'تضميد جروح', 'حقنة عضلية', 'تركيب مغذي وريدي', 'قياس ضغط الدم',
            // Lab related (can also be tests)
            'سحب عينة دم', 'تحليل بول روتيني',
            // Imaging related
            'تصوير أشعة سينية (X-ray)', 'تصوير بالموجات فوق الصوتية (Ultrasound)',
            // Dental
            'حشوة تجميلية', 'خلع ضرس', 'تركيب تقويم أسنان (استشارة)',
        ];

        return [
            'name' => $this->faker->randomElement($arabicMedicalServices),
            
            // Assumes ServiceGroup factory/records exist
            'service_group_id' => ServiceGroup::inRandomOrder()->first()?->id,
            // Or, if you seed ServiceGroups separately:
            // 'service_group_id' => ServiceGroup::inRandomOrder()->first()?->id ?? ServiceGroup::factory(),
            
            'price' => $this->faker->randomFloat(2, 50, 1000), // Price between 50.00 and 1000.00
            'activate' => $this->faker->boolean(90), // 90% chance of being active
            'variable' => $this->faker->boolean(20), // 20% chance of price being variable
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}