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
            'list all_doctor_shifts', 'edit doctor_shift_details'
        ];
        foreach ($doctorShiftPerms as $permission) {
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
            'request visit_services', 'remove visit_services', 'manage visit_vitals',
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

        // Assign Permissions to Roles
        // Super Admin gets all permissions
      

        // Super Admin gets all (if not already by Permission::all())
        $superAdminRole->givePermissionTo(Permission::where('guard_name', 'web')->get());

        // Admin Role
        $adminRole->givePermissionTo([
            // ... existing admin perms ...
            'open clinic_shifts', 'close clinic_shifts', 'manage clinic_shift_financials',
            'list clinic_shifts', 'view clinic_shifts',
            'manage doctor_shifts', // or granular 'start doctor_shifts', 'end doctor_shifts'
            'list all_doctor_shifts', 'edit doctor_shift_details',
            'list doctor_visits', 'view doctor_visits', 'delete doctor_visits', // Admin might not create/edit visits directly
                        'view settings',
            'update settings', // Grant update if Admin should also modify settings

        ]);

        // Doctor Role
        $doctorRole->givePermissionTo([
            // ... existing doctor perms ...
            'view active_doctor_shifts', // So they can see who else is on shift
            // 'start doctor_shifts', 'end doctor_shifts', // If doctors manage their own clock-in/out via the dialog
            'view doctor_visits', // Typically their own or all based on clinic policy
            'edit doctor_visits', // For their clinical notes, diagnosis within a visit
            'update doctor_visit_status', // e.g., from 'with_doctor' to 'lab_pending'
            'request visit_services', 'remove visit_services',
            'manage visit_vitals', 'manage visit_clinical_notes',
            'manage visit_lab_requests', 'view visit_lab_results',
            'manage visit_prescriptions', 'manage visit_documents'
        ]);

        // Receptionist Role
        $receptionistRole->givePermissionTo([
            // ... existing receptionist perms ...
            'view current_open_shift',
            'open clinic_shifts', 'close clinic_shifts', // If receptionists manage general shifts
            'view active_doctor_shifts',
            // 'manage doctor_shifts', // If reception can clock doctors in/out
            'create doctor_visits', // For registering patient and starting a visit
            'edit doctor_visits', // For non-clinical visit details (e.g., updating visit type, notes if allowed)
            'update doctor_visit_status', // e.g., from 'waiting' to 'with_doctor', or 'completed'
            'request visit_services', // If reception adds basic services
        ]);

        // Nurse Role
        $nurseRole->givePermissionTo([
            // ... existing nurse perms ...
            'view active_doctor_shifts',
            'view doctor_visits', // View visits they are involved in
            'update doctor_visit_status', // e.g., if they move patient to next step
            'manage visit_vitals',
            'manage visit_clinical_notes', // Specific nursing notes
            // 'request visit_services' // If nurses can request certain services
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