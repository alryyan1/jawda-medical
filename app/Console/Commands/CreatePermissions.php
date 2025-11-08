<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

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
            foreach ($this->permissions as $permissionName) {
                try {
                    $permission = Permission::firstOrCreate(
                        [
                            'name' => $permissionName,
                            'guard_name' => 'web'
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
                    $this->error("✗ Error creating '{$permissionName}': " . $e->getMessage());
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
            $this->info("✅ Permissions creation completed!");
            $this->info("   Created: {$created}");
            $this->info("   Already existed: {$existing}");
            
            if ($errors > 0) {
                $this->warn("   Errors: {$errors}");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error creating permissions: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
