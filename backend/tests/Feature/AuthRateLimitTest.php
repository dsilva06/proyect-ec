<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_endpoint_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'rate-limit@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'rate-limit@example.com',
            'password' => 'wrong-password',
        ])
            ->assertStatus(429)
            ->assertJson([
                'message' => 'Too many requests',
            ]);
    }

    public function test_register_endpoint_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/auth/register', [
                'first_name' => 'Rate',
                'last_name' => 'Limited',
                'document_type' => 'DNI',
                'document_number' => '1000000'.$attempt.'Z',
                'email' => "rate-register-{$attempt}@example.com",
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])->assertStatus(201);
        }

        $this->postJson('/api/auth/register', [
            'first_name' => 'Rate',
            'last_name' => 'Limited',
            'document_type' => 'DNI',
            'document_number' => '19999999Z',
            'email' => 'rate-register-overflow@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertStatus(429)
            ->assertJson([
                'message' => 'Too many requests',
            ]);
    }

    public function test_public_resend_verification_endpoint_is_rate_limited(): void
    {
        User::factory()->unverified()->create([
            'email' => 'resend-limit@example.com',
            'is_active' => true,
        ]);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/auth/email/resend', [
                'email' => 'resend-limit@example.com',
            ])->assertOk();
        }

        $this->postJson('/api/auth/email/resend', [
            'email' => 'resend-limit@example.com',
        ])
            ->assertStatus(429)
            ->assertJson([
                'message' => 'Too many requests',
            ]);
    }
}
