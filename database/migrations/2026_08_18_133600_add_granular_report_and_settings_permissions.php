<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Permission name => the menu item it gates. Keys map to the report
     * routes/labels defined in AppLayout.tsx.
     *
     * @var array<string, string>
     */
    private array $reportPermissions = [
        'عرض تقرير المختبر' => '/reports/lab-general',
        'عرض تقرير عيادات اليوم' => '/reports/doctor-shifts',
        'عرض التقرير العام' => '/reports/clinic-shift-summary',
        'عرض تقرير المصروفات' => '/reports/costs',
        'عرض تقرير المصروفات اليومية' => '/reports/daily-costs',
        'عرض تقرير الورديات الشهري' => '/reports/monthly-shifts',
        'عرض تقرير دخل المختبر الشهري' => '/reports/monthly-lab-income',
        'عرض تقرير دخل الخدمات الشهري' => '/reports/monthly-service-income',
        'عرض تقرير أداء الشركات' => '/reports/company-performance',
        'عرض تقرير استحقاقات الأطباء للشركات' => '/reports/doctor-company-entitlement',
        'عرض تقرير تكاليف الخدمات للمرضى' => '/reports/patient-service-costs',
        'عرض تقرير مقارنة الدخل السنوية' => '/reports/yearly-income-comparison',
        'عرض تقرير تردد المرضى' => '/reports/yearly-patient-frequency',
        'عرض تقرير إحصائيات الأطباء' => '/reports/doctor-statistics',
        'عرض تقرير إحصائيات الخدمات' => '/reports/service-statistics',
        'عرض تقرير إحصائيات تحاليل المختبر' => '/reports/lab-test-statistics',
    ];

    /**
     * @var array<string, string>
     */
    private array $settingsPermissions = [
        'عرض اعدادات عام' => '/settings/general',
        'عرض اعدادات الشركات' => '/settings/companies',
        'عرض اعدادات الجهات' => '/settings/parties',
        'عرض اعدادات المختبر' => '/settings/laboratory',
        'عرض اعدادات جداول الربط' => '/settings/laboratory/binding-matching',
        'عرض اعدادات مجموعات الخدمات' => '/settings/service-groups',
        'عرض اعدادات العمليات الجراحية' => '/settings/operations',
        'عرض اعدادات الخدمات' => '/settings/services',
        'عرض اعدادات العروض' => '/settings/offers',
        'عرض اعدادات الأطباء' => '/settings/doctors',
        'عرض اعدادات التخصصات الطبية' => '/settings/specialists',
        'عرض اعدادات المستخدمين' => '/settings/users',
        'عرض اعدادات الأدوار' => '/settings/roles',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $reportPermissionNames = array_keys($this->reportPermissions);
        $settingsPermissionNames = array_keys($this->settingsPermissions);

        foreach (array_merge($reportPermissionNames, $settingsPermissionNames) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Roles that already had the blanket "view reports"/"view settings"
        // permission keep seeing every item they see today; per-item access
        // can be narrowed afterwards from the Roles settings page.
        $reportRoles = Role::whereHas('permissions', fn ($q) => $q->where('name', 'عرض التقارير'))->get();
        foreach ($reportRoles as $role) {
            $role->givePermissionTo($reportPermissionNames);
        }

        $settingsRoles = Role::whereHas('permissions', fn ($q) => $q->where('name', 'عرض الاعدادات'))->get();
        foreach ($settingsRoles as $role) {
            $role->givePermissionTo($settingsPermissionNames);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $names = array_merge(array_keys($this->reportPermissions), array_keys($this->settingsPermissions));

        Permission::whereIn('name', $names)->where('guard_name', 'web')->delete();
    }
};
