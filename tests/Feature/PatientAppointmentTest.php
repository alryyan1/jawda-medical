<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PatientAppointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientAppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_an_appointment_and_sends_a_whatsapp_notification(): void
    {
        config(['services.whatsapp_cloud.token' => 'test-token', 'services.whatsapp_cloud.phone_number_id' => 'test-phone-id']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.abc123']]], 200)]);

        Sanctum::actingAs(User::factory()->create());
        $patient = Patient::factory()->create(['phone' => '0991961111']);
        $doctor = Doctor::factory()->create();

        $response = $this->postJson("/api/patients/{$patient->id}/appointments", [
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'notes' => 'متابعة نتائج التحاليل',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.doctor.id', $doctor->id)
            ->assertJsonPath('data.status', 'scheduled');

        $this->assertNotNull($response->json('data.whatsapp_sent_at'));
        $this->assertSame(1, PatientAppointment::where('patient_id', $patient->id)->count());
    }

    public function test_store_records_the_error_when_the_patient_has_no_valid_phone(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $patient = Patient::factory()->create(['phone' => '']);

        $response = $this->postJson("/api/patients/{$patient->id}/appointments", [
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(200);
        $this->assertNull($response->json('data.whatsapp_sent_at'));
        $this->assertNotNull($response->json('data.whatsapp_send_error'));
    }

    public function test_send_whatsapp_can_be_opted_out_of(): void
    {
        Http::fake();

        Sanctum::actingAs(User::factory()->create());
        $patient = Patient::factory()->create(['phone' => '0991961111']);

        $response = $this->postJson("/api/patients/{$patient->id}/appointments", [
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'send_whatsapp' => false,
        ]);

        $response->assertStatus(200);
        $this->assertNull($response->json('data.whatsapp_sent_at'));
        Http::assertNothingSent();
    }

    public function test_cancel_marks_the_appointment_cancelled(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $patient = Patient::factory()->create();

        $appointment = PatientAppointment::create([
            'patient_id' => $patient->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $response = $this->putJson("/api/patient-appointments/{$appointment->id}/cancel");

        $response->assertStatus(200)->assertJsonPath('data.status', 'cancelled');
    }
}
