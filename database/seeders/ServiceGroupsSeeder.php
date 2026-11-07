<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ServiceGroupsSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        \DB::table('service_groups')->delete();

        \DB::table('service_groups')->insert([

            [
                'id' => 1,
                'name' => 'كشف',
            ],

            [
                'id' => 4,
                'name' => 'الاشعه السينية',
            ],

            [
                'id' => 8,
                'name' => 'العمليات',
            ],

            [
                'id' => 12,
                'name' => 'الاطفال',
            ],

            [
                'id' => 16,
                'name' => 'الجراحه',
            ],

            [
                'id' => 19,
                'name' => 'العنبر',
            ],

            [
                'id' => 20,
                'name' => 'العمليه الصغيره',
            ],

            [
                'id' => 21,
                'name' => 'الموجات الصوتية',
            ],

            [
                'id' => 22,
                'name' => 'اسنان',
            ],

            [
                'id' => 23,
                'name' => 'الجراحة العامة عمليات',
            ],

            [
                'id' => 24,
                'name' => 'التجميل الطبي والعلاج بالليزر',
            ],

            [
                'id' => 25,
                'name' => 'العلاج الطبيعي',
            ],

            [
                'id' => 26,
                'name' => 'استشاري جراحة العظام والسلسلة الفقرية',
            ],

            [
                'id' => 27,
                'name' => 'استشاري  الاذن والانف والحنجرة',
            ],

            [
                'id' => 28,
                'name' => 'النساء والتوليد',
            ],
        ]);
    }
}
