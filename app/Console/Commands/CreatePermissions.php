<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class CreatePermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create default permissions for the application';

    /**
     * List of permissions to create
     *
     * @var array
     */
    protected $permissions = [
        'سداد فحص',
        'الغاء سداد فحص',
        'تخفيض فحص',
        'حذف فحص مضاف',
        'تحقيق نتيجه',
        'طباعه نتيجه',
        'تعديل بيانات',
        'سداد خدمه',
        'الغاء سداد خدمه',
        'حذف خدمه مضافه',
        'تخفيض خدمه',
        'تسجيل مريض كاش',
        'تسجيل مريض تامين',
        'فتح ورديه ماليه',
        'اغلاق ورديه ماليه',
        'عرض التقارير',
        'عرض الاعدادات',
        'اضافه خدمه',
        'اضافه فحص',
        'تعديل نتائج المختبر',
        'عرض كل مرضي المختبر',
        'عرض تقرير المختبر',
        'عرض تقرير عيادات اليوم',
        'عرض التقرير العام',
        'عرض تقرير المصروفات',
        'عرض تقرير المصروفات اليومية',
        'عرض تقرير الورديات الشهري',
        'عرض تقرير دخل المختبر الشهري',
        'عرض تقرير دخل الخدمات الشهري',
        'عرض تقرير أداء الشركات',
        'عرض تقرير استحقاقات الأطباء للشركات',
        'عرض تقرير تكاليف الخدمات للمرضى',
        'عرض تقرير مقارنة الدخل السنوية',
        'عرض تقرير تردد المرضى',
        'عرض تقرير إحصائيات الأطباء',
        'عرض تقرير إحصائيات الخدمات',
        'عرض تقرير إحصائيات تحاليل المختبر',
        'عرض تقرير خصومات المختبر للمرضى',
        'عرض تقرير أرقام هواتف المرضى',
        'عرض اعدادات عام',
        'عرض اعدادات الشركات',
        'عرض اعدادات الجهات',
        'عرض اعدادات المختبر',
        'عرض اعدادات جداول الربط',
        'عرض اعدادات مجموعات الخدمات',
        'عرض اعدادات العمليات الجراحية',
        'عرض اعدادات الخدمات',
        'عرض اعدادات العروض',
        'عرض اعدادات الأطباء',
        'عرض اعدادات التخصصات الطبية',
        'عرض اعدادات المستخدمين',
        'عرض اعدادات الأدوار',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating permissions...');
        $this->newLine();

        $created = 0;
        $existing = 0;
        $errors = 0;

        DB::beginTransaction();

        try {
            // Delete all existing permissions and their relationships
            $this->info('Deleting existing permissions...');

            // Delete from pivot tables first to avoid foreign key constraints
            DB::table('model_has_permissions')->delete();
            DB::table('role_has_permissions')->delete();

            // Disable foreign key checks temporarily to allow truncate
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            // Then delete all permissions
            Permission::truncate();

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            $this->line('✓ All permissions deleted.');
            $this->newLine();
        } catch (\Exception $e) {
            // Make sure to re-enable foreign key checks even if there's an error
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            DB::rollBack();
            $this->error('❌ Error deleting existing permissions: '.$e->getMessage());

            return Command::FAILURE;
        }

        try {
            foreach ($this->permissions as $permissionName) {
                try {
                    $permission = Permission::firstOrCreate(
                        [
                            'name' => $permissionName,
                            'guard_name' => 'web',
                        ]
                    );

                    if ($permission->wasRecentlyCreated) {
                        $this->line("✓ Created: {$permissionName}");
                        $created++;
                    } else {
                        $this->line("⊘ Already exists: {$permissionName}");
                        $existing++;
                    }
                } catch (\Exception $e) {
                    $this->error("✗ Error creating '{$permissionName}': ".$e->getMessage());
                    $errors++;
                }
            }

            DB::commit();

            // Clear the permission cache
            if (app()->bound('cache')) {
                cache()->forget(config('permission.cache.key'));
                $this->line('✓ Permission cache cleared.');
            }

            $this->newLine();
            $this->info('✅ Permissions creation completed!');
            $this->info("   Created: {$created}");

            if ($existing > 0) {
                $this->info("   Already existed: {$existing}");
            }

            if ($errors > 0) {
                $this->warn("   Errors: {$errors}");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error creating permissions: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
