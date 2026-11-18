<?php

namespace Tests\Feature;

use App\Models\DoctorVisit;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClinicWorkspaceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_from_admission_scope_returns_only_admission_origin_patients(): void
    {
        $admissionPatient = Patient::factory()->create(['from_admission' => true]);
        DoctorVisit::factory()->create(['patient_id' => $admissionPatient->id]);

        $regularPatient = Patient::factory()->create(['from_admission' => false]);
        DoctorVisit::factory()->create(['patient_id' => $regularPatient->id]);

        $response = $this->getJson('/api/clinic-active-patients?from_admission=true');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('patient.id');
        $this->assertTrue($ids->contains($admissionPatient->id));
        $this->assertFalse($ids->contains($regularPatient->id));
    }

    public function test_from_admission_scope_can_be_narrowed_by_date_range(): void
    {
        $patientInRange = Patient::factory()->create(['from_admission' => true]);
        DoctorVisit::factory()->create([
            'patient_id' => $patientInRange->id,
            'created_at' => '2026-01-10 10:00:00',
        ]);

        $patientOutOfRange = Patient::factory()->create(['from_admission' => true]);
        DoctorVisit::factory()->create([
            'patient_id' => $patientOutOfRange->id,
            'created_at' => '2026-02-01 10:00:00',
        ]);

        $response = $this->getJson('/api/clinic-active-patients?'.http_build_query([
            'from_admission' => 'true',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('patient.id');
        $this->assertTrue($ids->contains($patientInRange->id));
        $this->assertFalse($ids->contains($patientOutOfRange->id));
    }
}
