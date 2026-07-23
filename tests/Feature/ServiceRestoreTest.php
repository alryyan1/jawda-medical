<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_trashed_services(): void
    {
        $serviceGroup = ServiceGroup::factory()->create();
        $activeService = Service::factory()->create([
            'name' => 'Active Service',
            'service_group_id' => $serviceGroup->id,
        ]);
        $deletedService = Service::factory()->create([
            'name' => 'Deleted Service',
            'service_group_id' => $serviceGroup->id,
        ]);

        $deletedService->delete();

        $response = $this->getJson('/api/services/trashed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $deletedService->id)
            ->assertJsonPath('data.0.name', 'Deleted Service')
            ->assertJsonMissing(['id' => $activeService->id]);

        $this->assertNotNull($response->json('data.0.deleted_at'));
    }

    public function test_it_can_restore_a_soft_deleted_service(): void
    {
        $serviceGroup = ServiceGroup::factory()->create();
        $service = Service::factory()->create([
            'name' => 'Restorable Service',
            'service_group_id' => $serviceGroup->id,
            'activate' => true,
            'variable' => false,
            'has_cost' => false,
        ]);

        $service->delete();

        $response = $this->postJson('/api/services/'.$service->id.'/restore');

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Restorable Service');

        $this->assertNotNull(Service::find($service->id));
        $this->assertNull(Service::withTrashed()->find($service->id)->deleted_at);
    }
}
