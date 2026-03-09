<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Patient;
use App\Models\Ward;
use App\Models\Room;
use App\Models\Bed;
use App\Models\Admission;
use App\Models\AdmissionTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Carbon\Carbon;

class AdmissionSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $patient;
    protected $ward;
    protected $room;
    protected $bed;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create test patient
        $this->patient = Patient::factory()->create([
            'name' => 'Test Patient',
            'phone' => '1234567890',
        ]);

        // Create test ward
        $this->ward = Ward::factory()->create([
            'name' => 'Test Ward',
            'status' => true,
        ]);

        // Create test room
        $this->room = Room::factory()->create([
            'ward_id' => $this->ward->id,
            'room_number' => '101',
            'room_type' => 'normal',
            'capacity' => 4,
            'status' => true,
            'price_per_day' => 200.00,
        ]);

        // Create test bed
        $this->bed = Bed::factory()->create([
            'room_id' => $this->room->id,
            'bed_number' => '1',
            'status' => 'available',
        ]);
    }

    /**
     * Test creating a bed-based admission
     */
    public function test_create_bed_based_admission()
    {
        $response = $this->actingAs($this->user)->postJson('/api/admissions', [
            'patient_id' => $this->patient->id,
            'bed_id' => $this->bed->id,
            'admission_date' => '2026-02-03 10:30:00',
            'diagnosis' => 'Test diagnosis',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'patient_id',
                'ward_id',
                'room_id',
                'bed_id',
                'admission_date',
                'status',
            ],
        ]);

        // Verify admission was created
        $this->assertDatabaseHas('admissions', [
            'patient_id' => $this->patient->id,
            'bed_id' => $this->bed->id,
            'status' => 'admitted',
        ]);

        // Verify bed status was updated
        $this->assertDatabaseHas('beds', [
            'id' => $this->bed->id,
            'status' => 'occupied',
        ]);
    }

    /**
     * Test creating a room admission (implicitly bed-based or just room-linked)
     */
    public function test_create_room_admission()
    {
        $response = $this->actingAs($this->user)->postJson('/api/admissions', [
            'patient_id' => $this->patient->id,
            'bed_id' => $this->bed->id,
            'admission_date' => '2026-02-03 14:00:00',
            'diagnosis' => 'Test diagnosis',
        ]);

        $response->assertStatus(201);

        // Verify admission was created (room_id no longer on admissions table)
        $this->assertDatabaseHas('admissions', [
            'patient_id' => $this->patient->id,
            'bed_id' => $this->bed->id,
            'status' => 'admitted',
        ]);
    }


    /**
     * Test stay days calculation - Morning period (7 AM - 12 PM)
     */
    public function test_stay_days_calculation_morning_period()
    {
        $admission = Admission::factory()->create([
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'bed_id' => $this->bed->id,
            'admission_date' => '2026-02-03 08:00:00',
            'discharge_date' => '2026-02-04 10:00:00',
            'status' => 'discharged',
        ]);

        // 26 hours = 2 days (rounded up)
        $this->assertEquals(2, $admission->days_admitted);
    }

    /**
     * Test stay days calculation - Evening period (1 PM - 6 AM)
     */
    public function test_stay_days_calculation_evening_period()
    {
        $admission = Admission::factory()->create([
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'bed_id' => $this->bed->id,
            'admission_date' => '2026-02-03 14:00:00',
            'discharge_date' => '2026-02-04 06:00:00',
            'status' => 'discharged',
        ]);

        // Should be 1 full day
        $this->assertEquals(1, $admission->days_admitted);
    }

    /**
     * Test stay days calculation - Default period (6 AM - 7 AM)
     */
    public function test_stay_days_calculation_default_period()
    {
        $admission = Admission::factory()->create([
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'bed_id' => $this->bed->id,
            'admission_date' => '2026-02-03 06:30:00',
            'discharge_date' => '2026-02-05 06:30:00',
            'status' => 'discharged',
        ]);

        // (5 - 3) + 1 = 3 days
        $this->assertEquals(3, $admission->days_admitted);
    }

    /**
     * Test adding a debit transaction (charge)
     */
    public function test_add_debit_transaction()
    {
        $admission = Admission::factory()->create([
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'bed_id' => $this->bed->id,
            'status' => 'admitted',
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/admissions/{$admission->id}/transactions",
            [
                'type' => 'debit',
                'amount' => 500.00,
                'description' => 'Test charge',
                'reference_type' => 'charge',
            ]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('admission_transactions', [
            'admission_id' => $admission->id,
            'type' => 'debit',
            'amount' => 500.00,
        ]);
    }

    /**
     * Test adding a credit transaction (payment)
     */
    public function test_add_credit_transaction()
    {
        $admission = Admission::factory()->create([
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'bed_id' => $this->bed->id,
            'status' => 'admitted',
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/admissions/{$admission->id}/transactions",
            [
                'type' => 'credit',
                'amount' => 1000.00,
                'description' => 'Test payment',
                'reference_type' => 'deposit',
                'is_bank' => false,
            ]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('admission_transactions', [
            'admission_id' => $admission->id,
            'type' => 'credit',
            'amount' => 1000.00,
            'is_bank' => false,
        ]);
    }

    /**
     * Test ledger balance calculation
     */
    public function test_ledger_balance_calculation()
    {
        $admission = Admission::factory()->create([
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'bed_id' => $this->bed->id,
            'status' => 'admitted',
        ]);

        // Add charges (debits)
        AdmissionTransaction::factory()->create([
            'admission_id' => $admission->id,
            'type' => 'debit',
            'amount' => 500.00,
            'description' => 'Room charges',
        ]);

        AdmissionTransaction::factory()->create([
            'admission_id' => $admission->id,
            'type' => 'debit',
            'amount' => 300.00,
            'description' => 'Service charges',
        ]);

        // Add payments (credits)
        AdmissionTransaction::factory()->create([
            'admission_id' => $admission->id,
            'type' => 'credit',
            'amount' => 400.00,
            'description' => 'Payment',
        ]);

        $response = $this->actingAs($this->user)->getJson(
            "/api/admissions/{$admission->id}/ledger"
        );

        $response->assertStatus(200);
        $data = $response->json();

        // Balance = 800 - 400 = 400
        $this->assertEquals(800.00, $data['summary']['total_debits']);
        $this->assertEquals(400.00, $data['summary']['total_credits']);
        $this->assertEquals(400.00, $data['summary']['balance']);
    }

    /**
     * Test room charges calculation
     */
    public function test_room_charges_calculation()
    {
        $admission = Admission::factory()->create([
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'bed_id' => $this->bed->id,
            'admission_date' => '2026-02-03 10:00:00',
            'discharge_date' => '2026-02-08 10:00:00',
            'status' => 'discharged',
        ]);

        // Days = 5, Price per day = 200
        // Expected: 5 * 200 = 1000
        $days = $admission->days_admitted;
        $pricePerDay = $this->room->price_per_day;
        $expectedAmount = $days * $pricePerDay;

        $this->assertEquals(5, $days);
        $this->assertEquals(1000.00, $expectedAmount);
    }

    /**
     * Test transferring a patient
     */
    public function test_transfer_patient()
    {
        // Create second room and bed
        $room2 = Room::factory()->create([
            'ward_id' => $this->ward->id,
            'room_number' => '102',
            'status' => true,
        ]);

        $bed2 = Bed::factory()->create([
            'room_id' => $room2->id,
            'bed_number' => '1',
            'status' => 'available',
        ]);

        $admission = Admission::factory()->create([
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'bed_id' => $this->bed->id,
            'status' => 'admitted',
        ]);

        $response = $this->actingAs($this->user)->putJson(
            "/api/admissions/{$admission->id}/transfer",
            [
                'bed_id' => (string) $bed2->id,
                'notes' => 'Transfer test',
            ]
        );

        $response->assertStatus(200);

        // Verify admission was updated (room_id no longer on admissions table)
        $this->assertDatabaseHas('admissions', [
            'id' => $admission->id,
            'bed_id' => $bed2->id,
        ]);

        // Verify old bed is available
        $this->assertDatabaseHas('beds', [
            'id' => $this->bed->id,
            'status' => 'available',
        ]);

        // Verify new bed is occupied
        $this->assertDatabaseHas('beds', [
            'id' => $bed2->id,
            'status' => 'occupied',
        ]);
    }

    /**
     * Test discharging a patient (even with balance)
     */
    public function test_discharge_patient()
    {
        $admission = Admission::factory()->create([
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'bed_id' => $this->bed->id,
            'status' => 'admitted',
        ]);

        // Add a debit transaction so balance is not zero
        AdmissionTransaction::factory()->create([
            'admission_id' => $admission->id,
            'type' => 'debit',
            'amount' => 500.00,
        ]);

        $response = $this->actingAs($this->user)->putJson(
            "/api/admissions/{$admission->id}/discharge",
            [
                'discharge_date' => '2026-02-05 14:00:00',
                'notes' => 'Discharge test',
            ]
        );

        $response->assertStatus(200);

        // Verify admission status
        $this->assertDatabaseHas('admissions', [
            'id' => $admission->id,
            'status' => 'discharged',
            'discharge_date' => '2026-02-05 14:00:00',
        ]);

        // Verify bed is available
        $this->assertDatabaseHas('beds', [
            'id' => $this->bed->id,
            'status' => 'available',
        ]);
    }

    /**
     * Test cannot add transaction for discharged patient
     */
    public function test_cannot_add_transaction_for_discharged_patient()
    {
        $admission = Admission::factory()->create([
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'bed_id' => $this->bed->id,
            'status' => 'discharged',
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/admissions/{$admission->id}/transactions",
            [
                'type' => 'debit',
                'amount' => 100.00,
                'description' => 'Test',
            ]
        );

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'لا يمكن إضافة معاملة للمريض غير المقيم.',
        ]);
    }


    /**
     * Test admission list filtering
     */
    public function test_admission_list_filtering()
    {
        // Create multiple admissions
        Admission::factory()->create([
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'bed_id' => $this->bed->id,
            'status' => 'admitted',
        ]);

        Admission::factory()->create([
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'bed_id' => $this->bed->id,
            'status' => 'discharged',
        ]);

        // Filter by status
        $response = $this->actingAs($this->user)->getJson(
            '/api/admissions?status=admitted'
        );

        $response->assertStatus(200);
        $data = $response->json();

        // All results should be admitted
        foreach ($data['data'] as $admission) {
            $this->assertEquals('admitted', $admission['status']);
        }
    }
}
