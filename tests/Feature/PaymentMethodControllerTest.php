<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentMethodControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_the_seeded_payment_methods(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/payment-methods');

        $response->assertStatus(200);
        $response->assertJsonCount(4, 'data');
        $response->assertJsonPath('data.0.code', 'cash');
    }

    public function test_index_can_filter_to_active_only(): void
    {
        Sanctum::actingAs(User::factory()->create());
        PaymentMethod::where('code', 'ocash')->update(['is_active' => false]);

        $response = $this->getJson('/api/payment-methods?active=1');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_store_creates_a_new_payment_method(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/payment-methods', [
            'name' => 'محفظة',
            'code' => 'wallet',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.code', 'wallet');
        $this->assertDatabaseHas('payment_methods', ['code' => 'wallet', 'is_active' => true]);
    }

    public function test_update_can_toggle_active_state(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $fawry = PaymentMethod::where('code', 'fawry')->first();

        $response = $this->putJson("/api/payment-methods/{$fawry->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payment_methods', ['id' => $fawry->id, 'is_active' => false]);
    }

    public function test_default_cash_method_cannot_be_deactivated(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $cash = PaymentMethod::where('code', 'cash')->first();

        $response = $this->putJson("/api/payment-methods/{$cash->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('payment_methods', ['id' => $cash->id, 'is_active' => true]);
    }

    public function test_default_cash_method_cannot_be_deleted(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $cash = PaymentMethod::where('code', 'cash')->first();

        $response = $this->deleteJson("/api/payment-methods/{$cash->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('payment_methods', ['id' => $cash->id]);
    }

    public function test_payment_method_in_use_cannot_be_deleted(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $bank = PaymentMethod::where('code', 'bank')->first();
        DB::table('requested_service_deposits')->insert([
            'requested_service_id' => 1,
            'amount' => 10,
            'user_id' => 1,
            'is_bank' => true,
            'is_claimed' => false,
            'shift_id' => 1,
            'payment_method_id' => $bank->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/payment-methods/{$bank->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('payment_methods', ['id' => $bank->id]);
    }

    public function test_unused_custom_payment_method_can_be_deleted(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $method = PaymentMethod::create(['name' => 'محفظة', 'code' => 'wallet']);

        $response = $this->deleteJson("/api/payment-methods/{$method->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('payment_methods', ['id' => $method->id]);
    }
}
