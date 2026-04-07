<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_returns_token_and_user_payload(): void
    {
        $user = User::factory()->create([
            'email' => 'player@test.dev',
            'password_hash' => Hash::make('Password123!'),
            'role' => 'player',
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'email', 'role', 'is_active'],
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'player@test.dev',
            'password_hash' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'player@test.dev',
            'password' => 'WrongPassword!',
        ])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Validation error',
                'errors' => [
                    'email' => ['Invalid credentials'],
                ],
            ]);
    }

    public function test_protected_auth_endpoint_denies_access_without_token(): void
    {
        $this->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthorized',
            ]);
    }

    public function test_me_with_invalid_token_returns_unauthorized(): void
    {
        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthorized',
            ]);
    }

    public function test_login_rejects_inactive_user(): void
    {
        User::factory()->create([
            'email' => 'inactive@test.dev',
            'password_hash' => Hash::make('Password123!'),
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'inactive@test.dev',
            'password' => 'Password123!',
        ])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Validation error',
                'errors' => [
                    'email' => ['Account is inactive'],
                ],
            ]);
    }

    public function test_inactive_user_with_token_cannot_access_protected_player_endpoint(): void
    {
        $inactivePlayer = User::factory()->create([
            'role' => 'player',
            'is_active' => false,
        ]);

        Sanctum::actingAs($inactivePlayer);

        $this->getJson('/api/player/me')
            ->assertStatus(403)
            ->assertJson([
                'message' => 'User is inactive',
            ]);
    }

    public function test_inactive_user_with_token_cannot_access_auth_me_endpoint(): void
    {
        $inactivePlayer = User::factory()->create([
            'role' => 'player',
            'is_active' => false,
        ]);

        $token = $inactivePlayer->createToken('auth_token');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/auth/me')
            ->assertStatus(403)
            ->assertJson([
                'message' => 'User is inactive',
            ]);
    }

    public function test_player_role_cannot_access_admin_endpoint(): void
    {
        $player = User::factory()->create([
            'role' => 'player',
            'is_active' => true,
        ]);

        Sanctum::actingAs($player);

        $this->getJson('/api/admin/tournaments')
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden',
            ]);
    }

    public function test_logout_revokes_only_current_token(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $currentToken = $user->createToken('current');
        $otherToken = $user->createToken('other');

        $this->withHeader('Authorization', 'Bearer '.$currentToken->plainTextToken)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);

        $this->assertNull(PersonalAccessToken::findToken($currentToken->plainTextToken));
        $this->assertNotNull(PersonalAccessToken::findToken($otherToken->plainTextToken));
    }

    public function test_me_with_valid_token_returns_safe_payload(): void
    {
        $user = User::factory()->create([
            'email' => 'safe-user@test.dev',
            'role' => 'player',
            'is_active' => true,
        ]);

        $token = $user->createToken('auth_token');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'safe-user@test.dev')
            ->assertJsonPath('user.role', 'player')
            ->assertJsonMissingPath('user.password_hash')
            ->assertJsonMissingPath('user.remember_token');
    }

    public function test_idle_token_is_rejected_after_configured_inactivity_window(): void
    {
        config(['sanctum.idle_timeout' => 30]);

        $user = User::factory()->create([
            'email' => 'idle-player@test.dev',
            'role' => 'player',
            'is_active' => true,
        ]);

        $token = $user->createToken('auth_token');
        $storedToken = PersonalAccessToken::findToken($token->plainTextToken);

        $storedToken->forceFill([
            'last_used_at' => now()->subMinutes(31),
        ])->save();

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthorized',
            ]);

        $this->assertNull(PersonalAccessToken::findToken($token->plainTextToken));
    }

    public function test_recent_token_activity_keeps_session_valid(): void
    {
        config(['sanctum.idle_timeout' => 30]);

        $user = User::factory()->create([
            'email' => 'active-player@test.dev',
            'role' => 'player',
            'is_active' => true,
        ]);

        $token = $user->createToken('auth_token');
        $storedToken = PersonalAccessToken::findToken($token->plainTextToken);

        $storedToken->forceFill([
            'last_used_at' => now()->subMinutes(5),
        ])->save();

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'active-player@test.dev');
    }

    public function test_login_validation_error_returns_standard_api_shape(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Validation error',
            ])
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
