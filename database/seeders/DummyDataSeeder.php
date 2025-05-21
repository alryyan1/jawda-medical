<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Doctor;
use App\Models\Service;
use App\Models\Specialist;     // Make sure to import
use App\Models\ServiceGroup;    // Make sure to import
use App\Models\FinanceAccount; // Make sure to import

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        // Seed some specialists first if DoctorFactory relies on existing ones
        // Or let DoctorFactory create them on the fly if using Specialist::factory()
        if (Specialist::count() === 0) { // Only seed if table is empty
            Specialist::factory()->count(10)->create();
            $this->command->info('Specialists seeded.');
        }

        // Seed some finance accounts if DoctorFactory relies on existing ones
        if (FinanceAccount::count() === 0) {
            FinanceAccount::factory()->count(20)->create();
            $this->command->info('Finance Accounts seeded.');
        }

        // Seed Doctors
        if (Doctor::count() === 0) {
            Doctor::factory()->count(15)->create(); // Creates 15 doctors
            $this->command->info('Doctors seeded.');
        }


        // Seed some service groups first
        if (ServiceGroup::count() === 0) {
            ServiceGroup::factory()->count(8)->create();
            $this->command->info('Service Groups seeded.');
        }

        // Seed Services
        if (Service::count() === 0) {
            Service::factory()->count(30)->create(); // Creates 30 services
            $this->command->info('Services seeded.');
        }
    }
}