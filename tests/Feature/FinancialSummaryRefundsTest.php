<?php

namespace Tests\Feature;

use App\Models\DoctorVisit;
use App\Models\LabRequest;
use App\Models\RequestedService;
use App\Models\ReturnedLabRequest;
use App\Models\ReturnedRequestedService;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialSummaryRefundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_summary_includes_refunds_and_discounts()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $shift = Shift::create(['user_opened_id' => $user->id]);

        $visit = DoctorVisit::create([
            'patient_id' => 1, // Mock patient
            'user_id' => $user->id,
            'shift_id' => $shift->id,
        ]);

        // Create a lab request with discount
        LabRequest::create([
            'doctor_visit_id' => $visit->id,
            'price' => 100,
            'discount_per' => 10, // 10% discount = 10
            'is_paid' => true,
            'amount_paid' => 90,
        ]);

        // Create a service with discount
        RequestedService::create([
            'doctor_visit_id' => $visit->id,
            'service_id' => 1,
            'price' => 50,
            'discount' => 5,
            'is_paid' => true,
            'paid_amount' => 45,
        ]);

        // Create a refund
        ReturnedLabRequest::create([
            'lab_request_id' => 1,
            'amount' => 20,
            'shift_id' => $shift->id,
            'returned_payment_method' => 'cash',
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/dashboard/financial-summary?shift_id={$shift->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_refunds', 20.0)
            ->assertJsonPath('data.total_discounts', 15.0);
    }
}
