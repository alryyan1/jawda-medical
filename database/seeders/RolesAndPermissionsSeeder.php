<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use App\Models\User;

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

        // Seed default nav_items per user_type
        $this->seedDefaultNavItems();
    }

    /**
     * Seed default navigation items for users based on their user_type
     */
    private function seedDefaultNavItems(): void
    {
        // Default nav items per user_type
        $defaultNavItems = [
            'استقبال معمل' => [
                '/dashboard',
                '/lab-reception',
                '/lab-sample-collection',
                '/cash-reconciliation',
                '/patients'
            ],
            'ادخال نتائج' => [
                '/dashboard',
                '/lab-workstation',
                '/lab-sample-collection',
                '/patients'
            ],
            'استقبال عياده' => [
                '/dashboard',
                '/clinic',
                '/cash-reconciliation',
                '/patients',
                '/admissions',
                '/online-booking'
            ],
            'خزنه موحده' => [
                '/dashboard',
                '/clinic',
                '/cash-reconciliation',
                '/patients'
            ],
            'تامين' => [
                '/dashboard',
                '/clinic',
                '/cash-reconciliation',
                '/lab-reception',
                '/lab-sample-collection',
                '/patients'
            ],
        ];

        // Update users with default nav_items based on their user_type
        // Only update users where nav_items is NULL (not already customized)
        foreach ($defaultNavItems as $userType => $navItems) {
            User::where('user_type', $userType)
                ->whereNull('nav_items')
                ->update(['nav_items' => json_encode($navItems)]);
        }

        // Admin users (no user_type) should remain with NULL nav_items
        // They will need to configure manually
    }
}