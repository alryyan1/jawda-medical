<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\ChildTest;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(
            [
                RolesAndPermissionsSeeder::class,
                AdminSeeder::class,
                DummyDataSeeder::class,
                ContainersTableSeeder::class,
                MainTestsTableSeeder::class,
                PackageTableSeeder::class,
                UnitsTableSeeder::class,
                ChildTestsTableSeeder::class,
                // Add other seeders here as needed
                // For example, if you have a seeder for companies, doctors, etc.
                // CompanySeeder::class,
                // DoctorSeeder::class,
            ]
        );
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
