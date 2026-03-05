<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_admin_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/admin/tournaments')->assertStatus(401);
    }

    public function test_player_cannot_access_admin_endpoints(): void
    {
        $player = User::factory()->create(['role' => 'player']);
        Sanctum::actingAs($player);

        $this->getJson('/api/admin/tournaments')->assertStatus(403);
    }

    public function test_invalid_admin_payload_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/internal-ranking-rule', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['win_points', 'final_played_bonus', 'final_won_bonus']);
    }
}
