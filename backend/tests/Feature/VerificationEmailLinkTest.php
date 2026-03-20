<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerificationEmailLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_email_points_to_frontend_root_entry_and_uses_signed_api_url(): void
    {
        config([
            'app.url' => 'https://api.example.com/api',
            'app.frontend_url' => 'https://app.example.com',
        ]);
        URL::forceRootUrl((string) config('app.url'));
        URL::forceScheme('https');

        Notification::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'verify-link-format@test.dev',
        ]);
        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification, array $channels) use ($user): bool {
            $mailMessage = $notification->toMail($user);
            $rendered = $mailMessage->render();

            return str_contains($rendered, 'https://app.example.com/?url=')
                && str_contains($rendered, '%2Fapi%2Fauth%2Femail%2Fverify%2F')
                && ! str_contains($rendered, '/api/api/auth/email/verify/');
        });
    }
}
