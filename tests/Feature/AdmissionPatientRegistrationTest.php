<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\AdmissionIntegrationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdmissionPatientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'تسجيل مريض كاش', 'guard_name' => 'web']);
        $this->seed(AdmissionIntegrationSeeder::class);
    }

    public function test_it_creates_a_patient_from_the_admission_system_without_authentication(): void
    {
        Shift::factory()->create(['is_closed' => false]);

        $response = $this->postJson('/api/public/admission-patients', [
            'name' => 'Ahmed Ali',
            'phone' => '0991961111',
            'gender' => 'male',
            'age_year' => 40,
            'address' => 'Khartoum',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Ahmed Ali')
            ->assertJsonPath('data.from_admission', true);

        $this->assertDatabaseHas('patients', [
            'name' => 'Ahmed Ali',
            'from_admission' => true,
        ]);

        $patient = Patient::where('name', 'Ahmed Ali')->first();
        $this->assertNotNull($patient->doctorVisit);
        $this->assertSame(1, $patient->doctor_id);
        $this->assertSame('admission_system', User::find($patient->user_id)->username);
    }

    public function test_it_creates_a_new_doctor_by_name_when_none_matches_in_jawda_medical(): void
    {
        Shift::factory()->create(['is_closed' => false]);

        $response = $this->postJson('/api/public/admission-patients', [
            'name' => 'Ahmed Ali',
            'gender' => 'male',
            'doctor_name' => 'د. سارة عبدالله',
        ]);

        $response->assertCreated();
        $doctor = Doctor::where('name', 'د. سارة عبدالله')->first();
        $this->assertNotNull($doctor);
        $this->assertSame($doctor->id, Patient::where('name', 'Ahmed Ali')->first()->doctor_id);
    }

    public function test_it_reuses_an_existing_doctor_with_a_matching_name(): void
    {
        Shift::factory()->create(['is_closed' => false]);
        $existingDoctor = Doctor::factory()->create(['name' => 'د. سارة عبدالله']);

        $response = $this->postJson('/api/public/admission-patients', [
            'name' => 'Ahmed Ali',
            'gender' => 'male',
            'doctor_name' => 'د. سارة عبدالله',
        ]);

        $response->assertCreated();
        $this->assertSame(1, Doctor::where('name', 'د. سارة عبدالله')->count());
        $this->assertSame($existingDoctor->id, Patient::where('name', 'Ahmed Ali')->first()->doctor_id);
    }

    public function test_it_rejects_registration_without_an_open_shift(): void
    {
        Shift::factory()->create(['is_closed' => true]);

        $response = $this->postJson('/api/public/admission-patients', [
            'name' => 'Ahmed Ali',
            'gender' => 'male',
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseCount('patients', 0);
    }

    public function test_it_validates_required_fields(): void
    {
        Shift::factory()->create(['is_closed' => false]);

        $response = $this->postJson('/api/public/admission-patients', []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name', 'gender']);
    }
}
