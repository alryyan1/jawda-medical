<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPatientSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_patients_by_name_or_phone_without_authentication(): void
    {
        $match = Patient::factory()->create(['name' => 'Ahmed Ali', 'phone' => '0991961111']);
        Patient::factory()->create(['name' => 'Someone Else', 'phone' => '0991962222']);

        $response = $this->getJson('/api/public/patients-search?search=Ahmed');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('data.0.name', 'Ahmed Ali');
    }

    public function test_it_returns_an_empty_list_without_a_matching_search_term(): void
    {
        Patient::factory()->create(['name' => 'Ahmed Ali']);

        $response = $this->getJson('/api/public/patients-search?search=NoSuchPatient');

        $response->assertOk()->assertJsonCount(0, 'data');
    }
}
