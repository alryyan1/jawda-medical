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

        // Define Roles
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $doctorRole = Role::firstOrCreate(['name' => 'Doctor', 'guard_name' => 'web']);
        $receptionistRole = Role::firstOrCreate(['name' => 'Receptionist', 'guard_name' => 'web']);
        $nurseRole = Role::firstOrCreate(['name' => 'Nurse', 'guard_name' => 'web']);
        // ... other roles

        // Assign Permissions to Roles
        // Super Admin gets all permissions
        $superAdminRole->givePermissionTo(Permission::all());

        // Admin gets most user management and potentially doctor/patient management
        $adminRole->givePermissionTo([
            'list users', 'view users', 'create users', 'edit users', 'assign roles',
            'list roles', 'view roles', // etc.
            'list doctors', 'create doctors', 'edit doctors',
            'list patients', 'create patients', 'edit patients',
        ]);

        // Doctor role
        $doctorRole->givePermissionTo([
            'list patients', 'view patients', // Doctors might view all, or only their assigned
            'edit patients', // For clinical notes
            // 'create lab_requests', 'view lab_results', 'create prescriptions' etc.
        ]);

        // Receptionist role
        $receptionistRole->givePermissionTo([
            'list patients', 'create patients', 'edit patients', // Basic demographic edits
            // 'create appointments', 'manage appointments', 'access clinic_workspace' etc.
        ]);
        
        // Nurse role
        $nurseRole->givePermissionTo([
            'list patients', 'edit patients', // e.g., for vitals
            // 'manage clinic_workspace', 'prepare_patient_for_doctor' etc.
        ]);


      
    }
}