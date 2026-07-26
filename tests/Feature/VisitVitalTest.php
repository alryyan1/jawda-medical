<?php

namespace Tests\Feature;

use App\Models\DoctorVisit;
use App\Models\User;
use App\Models\VisitVital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitVitalTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_a_reading_for_the_visit(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();

        $response = $this->postJson("/api/doctor-visits/{$visit->id}/vitals", [
            'heart_rate' => 80,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.heart_rate', 80)
            ->assertJsonPath('data.doctor_visit_id', $visit->id);

        $this->assertSame(1, VisitVital::where('doctor_visit_id', $visit->id)->count());
    }

    public function test_update_amends_the_same_reading(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $visit = DoctorVisit::factory()->create();

        $vital = VisitVital::create([
            'doctor_visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'recorded_by_user_id' => $user->id,
            'heart_rate' => 80,
            'recorded_at' => now(),
        ]);

        $response = $this->putJson("/api/doctor-visits/{$visit->id}/vitals/{$vital->id}", [
            'heart_rate' => 80,
            'spo2' => 97,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.heart_rate', 80)
            ->assertJsonPath('data.spo2', 97);

        $this->assertSame(1, VisitVital::where('doctor_visit_id', $visit->id)->count());
    }

    public function test_update_rejects_a_reading_from_another_visit(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();
        $otherVisit = DoctorVisit::factory()->create();

        $vital = VisitVital::create([
            'doctor_visit_id' => $otherVisit->id,
            'patient_id' => $otherVisit->patient_id,
            'recorded_at' => now(),
        ]);

        $this->putJson("/api/doctor-visits/{$visit->id}/vitals/{$vital->id}", [
            'heart_rate' => 80,
        ])->assertStatus(404);
    }
}
