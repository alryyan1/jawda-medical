<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User; // If you want to assign a role to an existing user

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define Permissions for Users
        Permission::firstOrCreate(['name' => 'list users', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view users', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create users', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'edit users', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'delete users', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'assign roles', 'guard_name' => 'web']); // To assign roles to users

        // Define Permissions for Roles (if you want to manage roles via UI too)
        Permission::firstOrCreate(['name' => 'list roles', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view roles', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create roles', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'edit roles', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'delete roles', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'assign permissions to role', 'guard_name' => 'web']);


        // Define Permissions for Doctors (example)
        Permission::firstOrCreate(['name' => 'list doctors', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create doctors', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'edit doctors', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'delete doctors', 'guard_name' => 'web']);

        // Define Permissions for Patients (example)
        Permission::firstOrCreate(['name' => 'list patients', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create patients', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'edit patients', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'delete patients', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view patients', 'guard_name' => 'web']);

 // --- NEW: Define Permissions for Companies & Contracts ---
        Permission::firstOrCreate(['name' => 'view companies', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'list companies', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create companies', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'edit companies', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'delete companies', 'guard_name' => 'web']);
        
        Permission::firstOrCreate(['name' => 'view company_contracts', 'guard_name' => 'web']);
        // Option 1: General manage permission
        Permission::firstOrCreate(['name' => 'manage company_contracts', 'guard_name' => 'web']); 
        // Option 2: More granular permissions (use these OR the 'manage' one, not necessarily both unless for specific overrides)
        Permission::firstOrCreate(['name' => 'create company_contracts', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'edit company_contracts', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'delete company_contracts', 'guard_name' => 'web']);
        // ... add permissions for other modules (services, appointments, clinic, etc.)
        // view doctor_shift_reports
        Permission::firstOrCreate(['name' => 'view doctor_shift_reports', 'guard_name' => 'web']);
        // --- NEW PERMISSIONS TO ADD ---

        // General Clinic Shift Management
        $clinicShiftPerms = [
            'view current_open_shift', 'open clinic_shifts', 'close clinic_shifts',
            'manage clinic_shift_financials', 'list clinic_shifts', 'view clinic_shifts'
        ];
        foreach ($clinicShiftPerms as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Doctor-Specific Shift Management
        $doctorShiftPerms = [
            'view active_doctor_shifts', 'manage doctor_shifts', 
            'start doctor_shifts', 'end doctor_shifts', // Granular for manage
            'list all_doctor_shifts', 'edit doctor_shift_details',
            'view doctor_shift_financial_summary' // Financial summary dialog
        ];
        foreach ($doctorShiftPerms as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
      // --- NEW PERMISSIONS TO ADD/UPDATE ---
        $this->command->info('Creating new/updated permissions...');
        $newPerms = [
            // Lab Test Management Refined
            'manage lab_test_child_options', // For ChildTest predefined result options

            // Lab Test Price List Management
            'view lab_price_list',
            'update lab_test_prices',
            'batch_delete lab_tests', // Differentiated from single delete if needed
            'print lab_price_list',

            // Reporting Refined
            'print doctor_shift_reports',
            'print service_statistics_report',
            // 'view reports_section', 'view doctor_shift_reports', 'view service_statistics_report' should exist

            // Settings (ensure these exist if not already)
            'view settings', 'update settings',
        ];
        foreach ($newPerms as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Doctor Schedule Management
        $schedulePerms = [
            'view doctor_schedules',
            'manage own_doctor_schedule',
            'manage all_doctor_schedules' // Super admin/admin level permission
        ];
        foreach ($schedulePerms as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Reports Access
        $reportPerms = [
            'view reports_section',
            'view doctor_shift_reports'
        ];
        foreach ($reportPerms as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        
        // --- NEW SETTINGS PERMISSIONS ---
        $settingsPerms = [
            'view settings', 'update settings'
        ];
        foreach ($settingsPerms as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Doctor Visit Management
        $visitPerms = [
            'list doctor_visits', 'view doctor_visits', 'create doctor_visits',
            'edit doctor_visits', 'update doctor_visit_status', 'delete doctor_visits'
        ];
        foreach ($visitPerms as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Visit-Specific Clinical Actions
        $visitActionPerms = [
            'request visit_services', 'remove visit_services', 
            'edit visit_requested_service_details', // For editing service details
            'record visit_service_payment', // For service payments
            'manage visit_vitals',
            'manage visit_clinical_notes', 'manage visit_lab_requests', 'view visit_lab_results',
            'manage visit_prescriptions', 'manage visit_documents'
        ];
        foreach ($visitActionPerms as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Define Roles
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $doctorRole = Role::firstOrCreate(['name' => 'Doctor', 'guard_name' => 'web']);
        $receptionistRole = Role::firstOrCreate(['name' => 'Receptionist', 'guard_name' => 'web']);
        $nurseRole = Role::firstOrCreate(['name' => 'Nurse', 'guard_name' => 'web']);
        // ... other roles

        // Super Admin gets all permissions
        $superAdminRole->givePermissionTo(Permission::where('guard_name', 'web')->get());

        // Admin Role
        $adminRole->givePermissionTo([
            // ... existing admin perms ...
            'open clinic_shifts', 'close clinic_shifts', 'manage clinic_shift_financials',
            'list clinic_shifts', 'view clinic_shifts',
            'manage doctor_shifts',
            'list all_doctor_shifts', 'edit doctor_shift_details',
            'view doctor_shift_financial_summary',
            'list doctor_visits', 'view doctor_visits', 'delete doctor_visits',
            'view settings', 'update settings',
            'edit visit_requested_service_details',
            'record visit_service_payment',
            // New permissions
            'view doctor_schedules',
            'manage all_doctor_schedules',
            'view reports_section',
            'view doctor_shift_reports'
        ]);

        // Doctor Role
        $doctorRole->givePermissionTo([
            'view active_doctor_shifts',
            'view doctor_shift_financial_summary',
            'view doctor_visits',
            'edit doctor_visits',
            'update doctor_visit_status',
            'request visit_services', 'remove visit_services',
            'edit visit_requested_service_details',
            'manage visit_vitals', 'manage visit_clinical_notes',
            'manage visit_lab_requests', 'view visit_lab_results',
            'manage visit_prescriptions', 'manage visit_documents',
            // New permissions
            'view doctor_schedules',
            'manage own_doctor_schedule',
            'view reports_section',
            'view doctor_shift_reports'
        ]);

        // Receptionist Role
        $receptionistRole->givePermissionTo([
            'view current_open_shift',
            'open clinic_shifts', 'close clinic_shifts',
            'view active_doctor_shifts',
            'create doctor_visits',
            'edit doctor_visits',
            'update doctor_visit_status',
            'request visit_services',
            'edit visit_requested_service_details',
            'record visit_service_payment',
            // New permissions
            'view doctor_schedules',
            'view reports_section'
        ]);

        // Nurse Role
        $nurseRole->givePermissionTo([
            'view active_doctor_shifts',
            'view doctor_visits',
            'update doctor_visit_status',
            'manage visit_vitals',
            'manage visit_clinical_notes',
            // New permissions
            'view doctor_schedules',
            'view reports_section'
        ]);
        
        // Accountant Role (Example)
        // $accountantRole = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'sanctum']);
        // $accountantRole->givePermissionTo([
        //     'manage clinic_shift_financials',
        //     'list clinic_shifts', 'view clinic_shifts',
        //     'list doctor_visits', // For billing purposes
        //     // ... other financial permissions ...
        // ]);



      
    }
}