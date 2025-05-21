<?php
namespace Database\Factories;
use App\Models\ServiceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceGroupFactory extends Factory
{
    protected $model = ServiceGroup::class;

    public function definition(): array
    {
        $arabicServiceGroups = [
            'خدمات العيادات العامة', 'خدمات الأخصائيين', 'خدمات المختبر', 'خدمات الأشعة',
            'خدمات الطوارئ', 'خدمات الأسنان', 'العلاج الطبيعي', 'الإجراءات التمريضية'
        ];
        return [
            'name' => $this->faker->randomElement($arabicServiceGroups),
            // 'created_at' and 'updated_at' are not in the model, so omit them or add to model
        ];
    }
}