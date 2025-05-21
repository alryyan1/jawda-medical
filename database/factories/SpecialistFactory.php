<?php
namespace Database\Factories;
use App\Models\Specialist;
use Illuminate\Database\Eloquent\Factories\Factory;

class SpecialistFactory extends Factory
{
    protected $model = Specialist::class;

    public function definition(): array
    {
        $arabicSpecialties = [
            'طب العيون', 'طب الأطفال', 'الجراحة العامة', 'الأمراض الجلدية', 'أمراض القلب',
            'طب الأعصاب', 'الطب الباطني', 'طب الأسنان', 'الأنف والأذن والحنجرة', 'العظام'
        ];
        return [
            'name' => $this->faker->randomElement($arabicSpecialties),
            // 'created_at' and 'updated_at' are not in the model, so omit them or add to model
        ];
    }
}