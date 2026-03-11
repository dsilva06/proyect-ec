<?php

namespace App\Providers;

use App\Models\Payment;
use App\Models\Registration;
use App\Models\Tournament;
use App\Policies\PaymentPolicy;
use App\Policies\RegistrationPolicy;
use App\Policies\TournamentPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();
        Gate::policy(Registration::class, RegistrationPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Tournament::class, TournamentPolicy::class);

        RateLimiter::for('auth-login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));
            $ip = (string) $request->ip();

            return Limit::perMinute(10)
                ->by($email.'|'.$ip);
        });

        RateLimiter::for('auth-register', function (Request $request) {
            return Limit::perMinute(5)
                ->by((string) $request->ip());
        });

        RateLimiter::for('auth-session', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute(60)
                ->by((string) $userId.'|'.(string) $request->ip());
        });

        RateLimiter::for('auth-resend-verification', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(3)->by($email.'|'.$ip),
                Limit::perMinute(20)->by($ip),
            ];
        });
    }
}
