<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Doctor;
use App\Models\DoctorLabTestProfile;
use App\Models\MainTest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorLabTestProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeMainTest(): MainTest
    {
        $container = Container::create(['container_name' => 'Tube']);

        return MainTest::create([
            'main_test_name' => 'CBC',
            'container_id' => $container->id,
            'price' => 100,
            'available' => true,
        ]);
    }

    public function test_index_only_lists_the_authenticated_doctors_profiles(): void
    {
        $doctorA = Doctor::factory()->create();
        $doctorB = Doctor::factory()->create();
        $test = $this->makeMainTest();

        $profileA = DoctorLabTestProfile::create(['doctor_id' => $doctorA->id, 'name' => 'Panel A']);
        $profileA->mainTests()->attach($test->id);

        $profileB = DoctorLabTestProfile::create(['doctor_id' => $doctorB->id, 'name' => 'Panel B']);
        $profileB->mainTests()->attach($test->id);

        Sanctum::actingAs(User::factory()->forDoctor($doctorA->id)->create());

        $response = $this->getJson('/api/doctor-lab-test-profiles');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Panel A');
    }

    public function test_store_creates_a_profile_with_main_tests(): void
    {
        $doctor = Doctor::factory()->create();
        $test = $this->makeMainTest();
        Sanctum::actingAs(User::factory()->forDoctor($doctor->id)->create());

        $response = $this->postJson('/api/doctor-lab-test-profiles', [
            'name' => 'My Panel',
            'main_test_ids' => [$test->id],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('doctor_lab_test_profiles', [
            'doctor_id' => $doctor->id,
            'name' => 'My Panel',
        ]);
        $this->assertSame([$test->id], $response->json('data.main_test_ids'));
    }

    public function test_store_requires_a_linked_doctor_account(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/doctor-lab-test-profiles', [
            'name' => 'My Panel',
            'main_test_ids' => [],
        ]);

        $response->assertStatus(403);
    }

    public function test_update_is_forbidden_for_a_profile_owned_by_another_doctor(): void
    {
        $owner = Doctor::factory()->create();
        $other = Doctor::factory()->create();
        $test = $this->makeMainTest();

        $profile = DoctorLabTestProfile::create(['doctor_id' => $owner->id, 'name' => 'Owner Panel']);
        $profile->mainTests()->attach($test->id);

        Sanctum::actingAs(User::factory()->forDoctor($other->id)->create());

        $response = $this->putJson("/api/doctor-lab-test-profiles/{$profile->id}", [
            'name' => 'Hijacked',
            'main_test_ids' => [$test->id],
        ]);

        $response->assertStatus(403);
    }

    public function test_destroy_removes_the_profile(): void
    {
        $doctor = Doctor::factory()->create();
        $test = $this->makeMainTest();

        $profile = DoctorLabTestProfile::create(['doctor_id' => $doctor->id, 'name' => 'To Delete']);
        $profile->mainTests()->attach($test->id);

        Sanctum::actingAs(User::factory()->forDoctor($doctor->id)->create());

        $response = $this->deleteJson("/api/doctor-lab-test-profiles/{$profile->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('doctor_lab_test_profiles', ['id' => $profile->id]);
    }
}
