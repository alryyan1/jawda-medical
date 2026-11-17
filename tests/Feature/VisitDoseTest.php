<?php

namespace Tests\Feature;

use App\Jobs\SendDoseGivenWhatsAppNotice;
use App\Models\DoctorVisit;
use App\Models\User;
use App\Models\VisitDose;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitDoseTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_a_dose_for_the_visit(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();

        $response = $this->postJson("/api/doctor-visits/{$visit->id}/doses", [
            'medication_name' => 'باراسيتامول',
            'dosage' => '500mg',
            'status' => 'not_given',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.medication_name', 'باراسيتامول')
            ->assertJsonPath('data.status', 'not_given')
            ->assertJsonPath('data.doctor_visit_id', $visit->id);

        $this->assertSame(1, VisitDose::where('doctor_visit_id', $visit->id)->count());
    }

    public function test_storing_a_given_dose_dispatches_the_whatsapp_notice_job(): void
    {
        Bus::fake();
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();

        $response = $this->postJson("/api/doctor-visits/{$visit->id}/doses", [
            'medication_name' => 'باراسيتامول',
            'dosage' => '500mg',
            'status' => 'given',
        ]);

        $response->assertStatus(200);
        $doseId = $response->json('data.id');

        Bus::assertDispatched(SendDoseGivenWhatsAppNotice::class, fn ($job) => $job->visitDoseId === $doseId);
    }

    public function test_storing_a_not_given_dose_does_not_dispatch_the_whatsapp_notice_job(): void
    {
        Bus::fake();
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();

        $this->postJson("/api/doctor-visits/{$visit->id}/doses", [
            'medication_name' => 'باراسيتامول',
            'dosage' => '500mg',
            'status' => 'postponed',
        ])->assertStatus(200);

        Bus::assertNotDispatched(SendDoseGivenWhatsAppNotice::class);
    }

    public function test_update_dispatches_the_whatsapp_notice_job_only_when_status_transitions_to_given(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $visit = DoctorVisit::factory()->create();

        $dose = VisitDose::create([
            'doctor_visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'administered_by_user_id' => $user->id,
            'medication_name' => 'باراسيتامول',
            'dosage' => '500mg',
            'status' => 'postponed',
            'administered_at' => now(),
        ]);

        $this->putJson("/api/doctor-visits/{$visit->id}/doses/{$dose->id}", [
            'notes' => 'تم التأجيل بناء على طلب المريض',
        ])->assertStatus(200);

        Bus::assertNotDispatched(SendDoseGivenWhatsAppNotice::class);

        $this->putJson("/api/doctor-visits/{$visit->id}/doses/{$dose->id}", [
            'status' => 'given',
        ])->assertStatus(200)->assertJsonPath('data.status', 'given');

        Bus::assertDispatched(SendDoseGivenWhatsAppNotice::class, fn ($job) => $job->visitDoseId === $dose->id);
    }

    public function test_update_rejects_a_dose_from_another_visit(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();
        $otherVisit = DoctorVisit::factory()->create();

        $dose = VisitDose::create([
            'doctor_visit_id' => $otherVisit->id,
            'patient_id' => $otherVisit->patient_id,
            'medication_name' => 'باراسيتامول',
            'dosage' => '500mg',
            'status' => 'given',
            'administered_at' => now(),
        ]);

        $this->putJson("/api/doctor-visits/{$visit->id}/doses/{$dose->id}", [
            'status' => 'not_given',
        ])->assertStatus(404);
    }

    public function test_send_whatsapp_returns_success_when_the_message_is_sent(): void
    {
        Config::set('services.jawda_inpatient.base_url', 'http://jawda-inpatient.test/api');
        Config::set('services.whatsapp_cloud.token', 'test-token');
        Config::set('services.whatsapp_cloud.phone_number_id', '123456');
        Http::fake([
            '*/public/admissions/*/companion-contact' => Http::response(['phone' => '0912345678'], 200),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create(['admission_id' => 9]);

        $dose = VisitDose::create([
            'doctor_visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'medication_name' => 'باراسيتامول',
            'dosage' => '500mg',
            'status' => 'given',
            'administered_at' => now(),
        ]);

        $this->postJson("/api/doctor-visits/{$visit->id}/doses/{$dose->id}/send-whatsapp")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message_id', 'wamid.test');
    }

    public function test_send_whatsapp_sends_to_an_overridden_phone_number_without_an_admission(): void
    {
        Config::set('services.whatsapp_cloud.token', 'test-token');
        Config::set('services.whatsapp_cloud.phone_number_id', '123456');
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create(['admission_id' => null]);

        $dose = VisitDose::create([
            'doctor_visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'medication_name' => 'باراسيتامول',
            'dosage' => '500mg',
            'status' => 'given',
            'administered_at' => now(),
        ]);

        $this->postJson("/api/doctor-visits/{$visit->id}/doses/{$dose->id}/send-whatsapp", [
            'phone' => '0991234567',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }

    public function test_send_whatsapp_returns_an_error_when_there_is_no_admission_or_phone(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create(['admission_id' => null]);

        $dose = VisitDose::create([
            'doctor_visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'medication_name' => 'باراسيتامول',
            'dosage' => '500mg',
            'status' => 'given',
            'administered_at' => now(),
        ]);

        $this->postJson("/api/doctor-visits/{$visit->id}/doses/{$dose->id}/send-whatsapp")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_send_whatsapp_rejects_a_dose_from_another_visit(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();
        $otherVisit = DoctorVisit::factory()->create();

        $dose = VisitDose::create([
            'doctor_visit_id' => $otherVisit->id,
            'patient_id' => $otherVisit->patient_id,
            'medication_name' => 'باراسيتامول',
            'dosage' => '500mg',
            'status' => 'given',
            'administered_at' => now(),
        ]);

        $this->postJson("/api/doctor-visits/{$visit->id}/doses/{$dose->id}/send-whatsapp")
            ->assertStatus(404);
    }

    public function test_companion_contact_returns_the_companion_phone_for_an_admitted_visit(): void
    {
        Config::set('services.jawda_inpatient.base_url', 'http://jawda-inpatient.test/api');
        Http::fake([
            '*/public/admissions/*/companion-contact' => Http::response([
                'name' => 'سارة أحمد',
                'relationship' => 'زوجة',
                'phone' => '0912345678',
            ], 200),
        ]);
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create(['admission_id' => 9]);

        $this->getJson("/api/doctor-visits/{$visit->id}/companion-contact")
            ->assertStatus(200)
            ->assertJson(['name' => 'سارة أحمد', 'phone' => '0912345678']);
    }

    public function test_companion_contact_returns_nulls_when_the_visit_has_no_admission(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create(['admission_id' => null]);

        $this->getJson("/api/doctor-visits/{$visit->id}/companion-contact")
            ->assertStatus(200)
            ->assertJson(['name' => null, 'phone' => null]);
    }

    public function test_destroy_removes_the_dose(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();

        $dose = VisitDose::create([
            'doctor_visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'medication_name' => 'باراسيتامول',
            'dosage' => '500mg',
            'status' => 'not_given',
            'administered_at' => now(),
        ]);

        $this->deleteJson("/api/doctor-visits/{$visit->id}/doses/{$dose->id}")->assertStatus(204);

        $this->assertDatabaseMissing('visit_doses', ['id' => $dose->id]);
    }
}
