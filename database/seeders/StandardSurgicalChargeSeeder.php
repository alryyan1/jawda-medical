<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StandardSurgicalChargeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $charges = [
            ['name' => 'اخصائي الجراحة', 'type' => 'staff'],
            ['name' => 'مساعد الجراح', 'type' => 'staff'],
            ['name' => 'اخصائي التخدير', 'type' => 'staff'],
            ['name' => 'مساعد التخدير', 'type' => 'staff'],
            ['name' => 'تقني التحضير', 'type' => 'staff'],
            ['name' => 'فتح ملف', 'type' => 'center'],
            ['name' => 'الاقامة', 'type' => 'center'],
            ['name' => 'عناية مكثفة', 'type' => 'center'],
            ['name' => 'خدمات - التنويم', 'type' => 'center'],
            ['name' => 'المركز', 'type' => 'center'],
            ['name' => 'المعدات', 'type' => 'staff'],
            ['name' => 'المستهلكات', 'type' => 'center'],
            ['name' => 'أشعة داخل العملية', 'type' => 'center'],
            ['name' => 'عامل نظافة', 'type' => 'staff'],
            ['name' => 'تمريض إفاقة', 'type' => 'staff'],
            ['name' => 'خدمات بنك الدم', 'type' => 'staff'],
        ];

        foreach ($charges as $charge) {
            \App\Models\StandardSurgicalCharge::updateOrCreate(
                ['name' => $charge['name']],
                ['type' => $charge['type']]
            );
        }
    }
}
