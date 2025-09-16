<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'web';

        // Clear existing permissions and related pivot records first
        Schema::disableForeignKeyConstraints();
        DB::table('role_has_permissions')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('permissions')->truncate();
        Schema::enableForeignKeyConstraints();

        $permissions = [
            'فتح ورديه ماليه',
            'اغلاق ورديه ماليه',
            'فتح ورديه طبيب',
            'غلق ورديه طبيب',
            'تسجيل مريض كاش',
            'تسجيل مريض تامين',
            'سداد خدمات المريض',
            'سداد تحاليل المريض',
            'حذف خدمات المريض',
            'حذف تحاليل المريض',
            'الغاء سداد خدمات المريض',
            'الغاء سداد تحاليل المريض',
            'تعديل علي نتائج المريض',
            'تعديل علي بيانات المريض',
            'اظهار كل اطباء العيادات',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => $guardName,
            ]);
        }
    }
}