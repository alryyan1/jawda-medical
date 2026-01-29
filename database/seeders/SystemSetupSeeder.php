<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class SystemSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Disable foreign key checks to prevent issues with circular deps or order
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Run basic setup seeders (excluding Auth tables)
        $this->call(\Database\Seeders\system_setup_seeders\WardsTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\RoomsTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\BedsTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\SpecialistsTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\ContainersTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\PackagesTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\MainTestsTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\CbcBindingsTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\ChemistryBindingsTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\ChiefComplainTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\UnitsTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\ChildTestsTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\ChildTestOptionsTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\ClientsTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\DenosTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\DiagnosisTableSeeder::class);
        $this->call(\Database\Seeders\system_setup_seeders\DrugsTableSeeder::class);

        // 2. Create Permissions using the custom command
        $this->command->info('Creating permissions via command...');
        Artisan::call('permissions:create');

        // 3. Create Admin Role
        $this->command->info('Creating Admin Role...');
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // Assign ALL existing permissions to admin role
        $permissions = Permission::all();
        $adminRole->syncPermissions($permissions);
        $this->command->info('Assigned ' . $permissions->count() . ' permissions to admin role.');

        // 4. Create Admin User
        $this->command->info('Creating Admin User...');
        $adminUser = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'admin',
                'password' => Hash::make('admin'), // Password is 'admin'
                'is_active' => 1,
                'nav_items' => json_encode([
                    "/dashboard",
                    "/clinic",
                    "/lab-reception",
                    "/lab-sample-collection",
                    "/lab-workstation",
                    "/attendance/sheet",
                    "/patients",
                    "/admissions",
                    "/online-booking",
                    "/cash-reconciliation"
                ]),
                // Add any other required fields with defaults
            ]
        );

        // Update password if user likely existed with old hash (optional, but requested 'password admin')
        if (!$adminUser->wasRecentlyCreated) {
            $adminUser->password = Hash::make('admin');
            $adminUser->name = 'admin';
            $adminUser->nav_items = json_encode([
                "/dashboard",
                "/clinic",
                "/lab-reception",
                "/lab-sample-collection",
                "/lab-workstation",
                "/attendance/sheet",
                "/patients",
                "/admissions",
                "/online-booking",
                "/cash-reconciliation"
            ]);
            $adminUser->save();
        }

        // 5. Assign Role to User
        $adminUser->assignRole($adminRole);
        $this->command->info("Admin user setup complete. Username: admin, Password: admin");


        // 6. Reset Auto Increment
        \DB::statement('ALTER TABLE patients AUTO_INCREMENT = 1000;');
        \DB::statement('ALTER TABLE doctor_visits AUTO_INCREMENT = 1000;');

        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
