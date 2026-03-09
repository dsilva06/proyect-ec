<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiErrorConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_api_route_returns_consistent_json_404(): void
    {
        $this->getJson('/api/route-that-does-not-exist')
            ->assertStatus(404)
            ->assertJson([
                'message' => 'Route not found',
            ]);
    }

    public function test_missing_model_returns_consistent_json_404(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/tournaments/999999')
            ->assertStatus(404)
            ->assertJson([
                'message' => 'Resource not found',
            ]);
    }
}
