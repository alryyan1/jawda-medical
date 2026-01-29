<?php

namespace Database\Seeders\system_setup_seeders;

use Illuminate\Database\Seeder;

class PermissionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('permissions')->delete();
        
        \DB::table('permissions')->insert(array (
            
            array (
                'id' => 3,
                'name' => 'اضافه صنف',
                'guard_name' => 'web',
                'created_at' => '2024-06-10 01:28:03',
                'updated_at' => '2024-06-11 16:42:35',
            ),
            
            array (
                'id' => 4,
                'name' => 'تعديل صنف',
                'guard_name' => 'web',
                'created_at' => '2024-06-10 14:22:08',
                'updated_at' => '2024-06-11 16:42:35',
            ),
            
            array (
                'id' => 5,
                'name' => 'عرض الاصناف',
                'guard_name' => 'web',
                'created_at' => '2024-06-10 14:23:04',
                'updated_at' => '2024-06-11 16:43:01',
            ),
            
            array (
                'id' => 6,
                'name' => 'حذف الاصناف',
                'guard_name' => 'web',
                'created_at' => '2024-06-10 14:26:02',
                'updated_at' => '2024-06-11 16:43:01',
            ),
            
            array (
                'id' => 7,
                'name' => 'انشاء فاتوره',
                'guard_name' => 'web',
                'created_at' => '2024-06-10 20:19:25',
                'updated_at' => '2024-06-10 20:19:25',
            ),
            
            array (
                'id' => 9,
                'name' => 'اذن طلب',
                'guard_name' => 'web',
                'created_at' => '2024-06-10 20:20:35',
                'updated_at' => '2024-06-10 20:20:35',
            ),
            
            array (
                'id' => 10,
                'name' => 'اذن صرف',
                'guard_name' => 'web',
                'created_at' => '2024-06-10 20:22:09',
                'updated_at' => '2024-06-10 20:22:09',
            ),
            
            array (
                'id' => 11,
                'name' => 'اضافه مورد',
                'guard_name' => 'web',
                'created_at' => '2024-06-10 20:25:32',
                'updated_at' => '2024-06-10 20:25:32',
            ),
            
            array (
                'id' => 12,
                'name' => 'حذف مورد',
                'guard_name' => 'web',
                'created_at' => '2024-06-10 20:25:39',
                'updated_at' => '2024-06-10 20:25:39',
            ),
            
            array (
                'id' => 13,
                'name' => 'تعديل مورد',
                'guard_name' => 'web',
                'created_at' => '2024-06-10 20:25:56',
                'updated_at' => '2024-06-10 20:25:56',
            ),
            
            array (
                'id' => 14,
                'name' => 'اضافه للمخزون',
                'guard_name' => 'web',
                'created_at' => '2024-06-10 20:30:00',
                'updated_at' => '2024-06-10 20:30:00',
            ),
            
            array (
                'id' => 15,
                'name' => 'حذف فاتوره',
                'guard_name' => 'web',
                'created_at' => '2024-06-11 00:13:56',
                'updated_at' => '2024-06-11 00:13:56',
            ),
            
            array (
                'id' => 16,
                'name' => 'حذف اذن طلب',
                'guard_name' => 'web',
                'created_at' => '2024-06-11 00:49:46',
                'updated_at' => '2024-06-11 00:56:35',
            ),
            
            array (
                'id' => 17,
                'name' => 'reports',
                'guard_name' => 'web',
                'created_at' => '2024-06-11 16:00:28',
                'updated_at' => '2024-06-11 16:39:33',
            ),
            
            array (
                'id' => 18,
                'name' => 'الغاء سداد فحص',
                'guard_name' => 'web',
                'created_at' => '2024-07-13 03:54:08',
                'updated_at' => '2024-07-13 03:54:08',
            ),
            
            array (
                'id' => 19,
                'name' => 'التخفيض',
                'guard_name' => 'web',
                'created_at' => '2024-07-13 03:54:08',
                'updated_at' => '2024-07-13 03:54:08',
            ),
            
            array (
                'id' => 20,
                'name' => 'سداد فحص',
                'guard_name' => 'web',
                'created_at' => '2024-07-13 03:54:08',
                'updated_at' => '2024-07-13 03:54:08',
            ),
            
            array (
                'id' => 21,
                'name' => 'تعديل بيانات المريض',
                'guard_name' => 'web',
                'created_at' => '2024-07-13 03:54:08',
                'updated_at' => '2024-07-13 03:54:08',
            ),
            
            array (
                'id' => 22,
                'name' => 'الغاء سداد خدمه',
                'guard_name' => 'web',
                'created_at' => '2024-07-13 03:54:08',
                'updated_at' => '2024-07-13 03:54:08',
            ),
            
            array (
                'id' => 23,
                'name' => 'سداد خدمه',
                'guard_name' => 'web',
                'created_at' => '2024-07-13 03:54:08',
                'updated_at' => '2024-07-13 03:54:08',
            ),
            
            array (
                'id' => 24,
                'name' => 'حذف خدمه',
                'guard_name' => 'web',
                'created_at' => '2024-07-13 03:54:08',
                'updated_at' => '2024-07-13 03:54:08',
            ),
            
            array (
                'id' => 25,
                'name' => 'اضافه عميل',
                'guard_name' => 'web',
                'created_at' => '2024-07-22 12:59:18',
                'updated_at' => '2024-07-22 12:59:18',
            ),
            
            array (
                'id' => 26,
                'name' => 'سداد صيدليه',
                'guard_name' => 'web',
                'created_at' => '2024-12-19 00:20:06',
                'updated_at' => '2024-12-19 00:20:06',
            ),
            
            array (
                'id' => 27,
                'name' => 'الغاء سداد صيدليه',
                'guard_name' => 'web',
                'created_at' => '2024-12-19 00:20:26',
                'updated_at' => '2024-12-19 00:20:26',
            ),
            
            array (
                'id' => 28,
                'name' => 'اضافه منتج',
                'guard_name' => 'web',
                'created_at' => '2026-01-29 18:48:04',
                'updated_at' => '2026-01-29 18:48:04',
            ),
            
            array (
                'id' => 29,
                'name' => 'تعديل منتج',
                'guard_name' => 'web',
                'created_at' => '2026-01-29 18:48:04',
                'updated_at' => '2026-01-29 18:48:04',
            ),
            
            array (
                'id' => 30,
                'name' => 'عرض منتج',
                'guard_name' => 'web',
                'created_at' => '2026-01-29 18:48:04',
                'updated_at' => '2026-01-29 18:48:04',
            ),
            
            array (
                'id' => 31,
                'name' => 'دفع مبيعات',
                'guard_name' => 'web',
                'created_at' => '2026-01-29 18:48:04',
                'updated_at' => '2026-01-29 18:48:04',
            ),
            
            array (
                'id' => 32,
                'name' => 'الغاء دفع مبيعات',
                'guard_name' => 'web',
                'created_at' => '2026-01-29 18:48:04',
                'updated_at' => '2026-01-29 18:48:04',
            ),
            
            array (
                'id' => 33,
                'name' => 'تعديل النتائج',
                'guard_name' => 'web',
                'created_at' => '2026-01-29 18:48:04',
                'updated_at' => '2026-01-29 18:48:04',
            ),
            
            array (
                'id' => 34,
                'name' => 'اضافه قيد',
                'guard_name' => 'web',
                'created_at' => '2026-01-29 18:48:04',
                'updated_at' => '2026-01-29 18:48:04',
            ),
            
            array (
                'id' => 35,
                'name' => 'اضافه مصروف',
                'guard_name' => 'web',
                'created_at' => '2026-01-29 18:48:04',
                'updated_at' => '2026-01-29 18:48:04',
            ),
        ));
        
        
    }
}