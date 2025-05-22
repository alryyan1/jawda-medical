<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DoctorShift;
use App\Models\DoctorVisit;
use App\Models\RequestedService;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Shift; // General clinic shift
use App\Models\User;
use App\Models\Service;
use Carbon\Carbon;

class ClinicActivitySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding clinic activity (Doctor Shifts, Visits, Requested Services)...');

        // Ensure some base data exists (users, doctors, services, general shifts)
        if (User::count() < 5) User::factory()->count(5)->create();
        if (Doctor::count() < 5) Doctor::factory()->count(5)->create();
        if (Service::where('activate', true)->count() < 10) Service::factory()->count(10)->create(['activate' => true]);
        if (Shift::open()->today()->count() === 0) { // Ensure at least one open general shift for today
            Shift::factory()->openTodayState()->create();
        }
        
        $numberOfActiveDoctorShifts = $this->command->ask('How many active doctor shifts for today would you like to create?', 3);
        $patientsPerShiftMin = $this->command->ask('Minimum patients per active doctor shift?', 2);
        $patientsPerShiftMax = $this->command->ask('Maximum patients per active doctor shift?', 5);
        $servicesPerPatientMin = $this->command->ask('Minimum services per patient visit?', 1);
        $servicesPerPatientMax = $this->command->ask('Maximum services per patient visit?', 3);


        // Create some active doctor shifts for today for different doctors
        Doctor::inRandomOrder()->take($numberOfActiveDoctorShifts)->get()->each(function ($doctor) use ($patientsPerShiftMin, $patientsPerShiftMax, $servicesPerPatientMin, $servicesPerPatientMax) {
            // Check if this doctor already has an active shift today to avoid duplicates
            $hasActiveShift = DoctorShift::where('doctor_id', $doctor->id)->activeToday()->exists();
            
            if (!$hasActiveShift) {
                $doctorShift = DoctorShift::factory()->activeToday()->create(['doctor_id' => $doctor->id]);
                $this->command->line("Created active shift for Dr. {$doctor->name} (DoctorShift ID: {$doctorShift->id})");

                // Create some patient visits for this active doctor shift
                $numberOfVisits = rand((int)$patientsPerShiftMin, (int)$patientsPerShiftMax);
                DoctorVisit::factory()
                    ->count($numberOfVisits)
                    ->forDoctorShift($doctorShift) // Use the state to link correctly
                    ->create()
                    ->each(function ($visit) use ($servicesPerPatientMin, $servicesPerPatientMax, $doctorShift) {
                        $this->command->line("  - Created Visit ID: {$visit->id} for Patient ID: {$visit->patient_id}");
                        // Add random requested services to this visit
                        $numberOfServices = rand((int)$servicesPerPatientMin, (int)$servicesPerPatientMax);
                        RequestedService::factory()
                            ->count(count: $numberOfServices)
                            ->create([
                                'doctorvisits_id' => $visit->id, // Ensure FK is correct
                                'doctor_id' => $doctorShift->doctor_id, // Doctor of the shift/visit
                            ]);
                        $this->command->line("    - Added {$numberOfServices} services to Visit ID: {$visit->id}");
                    });
            } else {
                 $this->command->warn("Dr. {$doctor->name} already has an active shift today. Skipping.");
            }
        });

        $this->command->info('Clinic activity seeding completed.');
    }
}