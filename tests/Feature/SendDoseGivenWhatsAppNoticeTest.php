<?php

namespace Tests\Feature;

use App\Jobs\SendDoseGivenWhatsAppNotice;
use App\Models\DoctorVisit;
use App\Models\VisitDose;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendDoseGivenWhatsAppNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.jawda_inpatient.base_url', 'http://jawda-inpatient.test/api');
        Config::set('services.whatsapp_cloud.token', 'test-token');
        Config::set('services.whatsapp_cloud.phone_number_id', '123456');
    }

    public function test_it_sends_a_whatsapp_template_to_the_companion_when_an_admission_is_linked(): void
    {
        Http::fake([
            '*/public/admissions/*/companion-contact' => Http::response([
                'name' => 'سارة أحمد',
                'relationship' => 'زوجة',
                'phone' => '0912345678',
            ], 200),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        $visit = DoctorVisit::factory()->create(['admission_id' => 9]);
        $dose = VisitDose::create([
            'doctor_visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'medication_name' => 'باراسيتامول',
            'dosage' => '500mg',
            'status' => 'given',
            'administered_at' => now(),
        ]);

        (new SendDoseGivenWhatsAppNotice($dose->id))->handle(new \App\Services\JawdaInpatientClient);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/public/admissions/9/companion-contact'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }

    public function test_it_does_nothing_when_the_visit_has_no_linked_admission(): void
    {
        Http::fake();

        $visit = DoctorVisit::factory()->create(['admission_id' => null]);
        $dose = VisitDose::create([
            'doctor_visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'medication_name' => 'باراسيتامول',
            'dosage' => '500mg',
            'status' => 'given',
            'administered_at' => now(),
        ]);

        (new SendDoseGivenWhatsAppNotice($dose->id))->handle(new \App\Services\JawdaInpatientClient);

        Http::assertNothingSent();
    }

    public function test_it_does_nothing_when_the_companion_has_no_phone(): void
    {
        Http::fake([
            '*/public/admissions/*/companion-contact' => Http::response([
                'name' => null,
                'relationship' => null,
                'phone' => null,
            ], 200),
        ]);

        $visit = DoctorVisit::factory()->create(['admission_id' => 9]);
        $dose = VisitDose::create([
            'doctor_visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'medication_name' => 'باراسيتامول',
            'dosage' => '500mg',
            'status' => 'given',
            'administered_at' => now(),
        ]);

        (new SendDoseGivenWhatsAppNotice($dose->id))->handle(new \App\Services\JawdaInpatientClient);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }
}
