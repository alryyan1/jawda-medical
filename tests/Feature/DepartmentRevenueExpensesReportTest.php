<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Cost;
use App\Models\CostCategory;
use App\Models\DoctorVisit;
use App\Models\LabRequest;
use App\Models\MainTest;
use App\Models\RequestedService;
use App\Models\RequestedServiceDeposit;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentRevenueExpensesReportTest extends TestCase
{
    use RefreshDatabase;

    private function withCreatedAt(Model $model, string $date): Model
    {
        $model->created_at = $date;
        $model->save();

        return $model;
    }

    private function mainTest(): MainTest
    {
        $container = Container::firstOrCreate(['container_name' => 'Default']);

        return MainTest::create([
            'main_test_name' => 'CBC',
            'container_id' => $container->id,
        ]);
    }

    public function test_revenue_is_aggregated_per_service_group_with_cash_bank_split(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $user = User::factory()->create();
        $shift = Shift::factory()->create();

        $group = ServiceGroup::factory()->create(['name' => 'العيادة العامة']);
        $service = Service::factory()->create(['service_group_id' => $group->id, 'price' => 100]);
        $visit = DoctorVisit::factory()->create();
        $requestedService = RequestedService::factory()->create([
            'doctorvisits_id' => $visit->id,
            'service_id' => $service->id,
        ]);

        $this->withCreatedAt(RequestedServiceDeposit::create([
            'requested_service_id' => $requestedService->id,
            'amount' => 100,
            'user_id' => $user->id,
            'is_bank' => false,
            'shift_id' => $shift->id,
        ]), '2026-05-10');

        $this->withCreatedAt(RequestedServiceDeposit::create([
            'requested_service_id' => $requestedService->id,
            'amount' => 50,
            'user_id' => $user->id,
            'is_bank' => true,
            'shift_id' => $shift->id,
        ]), '2026-05-11');

        $response = $this->getJson('/api/reports/department-revenue-expenses?'.http_build_query([
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $group->id);
        $this->assertSame(100.0, $row['revenue']['cash']);
        $this->assertSame(50.0, $row['revenue']['bank']);
        $this->assertSame(150.0, $row['revenue']['total']);
    }

    public function test_lab_appears_as_a_virtual_department_and_excludes_unpaid_requests(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $visit = DoctorVisit::factory()->create();
        $mainTestId = $this->mainTest()->id;

        $this->withCreatedAt(LabRequest::create([
            'main_test_id' => $mainTestId,
            'pid' => $visit->patient_id,
            'doctor_visit_id' => $visit->id,
            'price' => 200,
            'count' => 1,
            'discount_per' => 0,
            'endurance' => 0,
            'amount_paid' => 200,
            'is_paid' => true,
            'is_bankak' => false,
        ]), '2026-05-10');

        $this->withCreatedAt(LabRequest::create([
            'main_test_id' => $mainTestId,
            'pid' => $visit->patient_id,
            'doctor_visit_id' => $visit->id,
            'price' => 80,
            'count' => 1,
            'discount_per' => 0,
            'endurance' => 0,
            'amount_paid' => 80,
            'is_paid' => true,
            'is_bankak' => true,
        ]), '2026-05-11');

        // Unpaid — must be excluded entirely.
        $this->withCreatedAt(LabRequest::create([
            'main_test_id' => $mainTestId,
            'pid' => $visit->patient_id,
            'doctor_visit_id' => $visit->id,
            'price' => 999,
            'count' => 1,
            'discount_per' => 0,
            'endurance' => 0,
            'amount_paid' => 0,
            'is_paid' => false,
            'is_bankak' => false,
        ]), '2026-05-12');

        $response = $this->getJson('/api/reports/department-revenue-expenses?'.http_build_query([
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]));

        $response->assertOk();
        $labRow = collect($response->json('data'))->firstWhere('is_lab', true);
        $this->assertNotNull($labRow);
        $this->assertSame('المختبر', $labRow['name']);
        $this->assertSame(200.0, $labRow['revenue']['cash']);
        $this->assertSame(80.0, $labRow['revenue']['bank']);
        $this->assertSame(280.0, $labRow['revenue']['total']);
    }

    public function test_expenses_roll_up_through_linked_categories_and_unlinked_ones_are_unassigned(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $shift = Shift::factory()->create();

        $group = ServiceGroup::factory()->create(['name' => 'العمليات']);
        $linkedCategoryA = CostCategory::create(['name' => 'صيانة', 'service_group_id' => $group->id]);
        $linkedCategoryB = CostCategory::create(['name' => 'أدوية', 'service_group_id' => $group->id]);
        $unlinkedCategory = CostCategory::create(['name' => 'عام']);

        $this->withCreatedAt(Cost::create([
            'shift_id' => $shift->id,
            'cost_category_id' => $linkedCategoryA->id,
            'description' => 'x',
            'amount' => 40,
            'amount_bankak' => 10,
        ]), '2026-05-10');

        $this->withCreatedAt(Cost::create([
            'shift_id' => $shift->id,
            'cost_category_id' => $linkedCategoryB->id,
            'description' => 'y',
            'amount' => 20,
            'amount_bankak' => 0,
        ]), '2026-05-11');

        $this->withCreatedAt(Cost::create([
            'shift_id' => $shift->id,
            'cost_category_id' => $unlinkedCategory->id,
            'description' => 'z',
            'amount' => 5,
            'amount_bankak' => 0,
        ]), '2026-05-12');

        $response = $this->getJson('/api/reports/department-revenue-expenses?'.http_build_query([
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $group->id);
        $this->assertSame(70.0, $row['expense']['total']); // 40+10+20
        $this->assertSame(0.0 - 70.0, $row['net']);
        $this->assertSame(5.0, $response->json('unassigned_expense.total'));
    }

    public function test_net_equals_revenue_minus_expense(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $user = User::factory()->create();
        $shift = Shift::factory()->create();

        $group = ServiceGroup::factory()->create(['name' => 'الأسنان']);
        $service = Service::factory()->create(['service_group_id' => $group->id, 'price' => 100]);
        $visit = DoctorVisit::factory()->create();
        $requestedService = RequestedService::factory()->create([
            'doctorvisits_id' => $visit->id,
            'service_id' => $service->id,
        ]);

        $this->withCreatedAt(RequestedServiceDeposit::create([
            'requested_service_id' => $requestedService->id,
            'amount' => 300,
            'user_id' => $user->id,
            'is_bank' => false,
            'shift_id' => $shift->id,
        ]), '2026-05-10');

        $category = CostCategory::create(['name' => 'مستلزمات أسنان', 'service_group_id' => $group->id]);
        $this->withCreatedAt(Cost::create([
            'shift_id' => $shift->id,
            'cost_category_id' => $category->id,
            'description' => 'x',
            'amount' => 120,
            'amount_bankak' => 0,
        ]), '2026-05-11');

        $response = $this->getJson('/api/reports/department-revenue-expenses?'.http_build_query([
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $group->id);
        $this->assertSame(300.0, $row['revenue']['total']);
        $this->assertSame(120.0, $row['expense']['total']);
        $this->assertSame(180.0, $row['net']);
    }

    public function test_records_outside_the_date_range_are_excluded_and_invalid_range_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $user = User::factory()->create();
        $shift = Shift::factory()->create();

        $group = ServiceGroup::factory()->create(['name' => 'الأشعة']);
        $service = Service::factory()->create(['service_group_id' => $group->id, 'price' => 100]);
        $visit = DoctorVisit::factory()->create();
        $requestedService = RequestedService::factory()->create([
            'doctorvisits_id' => $visit->id,
            'service_id' => $service->id,
        ]);

        $this->withCreatedAt(RequestedServiceDeposit::create([
            'requested_service_id' => $requestedService->id,
            'amount' => 999,
            'user_id' => $user->id,
            'is_bank' => false,
            'shift_id' => $shift->id,
        ]), '2026-06-15'); // outside the May range below

        $response = $this->getJson('/api/reports/department-revenue-expenses?'.http_build_query([
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $group->id);
        $this->assertSame(0.0, $row['revenue']['total']);

        $invalid = $this->getJson('/api/reports/department-revenue-expenses?'.http_build_query([
            'date_from' => '2026-05-31',
            'date_to' => '2026-05-01',
        ]));
        $invalid->assertStatus(422);
    }

    public function test_cost_category_update_sets_clears_and_renames_with_unique_validation(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $group = ServiceGroup::factory()->create();
        $other = CostCategory::create(['name' => 'موجودة']);
        $category = CostCategory::create(['name' => 'صيانة']);

        $this->putJson("/api/cost-categories/{$category->id}", [
            'service_group_id' => $group->id,
        ])->assertOk();
        $this->assertSame($group->id, $category->refresh()->service_group_id);

        $this->putJson("/api/cost-categories/{$category->id}", [
            'name' => 'صيانة وتشغيل',
        ])->assertOk();
        $this->assertSame('صيانة وتشغيل', $category->refresh()->name);
        $this->assertSame($group->id, $category->service_group_id); // untouched by the rename

        $this->putJson("/api/cost-categories/{$category->id}", [
            'service_group_id' => null,
        ])->assertOk();
        $this->assertNull($category->refresh()->service_group_id);

        $this->putJson("/api/cost-categories/{$category->id}", [
            'name' => $other->name,
        ])->assertStatus(422);

        $list = $this->getJson('/api/cost-categories-list')->assertOk();
        $listed = collect($list->json('data'))->firstWhere('id', $category->id);
        $this->assertNull($listed['service_group']);
    }
}
