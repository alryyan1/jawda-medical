<?php

namespace Tests\Feature;

use App\Models\DoctorVisit;
use App\Models\User;
use App\Models\VisitIntakeOutput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitIntakeOutputTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_an_entry_with_computed_totals(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();

        $response = $this->postJson("/api/doctor-visits/{$visit->id}/intake-outputs", [
            'feeding' => 100,
            'iv_fluid' => 250,
            'maintenance' => 50,
            'urine_output' => 300,
            'insensible_loss' => 20,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.doctor_visit_id', $visit->id)
            ->assertJsonPath('data.total_intake', 400.0)
            ->assertJsonPath('data.total_output', 320.0)
            ->assertJsonPath('data.balance', 80.0);

        $this->assertSame(1, VisitIntakeOutput::where('doctor_visit_id', $visit->id)->count());
    }

    public function test_store_defaults_missing_components_to_zero(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();

        $response = $this->postJson("/api/doctor-visits/{$visit->id}/intake-outputs", [
            'urine_output' => 150,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_intake', 0.0)
            ->assertJsonPath('data.total_output', 150.0)
            ->assertJsonPath('data.balance', -150.0);
    }

    public function test_index_returns_entries_ordered_by_recorded_at(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();

        $later = VisitIntakeOutput::create([
            'doctor_visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'recorded_at' => now()->addHour(),
            'urine_output' => 100,
        ]);
        $earlier = VisitIntakeOutput::create([
            'doctor_visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'recorded_at' => now(),
            'urine_output' => 50,
        ]);

        $response = $this->getJson("/api/doctor-visits/{$visit->id}/intake-outputs")
            ->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$earlier->id, $later->id], $ids);
    }

    public function test_update_recalculates_totals(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $visit = DoctorVisit::factory()->create();

        $entry = VisitIntakeOutput::create([
            'doctor_visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'recorded_by_user_id' => $user->id,
            'recorded_at' => now(),
            'feeding' => 100,
            'urine_output' => 50,
        ]);

        $this->putJson("/api/doctor-visits/{$visit->id}/intake-outputs/{$entry->id}", [
            'feeding' => 200,
        ])->assertStatus(200)
            ->assertJsonPath('data.feeding', 200.0)
            ->assertJsonPath('data.total_intake', 200.0)
            ->assertJsonPath('data.balance', 150.0);
    }

    public function test_update_rejects_an_entry_from_another_visit(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();
        $otherVisit = DoctorVisit::factory()->create();

        $entry = VisitIntakeOutput::create([
            'doctor_visit_id' => $otherVisit->id,
            'patient_id' => $otherVisit->patient_id,
            'recorded_at' => now(),
            'urine_output' => 50,
        ]);

        $this->putJson("/api/doctor-visits/{$visit->id}/intake-outputs/{$entry->id}", [
            'urine_output' => 100,
        ])->assertStatus(404);
    }

    public function test_destroy_removes_the_entry(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();

        $entry = VisitIntakeOutput::create([
            'doctor_visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'recorded_at' => now(),
            'urine_output' => 50,
        ]);

        $this->deleteJson("/api/doctor-visits/{$visit->id}/intake-outputs/{$entry->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('visit_intake_outputs', ['id' => $entry->id]);
    }

    public function test_destroy_rejects_an_entry_from_another_visit(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();
        $otherVisit = DoctorVisit::factory()->create();

        $entry = VisitIntakeOutput::create([
            'doctor_visit_id' => $otherVisit->id,
            'patient_id' => $otherVisit->patient_id,
            'recorded_at' => now(),
            'urine_output' => 50,
        ]);

        $this->deleteJson("/api/doctor-visits/{$visit->id}/intake-outputs/{$entry->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('visit_intake_outputs', ['id' => $entry->id]);
    }
}
