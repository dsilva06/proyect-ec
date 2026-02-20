<?php

namespace App\Providers;

use App\Models\Payment;
use App\Models\Registration;
use App\Models\Tournament;
use App\Policies\PaymentPolicy;
use App\Policies\RegistrationPolicy;
use App\Policies\TournamentPolicy;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
    }
}
