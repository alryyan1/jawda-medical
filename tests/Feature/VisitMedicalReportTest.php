<?php

namespace Tests\Feature;

use App\Models\DoctorVisit;
use App\Models\User;
use App\Models\VisitMedicalReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitMedicalReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_is_idempotent(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();

        $first = $this->postJson("/api/doctor-visits/{$visit->id}/medical-report")
            ->assertStatus(200)
            ->json('data.id');

        $second = $this->postJson("/api/doctor-visits/{$visit->id}/medical-report")
            ->assertStatus(200)
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, VisitMedicalReport::where('doctor_visit_id', $visit->id)->count());
    }

    public function test_completed_at_is_set_only_on_false_to_true_transition(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $visit = DoctorVisit::factory()->create();

        $report = VisitMedicalReport::create([
            'doctor_visit_id' => $visit->id,
            'user_id' => $user->id,
            'content' => '<p>Test</p>',
            'complete' => false,
        ]);

        $response = $this->putJson("/api/visit-medical-reports/{$report->id}", [
            'complete' => true,
        ]);

        $response->assertStatus(200);
        $completedAt = $response->json('data.completed_at');
        $this->assertNotNull($completedAt);

        // A second update with complete=true again must not change completed_at.
        $response2 = $this->putJson("/api/visit-medical-reports/{$report->id}", [
            'complete' => true,
        ]);

        $this->assertSame($completedAt, $response2->json('data.completed_at'));
    }

    public function test_generate_pdf_returns_a_pdf_response(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $visit = DoctorVisit::factory()->create();

        $report = VisitMedicalReport::create([
            'doctor_visit_id' => $visit->id,
            'user_id' => $user->id,
            'content' => '<p>Assessment and plan</p>',
            'complete' => false,
        ]);

        $response = $this->get("/api/visit-medical-reports/{$report->id}/pdf");

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}
