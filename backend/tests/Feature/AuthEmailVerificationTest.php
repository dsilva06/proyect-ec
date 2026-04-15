<?php

namespace Tests\Feature;

use App\Mail\PendingRegistrationVerificationMail;
use App\Mail\WelcomePlayer;
use App\Models\Category;
use App\Models\PlayerProfile;
use App\Models\Registration;
use App\Models\Status;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Database\Seeders\StatusSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
    }

    public function test_register_returns_created_with_verify_email_message_and_no_token(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => 'V-10000001',
            'email' => 'verify-register-message@test.dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJson([
                'message' => 'Account created. Please verify your email before logging in.',
            ])
            ->assertJsonStructure([
                'message',
                'verification_context',
            ])
            ->assertJsonMissing(['token']);
    }

    public function test_registration_sends_verification_notification(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => 'V-10000001',
            'email' => 'verify-register-notify@test.dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJson([
                'message' => 'Account created. Please verify your email before logging in.',
            ])
            ->assertJsonStructure([
                'message',
                'verification_context',
            ])
            ->assertJsonMissing(['token']);

        $this->assertDatabaseMissing('users', [
            'email' => 'verify-register-notify@test.dev',
        ]);

        Mail::assertSent(PendingRegistrationVerificationMail::class, function (PendingRegistrationVerificationMail $mail): bool {
            return $mail->hasTo('verify-register-notify@test.dev');
        });
    }

    public function test_register_does_not_persist_user_until_email_is_verified(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => 'V-10000009',
            'email' => 'pending-register@test.dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated();

        $this->assertDatabaseMissing('users', [
            'email' => 'pending-register@test.dev',
        ]);
    }

    public function test_register_purges_legacy_unverified_user_records_before_sending_new_verification(): void
    {
        Mail::fake();

        $legacyUser = User::factory()->unverified()->create([
            'email' => 'legacy-pending@test.dev',
            'phone' => '+584121234567',
            'password_hash' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        PlayerProfile::query()->create([
            'user_id' => $legacyUser->id,
            'first_name' => 'Legacy',
            'last_name' => 'Pending',
            'dni' => 'V-10000123',
            'province_state' => 'Unknown',
            'ranking_source' => 'NONE',
        ]);

        $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => 'V-10000123',
            'email' => 'legacy-pending@test.dev',
            'phone' => '+584121234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated();

        $this->assertDatabaseMissing('users', [
            'id' => $legacyUser->id,
        ]);

        $this->assertDatabaseMissing('player_profiles', [
            'user_id' => $legacyUser->id,
        ]);

        Mail::assertSent(PendingRegistrationVerificationMail::class, function (PendingRegistrationVerificationMail $mail): bool {
            return $mail->hasTo('legacy-pending@test.dev');
        });
    }

    public function test_register_still_rejects_email_for_verified_users(): void
    {
        User::factory()->create([
            'email' => 'verified-register@test.dev',
        ]);

        $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => 'V-10000124',
            'email' => 'verified-register@test.dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_rejects_invalid_dni_format(): void
    {
        $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => '10000001',
            'email' => 'verify-register-invalid-dni@test.dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dni']);
    }

    public function test_register_accepts_passport_style_dni_prefix(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => 'P-10000001',
            'email' => 'verify-register-passport-prefix@test.dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertCreated();
    }

    public function test_register_rejects_invalid_dni_prefix(): void
    {
        $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => 'X-12345678',
            'email' => 'verify-register-invalid-prefix@test.dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dni']);
    }

    public function test_register_rejects_identity_numbers_longer_than_ten_digits(): void
    {
        $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => 'V-12345678901',
            'email' => 'verify-register-invalid-length@test.dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dni']);
    }

    public function test_register_normalizes_dni_format_before_verification(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => ' v12345678 ',
            'email' => 'verify-register-normalized-dni@test.dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated();

        $verificationEntryUrl = null;

        Mail::assertSent(PendingRegistrationVerificationMail::class, function (PendingRegistrationVerificationMail $mail) use (&$verificationEntryUrl): bool {
            if (! $mail->hasTo('verify-register-normalized-dni@test.dev')) {
                return false;
            }

            $verificationEntryUrl = $mail->verificationEntryUrl;

            return true;
        });

        parse_str((string) parse_url((string) $verificationEntryUrl, PHP_URL_QUERY), $entryQuery);
        $verificationApiUrl = (string) ($entryQuery['verify_url'] ?? '');
        $parsedVerificationApiUrl = parse_url($verificationApiUrl);
        $relativeVerificationUrl = (string) ($parsedVerificationApiUrl['path'] ?? '');
        if (! empty($parsedVerificationApiUrl['query'])) {
            $relativeVerificationUrl .= '?'.$parsedVerificationApiUrl['query'];
        }

        $this->getJson($relativeVerificationUrl)->assertOk();

        $this->assertDatabaseHas('player_profiles', [
            'dni' => 'V-12345678',
        ]);
    }

    public function test_register_rejects_invalid_phone_format(): void
    {
        $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => 'V-10000001',
            'email' => 'verify-register-invalid-phone@test.dev',
            'phone' => '0412-12',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
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

    public function test_verification_link_marks_email_as_verified_without_authenticating_the_user(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => 'V-10000010',
            'email' => 'verify-link@test.dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated();

        $verificationEntryUrl = null;

        Mail::assertSent(PendingRegistrationVerificationMail::class, function (PendingRegistrationVerificationMail $mail) use (&$verificationEntryUrl): bool {
            if (! $mail->hasTo('verify-link@test.dev')) {
                return false;
            }

            $verificationEntryUrl = $mail->verificationEntryUrl;

            return true;
        });

        parse_str((string) parse_url((string) $verificationEntryUrl, PHP_URL_QUERY), $entryQuery);
        $verificationApiUrl = (string) ($entryQuery['verify_url'] ?? '');
        $parsedVerificationApiUrl = parse_url($verificationApiUrl);
        $relativeVerificationUrl = (string) ($parsedVerificationApiUrl['path'] ?? '');
        if (! empty($parsedVerificationApiUrl['query'])) {
            $relativeVerificationUrl .= '?'.$parsedVerificationApiUrl['query'];
        }

        $this->getJson($relativeVerificationUrl)
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'verified',
                'name',
            ])
            ->assertJson([
                'message' => 'Email verified successfully.',
                'verified' => true,
            ])
            ->assertJsonMissing(['token', 'user']);

        $this->assertDatabaseHas('users', [
            'email' => 'verify-link@test.dev',
        ]);
        $this->assertNotNull(User::query()->where('email', 'verify-link@test.dev')->firstOrFail()->email_verified_at);
        Mail::assertNotQueued(WelcomePlayer::class);
    }

    public function test_already_verified_link_returns_verification_message_without_auth_payload(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => 'V-10000011',
            'email' => 'already-verified-link@test.dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated();

        $verificationEntryUrl = null;

        Mail::assertSent(PendingRegistrationVerificationMail::class, function (PendingRegistrationVerificationMail $mail) use (&$verificationEntryUrl): bool {
            if (! $mail->hasTo('already-verified-link@test.dev')) {
                return false;
            }

            $verificationEntryUrl = $mail->verificationEntryUrl;

            return true;
        });

        parse_str((string) parse_url((string) $verificationEntryUrl, PHP_URL_QUERY), $entryQuery);
        $verificationApiUrl = (string) ($entryQuery['verify_url'] ?? '');
        $parsedVerificationApiUrl = parse_url($verificationApiUrl);
        $relativeVerificationUrl = (string) ($parsedVerificationApiUrl['path'] ?? '');
        if (! empty($parsedVerificationApiUrl['query'])) {
            $relativeVerificationUrl .= '?'.$parsedVerificationApiUrl['query'];
        }

        $this->getJson($relativeVerificationUrl)->assertOk();

        $this->getJson($relativeVerificationUrl)
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'verified',
                'name',
            ])
            ->assertJson([
                'message' => 'Email is already verified.',
                'verified' => true,
            ])
            ->assertJsonMissing(['token', 'user']);
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
            absolute: false,
        );

        $this->getJson($expiredUrl)
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Invalid or expired verification link.',
            ])
            ->assertJsonMissing(['token']);
    }

    private function statusId(string $module, string $code): int
    {
        return (int) Status::query()
            ->where('module', $module)
            ->where('code', $code)
            ->firstOrFail()
            ->id;
    }
}
