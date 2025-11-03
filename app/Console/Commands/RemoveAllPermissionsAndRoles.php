<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveAllPermissionsAndRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:remove-all 
                            {--force : Force the operation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove all permissions and roles from the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('⚠️  WARNING: This will permanently delete ALL permissions and roles. This cannot be undone! Do you want to continue?')) {
                $this->info('Operation cancelled.');
                return Command::FAILURE;
            }
        }

        $this->info('Removing all permissions and roles...');

        try {
            // Delete from pivot tables first to avoid foreign key constraint issues
            $this->line('Deleting model permissions assignments...');
            $modelPermissionsCount = DB::table('model_has_permissions')->count();
            DB::table('model_has_permissions')->delete();
            $this->info("✓ Deleted {$modelPermissionsCount} model permission assignments.");

            $this->line('Deleting model roles assignments...');
            $modelRolesCount = DB::table('model_has_roles')->count();
            DB::table('model_has_roles')->delete();
            $this->info("✓ Deleted {$modelRolesCount} model role assignments.");

            $this->line('Deleting role permissions assignments...');
            $rolePermissionsCount = DB::table('role_has_permissions')->count();
            DB::table('role_has_permissions')->delete();
            $this->info("✓ Deleted {$rolePermissionsCount} role permission assignments.");

            // Delete all permissions
            $this->line('Deleting permissions...');
            $permissionsCount = DB::table('permissions')->count();
            DB::table('permissions')->delete();
            $this->info("✓ Deleted {$permissionsCount} permissions.");

            // Delete all roles
            $this->line('Deleting roles...');
            $rolesCount = DB::table('roles')->count();
            DB::table('roles')->delete();
            $this->info("✓ Deleted {$rolesCount} roles.");

            // Clear the permission cache if it exists
            if (app()->bound('cache')) {
                $this->line('Clearing permission cache...');
                cache()->forget(config('permission.cache.key'));
                $this->info('✓ Permission cache cleared.');
            }

            $this->newLine();
            $this->info('✅ Successfully removed all permissions and roles!');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error removing permissions and roles: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
