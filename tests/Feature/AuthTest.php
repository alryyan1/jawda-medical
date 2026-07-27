<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_a_token_that_expires_after_the_configured_ttl(): void
    {
        $user = User::factory()->create([
            'username' => 'jdoe',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/login', [
            'username' => 'jdoe',
            'password' => 'secret123',
        ])->assertStatus(200)->assertJsonStructure(['user', 'token']);

        $token = $user->tokens()->latest()->first();

        $this->assertNotNull($token->expires_at);
        $this->assertTrue(
            $token->expires_at->between(
                now()->addMinutes(config('sanctum.token_ttl'))->subMinute(),
                now()->addMinutes(config('sanctum.token_ttl'))->addMinute()
            )
        );
    }

    public function test_authenticated_requests_slide_the_token_expiration_forward(): void
    {
        $user = User::factory()->create();
        $newToken = $user->createToken('test-device', ['*'], now()->addMinutes(5));

        $this->withHeader('Authorization', 'Bearer '.$newToken->plainTextToken)
            ->getJson('/api/user')
            ->assertStatus(200);

        $newToken->accessToken->refresh();

        $this->assertTrue($newToken->accessToken->expires_at->gt(now()->addMinutes(10)));
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        User::factory()->create(['username' => 'jdoe']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'username' => 'jdoe',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/login', [
            'username' => 'jdoe',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
