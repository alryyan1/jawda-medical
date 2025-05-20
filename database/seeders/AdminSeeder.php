<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure Super Admin and Admin roles exist (or create them)
        // This assumes RolesAndPermissionsSeeder might have already run or will run.
        // It's good practice to use firstOrCreate here to avoid duplicates if run multiple times.
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'Super Admin', 'guard_name' => 'web']
        );
        $adminRole = Role::firstOrCreate(
            ['name' => 'Admin', 'guard_name' => 'web']
        );

        // Ensure all permissions exist and assign them to Super Admin if they don't have them
        // This part is optional if your RolesAndPermissionsSeeder handles all permission assignments
        // For simplicity, we'll assume RolesAndPermissionsSeeder grants all permissions to Super Admin.
        // If not, you'd iterate through all permissions and assign them:
        // $allPermissions = Permission::all();
        // $superAdminRole->syncPermissions($allPermissions);


        // --- Create Super Admin User ---
        $superAdminUser = User::firstOrCreate(
            ['username' => 'superadmin'], // Unique identifier for lookup
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('12345678'), // CHANGE THIS TO A STRONG, SECURE PASSWORD
                // Add other required fields from your 'users' table with sensible defaults
                'is_nurse' => false,
                'user_money_collector_type' => 'all', // Or whatever is appropriate for a super admin
                // 'email' => 'superadmin@example.com', // If you have an email field
                // 'doctor_id' => null, // Super admin is likely not a doctor profile
            ]
        );

        if ($superAdminUser->wasRecentlyCreated) {
            $this->command->info('Super Admin user created.');
        } else {
            $this->command->info('Super Admin user already exists.');
            // Ensure password is set if user exists but might have been created without it
            if (!Hash::check('12345678', $superAdminUser->password)) {
                 $superAdminUser->password = Hash::make('12345678'); // Update password if necessary
                 $superAdminUser->save();
                 $this->command->info('Super Admin password updated.');
            }
        }
        // Assign the 'Super Admin' role if not already assigned
        if (!$superAdminUser->hasRole('Super Admin')) {
            $superAdminUser->assignRole($superAdminRole);
            $this->command->info("Super Admin role assigned to {$superAdminUser->username}.");
        }


        // --- Optional: Create a regular Admin User ---
        $adminUser = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('AdminP@$$123'), // CHANGE THIS TO A STRONG, SECURE PASSWORD
                'is_nurse' => false,
                'user_money_collector_type' => 'clinic', // Example
                // 'email' => 'admin@example.com',
            ]
        );

        if ($adminUser->wasRecentlyCreated) {
            $this->command->info('Admin user created.');
        } else {
            $this->command->info('Admin user already exists.');
             if (!Hash::check('AdminP@$$123', $adminUser->password)) {
                 $adminUser->password = Hash::make('AdminP@$$123');
                 $adminUser->save();
                 $this->command->info('Admin password updated.');
            }
        }
        if (!$adminUser->hasRole('Admin')) {
            $adminUser->assignRole($adminRole);
            $this->command->info("Admin role assigned to {$adminUser->username}.");
        }

        $this->command->info('Admin users seeding completed.');
    }
}