<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Container;
use App\Models\DoctorVisit;
use App\Models\LabRequest;
use App\Models\MainTest;
use App\Models\Patient;
use App\Models\RequestedService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserPatientActivityReportTest extends TestCase
{
    use RefreshDatabase;

    private function visitForUser(User $user, array $patientOverrides = []): DoctorVisit
    {
        // 'created_at' isn't mass-assignable on Patient, so it must be set via
        // direct property assignment + save() to land after the factory's own now().
        $createdAt = $patientOverrides['created_at'] ?? null;
        unset($patientOverrides['created_at']);

        $patient = Patient::factory()->create(array_merge([
            'user_id' => $user->id,
            'company_id' => null,
        ], $patientOverrides));

        if ($createdAt) {
            $patient->created_at = $createdAt;
            $patient->save();
        }

        return DoctorVisit::factory()->create(['patient_id' => $patient->id]);
    }

    private function mainTest(): MainTest
    {
        $container = Container::firstOrCreate(['container_name' => 'Default']);

        return MainTest::create([
            'main_test_name' => 'CBC',
            'container_id' => $container->id,
        ]);
    }

    public function test_report_only_includes_the_selected_users_patients_within_the_date_range(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $targetUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->visitForUser($targetUser, ['created_at' => '2026-05-10']);
        $this->visitForUser($targetUser, ['created_at' => '2026-05-15']);
        $this->visitForUser($targetUser, ['created_at' => '2026-06-01']); // outside range
        $this->visitForUser($otherUser, ['created_at' => '2026-05-12']); // wrong user

        $response = $this->getJson('/api/reports/user-patient-activity?'.http_build_query([
            'user_id' => $targetUser->id,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]));

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(2, $response->json('summary.total_patients'));
    }

    public function test_report_filters_by_company(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $user = User::factory()->create();
        $company = Company::create([
            'name' => 'Acme Insurance',
            'lab_endurance' => '0',
            'service_endurance' => '0',
            'status' => true,
            'lab_roof' => 0,
            'service_roof' => 0,
            'phone' => '0000000',
            'email' => 'acme@example.com',
        ]);

        $this->visitForUser($user, ['created_at' => '2026-05-10', 'company_id' => $company->id]);
        $this->visitForUser($user, ['created_at' => '2026-05-11', 'company_id' => null]);

        $response = $this->getJson('/api/reports/user-patient-activity?'.http_build_query([
            'user_id' => $user->id,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
            'company_id' => $company->id,
        ]));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($company->name, $response->json('data.0.company_name'));
    }

    public function test_discounted_only_filter_catches_both_service_and_lab_discounts(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $user = User::factory()->create();

        $noDiscountVisit = $this->visitForUser($user, ['created_at' => '2026-05-10']);
        RequestedService::factory()->create(['doctorvisits_id' => $noDiscountVisit->id, 'discount' => 0, 'discount_per' => 0]);

        $serviceDiscountVisit = $this->visitForUser($user, ['created_at' => '2026-05-11']);
        RequestedService::factory()->create(['doctorvisits_id' => $serviceDiscountVisit->id, 'discount' => 0, 'discount_per' => 20]);

        $labDiscountVisit = $this->visitForUser($user, ['created_at' => '2026-05-12']);
        LabRequest::create([
            'main_test_id' => $this->mainTest()->id,
            'pid' => $labDiscountVisit->patient_id,
            'doctor_visit_id' => $labDiscountVisit->id,
            'price' => '100',
            'count' => 1,
            'discount_per' => 30,
            'endurance' => 0,
        ]);

        $response = $this->getJson('/api/reports/user-patient-activity?'.http_build_query([
            'user_id' => $user->id,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
            'discounted_only' => 1,
        ]));

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(2, $response->json('summary.discounted_patients_count'));
    }

    public function test_summary_totals_and_high_risk_flag_are_computed_from_discount_percent(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $user = User::factory()->create();

        $highRiskVisit = $this->visitForUser($user, ['created_at' => '2026-05-10']);
        RequestedService::factory()->create([
            'doctorvisits_id' => $highRiskVisit->id,
            'price' => 100,
            'count' => 1,
            'discount' => 0,
            'discount_per' => 60,
        ]);

        $fullDiscountVisit = $this->visitForUser($user, ['created_at' => '2026-05-11']);
        RequestedService::factory()->create([
            'doctorvisits_id' => $fullDiscountVisit->id,
            'price' => 50,
            'count' => 1,
            'discount' => 0,
            'discount_per' => 100,
        ]);

        $response = $this->getJson('/api/reports/user-patient-activity?'.http_build_query([
            'user_id' => $user->id,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]));

        $response->assertOk();
        $rows = collect($response->json('data'))->keyBy('patient_id');
        $this->assertTrue($rows[$highRiskVisit->patient_id]['is_high_risk']);
        $this->assertSame(60.0, $rows[$highRiskVisit->patient_id]['discount_percent']);
        $this->assertSame(1, $response->json('summary.full_discount_count'));
        $this->assertEquals(110.0, $response->json('summary.total_discount'));
    }

    public function test_repeat_patients_receiving_discounts_are_flagged(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $user = User::factory()->create();

        $visit1 = $this->visitForUser($user, ['created_at' => '2026-05-10', 'name' => 'Jane Doe', 'phone' => '0100000001']);
        RequestedService::factory()->create(['doctorvisits_id' => $visit1->id, 'price' => 100, 'count' => 1, 'discount_per' => 10]);

        $visit2 = $this->visitForUser($user, ['created_at' => '2026-05-20', 'name' => 'Jane Doe', 'phone' => '0100000001']);
        RequestedService::factory()->create(['doctorvisits_id' => $visit2->id, 'price' => 100, 'count' => 1, 'discount_per' => 15]);

        $response = $this->getJson('/api/reports/user-patient-activity?'.http_build_query([
            'user_id' => $user->id,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]));

        $response->assertOk();
        $repeatPatients = $response->json('repeat_patients');
        $this->assertCount(1, $repeatPatients);
        $this->assertSame('Jane Doe', $repeatPatients[0]['patient_name']);
        $this->assertSame(2, $repeatPatients[0]['times']);
    }
}
