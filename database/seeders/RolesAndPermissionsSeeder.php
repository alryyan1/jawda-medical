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
        Permission::firstOrCreate(['name' => 'list users', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'view users', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'create users', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'edit users', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'delete users', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'assign roles', 'guard_name' => 'sanctum']); // To assign roles to users

        // Define Permissions for Roles (if you want to manage roles via UI too)
        Permission::firstOrCreate(['name' => 'list roles', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'view roles', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'create roles', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'edit roles', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'delete roles', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'assign permissions to role', 'guard_name' => 'sanctum']);


        // Define Permissions for Doctors (example)
        Permission::firstOrCreate(['name' => 'list doctors', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'create doctors', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'edit doctors', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'delete doctors', 'guard_name' => 'sanctum']);

        // Define Permissions for Patients (example)
        Permission::firstOrCreate(['name' => 'list patients', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'create patients', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'edit patients', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'delete patients', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'view patients', 'guard_name' => 'sanctum']);


        // ... add permissions for other modules (services, appointments, clinic, etc.)

        // Define Roles
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'sanctum']);
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'sanctum']);
        $doctorRole = Role::firstOrCreate(['name' => 'Doctor', 'guard_name' => 'sanctum']);
        $receptionistRole = Role::firstOrCreate(['name' => 'Receptionist', 'guard_name' => 'sanctum']);
        $nurseRole = Role::firstOrCreate(['name' => 'Nurse', 'guard_name' => 'sanctum']);
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


        // --- Optional: Assign a role to an existing user (e.g., the first user) ---
        $user = User::find(1); // Find a user
        if ($user) {
            if (!$user->hasRole('Super Admin')) {
                $user->assignRole('Super Admin');
                $this->command->info("User {$user->name} assigned Super Admin role.");
            }
        } else {
            // Or create a Super Admin user if one doesn't exist
            $superAdminUser = User::firstOrCreate(
                ['username' => 'superadmin'], // Or email
                [
                    'name' => 'Super Administrator',
                    'password' => bcrypt('password'), // Change this!
                    // Add other required fields from your users table
                ]
            );
            $superAdminUser->assignRole('Super Admin');
            $this->command->info("Super Admin user created and role assigned.");
        }
    }
}