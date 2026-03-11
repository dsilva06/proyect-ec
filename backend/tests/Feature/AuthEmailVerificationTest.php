<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_created_with_verify_email_message_and_no_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => 'V-10000001',
            'email' => 'verify-register-message@test.dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertExactJson([
                'message' => 'Account created. Please verify your email before logging in.',
            ])
            ->assertJsonMissing(['token']);
    }

    public function test_registration_sends_verification_notification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => 'V-10000001',
            'email' => 'verify-register-notify@test.dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertExactJson([
                'message' => 'Account created. Please verify your email before logging in.',
            ]);

        $user = User::query()->where('email', 'verify-register-notify@test.dev')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_login_is_blocked_when_email_is_unverified(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified-login@test.dev',
            'password_hash' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Please verify your email before logging in.',
            ]);
    }

    public function test_verified_user_can_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'verified-login@test.dev',
            'password_hash' => Hash::make('Password123!'),
            'is_active' => true,
            'email_verified_at' => now(),
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

    public function test_verification_link_marks_email_as_verified(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'verify-link@test.dev',
        ]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $this->getJson($url)
            ->assertOk()
            ->assertJson([
                'message' => 'Email verified successfully.',
            ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_resend_verification_email_works_for_unverified_user(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/verification-notification')
            ->assertOk()
            ->assertJson([
                'message' => 'Verification email sent.',
            ]);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_public_resend_verification_sends_for_existing_unverified_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'public-resend-unverified@test.dev',
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/email/resend', [
            'email' => '  PUBLIC-RESEND-UNVERIFIED@test.dev  ',
        ])
            ->assertOk()
            ->assertExactJson([
                'message' => 'If the account exists and is not yet verified, a verification email has been sent.',
            ]);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_public_resend_verification_returns_generic_response_for_nonexistent_email(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/email/resend', [
            'email' => 'missing-account@test.dev',
        ])
            ->assertOk()
            ->assertExactJson([
                'message' => 'If the account exists and is not yet verified, a verification email has been sent.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_public_resend_verification_returns_generic_response_for_verified_email(): void
    {
        Notification::fake();

        User::factory()->create([
            'email' => 'public-resend-verified@test.dev',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/email/resend', [
            'email' => 'public-resend-verified@test.dev',
        ])
            ->assertOk()
            ->assertExactJson([
                'message' => 'If the account exists and is not yet verified, a verification email has been sent.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_unverified_user_with_valid_token_cannot_access_auth_me_endpoint(): void
    {
        $user = User::factory()->unverified()->create([
            'is_active' => true,
        ]);

        $token = $user->createToken('auth_token');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/auth/me')
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Please verify your email before accessing this resource.',
            ]);
    }

    public function test_unverified_user_with_valid_token_cannot_access_player_endpoint(): void
    {
        $user = User::factory()->unverified()->create([
            'role' => 'player',
            'is_active' => true,
        ]);

        $token = $user->createToken('auth_token');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/player/me')
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Please verify your email before accessing this resource.',
            ]);
    }

    public function test_unverified_admin_with_valid_token_cannot_access_admin_endpoint(): void
    {
        $user = User::factory()->unverified()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $token = $user->createToken('auth_token');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/admin/tournaments')
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Please verify your email before accessing this resource.',
            ]);
    }

    public function test_resend_verification_returns_clean_response_for_verified_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/verification-notification')
            ->assertOk()
            ->assertJson([
                'message' => 'Email is already verified.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_expired_or_invalid_signed_verification_link_returns_json_error(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'expired-link@test.dev',
        ]);

        $expiredUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $this->getJson($expiredUrl)
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Invalid or expired verification link.',
            ]);
    }
}
