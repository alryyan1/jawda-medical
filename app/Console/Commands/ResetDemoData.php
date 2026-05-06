<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class ResetDemoData extends Command
{
    protected $signature = 'system:reset-demo {--force : Skip confirmation prompt}';
    protected $description = 'Remove all users, patients, and doctors, then create a fresh admin user';

    private array $adminNavItems = [
        '/dashboard',
        '/clinic',
        '/lab-reception',
        '/lab-sample-collection',
        '/lab-workstation',
        '/attendance/sheet',
        '/patients',
        '/admissions',
        '/online-booking',
        '/cash-reconciliation',
        '/reports',
        '/settings',
        '/users',
        '/doctors',
        '/finance',
        '/employees',
    ];

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('⚠  WARNING: This will permanently delete ALL users, patients, and doctors!');
            $this->warn('   Configuration data (tests, specialists, services, etc.) will be preserved.');
            $this->newLine();
            if (! $this->confirm('Are you sure you want to continue?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $this->info('Starting demo data reset...');
        $this->newLine();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            $this->cleanPatientData();
            $this->cleanDoctorData();
            $this->cleanFinancialData();
            $this->cleanUserData();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $this->setupPermissionsAndAdmin();

        $this->newLine();
        $this->info('✅ Reset complete!');
        $this->table(
            ['Field', 'Value'],
            [
                ['Username', 'admin'],
                ['Password', 'admin'],
                ['Role', 'admin (all permissions)'],
                ['Nav items', count($this->adminNavItems) . ' routes configured'],
            ]
        );

        return 0;
    }

    private function cleanPatientData(): void
    {
        $this->info('🧹 Cleaning patient data...');

        $this->truncate([
            // Analyzer results
            'acon_cbc_results',
            'mindray_cbc',
            'mindray2',
            'sysmex',
            'sysmex550',
            // Lab request children
            'requested_results',
            'requested_organisms',
            'discount_lab_requests',
            'returned_lab_requests',
            'countings',
            'lab_finished_notifications',
            'new_patient_notifications',
            'hl7_messages',
            // Lab requests
            'labrequests',
            // Service children
            'requested_service_deposit_deletions',
            'requested_service_deposits',
            'requested_service_cost',
            'audited_requested_services',
            'returned_requested_services',
            'costs',
            'requested_services',
            // Visit children
            'drugs_prescribed',
            'sickleaves',
            'nurse_notes',
            'patient_medical_histories',
            'audited_patient_records',
            'appointments',
            // Surgery children
            'requested_surgery_transactions',
            'requested_surgery_finances',
            'requested_surgeries',
            // Admission children
            'admission_vital_signs',
            'admission_requested_lab_tests',
            'admission_requested_service_costs',
            'admission_requested_service_deposits',
            'admission_requested_services',
            'admission_doses',
            'admission_nursing_assignments',
            'admission_treatments',
            'admission_transactions',
            'admission_deposits',
            // Main tables
            'admissions',
            'doctorvisits',
            // Misc patient data
            'images',
            'files',
            'bankak_images',
            'whats_app_messages',
            // client_payments preserved (insurance/company payment records)
            'patients',
        ]);
    }

    private function cleanDoctorData(): void
    {
        $this->info('🧹 Cleaning doctor data...');

        $this->truncate([
            // doctor_service_costs and doctor_services are preserved (service configurations)
            'doctor_schedules',
            'doctor_shifts',
            'doctors',
        ]);
    }

    private function cleanFinancialData(): void
    {
        $this->info('🧹 Cleaning financial/shift data...');

        $this->truncate([
            'deducted_items',
            'deducts',
            'debits',
            'deposit_items',
            'deposits',
            'petty_cash_permissions',
            'debit_entries',
            'credit_entries',
            'finance_entries',
            'cash_tally',
            'balance_sheet_statements',
            'income_statement_reports',
            'income_statements',
            'shifts',
        ]);
    }

    private function cleanUserData(): void
    {
        $this->info('🧹 Cleaning user data...');

        $this->truncate([
            'personal_access_tokens',
            'attendances',
            'user_default_shifts',
            'denos_users',
            'user_routes',
            'user_sub_routes',
            'user_doc_selections',
            'user_settings',
            'model_has_permissions',
            'model_has_roles',
            'users',
        ]);
    }

    private function setupPermissionsAndAdmin(): void
    {
        $this->info('🔐 Setting up permissions...');
        $this->call('permissions:create');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());
        $this->line('  ✓ Admin role synced with ' . Permission::count() . ' permissions.');

        $this->info('👤 Creating admin user...');
        $adminUser = User::create([
            'name'      => 'admin',
            'username'  => 'admin',
            'password'  => Hash::make('admin'),
            'is_active' => 1,
            'nav_items' => json_encode($this->adminNavItems),
        ]);
        $adminUser->assignRole($adminRole);
        $this->line('  ✓ Admin user created and assigned admin role.');

        $this->info('📅 Creating default shift...');
        $this->call('db:seed', [
            '--class' => 'Database\\Seeders\\system_setup_seeders\\ShiftsTableSeeder',
            '--force' => true,
        ]);

        DB::statement('ALTER TABLE patients AUTO_INCREMENT = 1000;');
        DB::statement('ALTER TABLE doctorvisits AUTO_INCREMENT = 1000;');
        $this->line('  ✓ Auto-increment counters reset.');
    }

    private function truncate(array $tables): void
    {
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->line("  ✓ {$table}");
            } else {
                $this->warn("  ⚠ Table not found: {$table}");
            }
        }
    }
}
