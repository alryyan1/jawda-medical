<?php

namespace Database\Seeders;

use App\Models\OperationItem;
use Illuminate\Database\Seeder;

class OperationItemSeeder extends Seeder
{
    public function run()
    {
        $items = [
            // Staff (Kader)
            ['name' => 'أخصائي الجراحة', 'type' => 'staff'],
            ['name' => 'مساعد الجراح', 'type' => 'staff'],
            ['name' => 'أخصائي التخدير', 'type' => 'staff'],
            ['name' => 'مساعد التخدير', 'type' => 'staff'],
            ['name' => 'تقني التحضير', 'type' => 'staff'],
            ['name' => 'تمريض إفاقة', 'type' => 'staff'],
            ['name' => 'عامل نظافة', 'type' => 'staff'], // Assuming staff based on context, move to center if requested

            // Center (Al-Markaz)
            ['name' => 'فتح ملف', 'type' => 'center'], // File Opening
            ['name' => 'الإقامة', 'type' => 'center'], // Accommodation
            ['name' => 'عناية مكثفة', 'type' => 'center'], // ICU
            ['name' => 'المركز', 'type' => 'center'], // Center Share
            ['name' => 'المعدات', 'type' => 'center'], // Equipment
            ['name' => 'المستهلكات', 'type' => 'center'], // Consumables
            ['name' => 'أشعة داخل العملية', 'type' => 'center'], // Intraoperative X-ray
            ['name' => 'خدمات بنك الدم', 'type' => 'center'], // Blood Bank Services
            ['name' => 'خدمات - التنويم', 'type' => 'center'], // Services - Admission
        ];

        foreach ($items as $item) {
            OperationItem::updateOrCreate(
                ['name' => $item['name']], // Unique by name to avoid duplicates
                ['type' => $item['type']]
            );
        }
    }
}
