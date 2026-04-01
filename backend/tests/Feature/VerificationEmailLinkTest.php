<?php

namespace Tests\Feature;

use App\Mail\PendingRegistrationVerificationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerificationEmailLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_email_points_to_frontend_root_entry_and_uses_signed_api_url(): void
    {
        config([
            'app.url' => 'https://api.example.com',
            'app.frontend_url' => 'https://app.example.com',
        ]);
        URL::forceRootUrl((string) config('app.url'));
        URL::forceScheme('https');

        Mail::fake();

        $this->postJson('/api/auth/register', [
            'first_name' => 'Diego',
            'last_name' => 'Silva',
            'dni' => 'V-11111111',
            'email' => 'verify-link-format@test.dev',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated();

        Mail::assertSent(PendingRegistrationVerificationMail::class, function (PendingRegistrationVerificationMail $mail): bool {
            return $mail->hasTo('verify-link-format@test.dev')
                && str_contains($mail->verificationEntryUrl, 'https://app.example.com/?verify_url=')
                && str_contains($mail->verificationEntryUrl, '%2Fapi%2Fauth%2Femail%2Fverify%2F0%2F')
                && ! str_contains($mail->verificationEntryUrl, '/api/api/auth/email/verify/');
        });
    }
}
