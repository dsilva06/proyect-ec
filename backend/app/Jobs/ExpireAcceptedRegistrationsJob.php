<?php

namespace App\Jobs;

use App\Models\Registration;
use App\Services\StatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireAcceptedRegistrationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(StatusService $statusService): void
    {
        $expiredStatusId = $statusService->resolveStatusId('registration', 'expired');

        $registrations = Registration::query()
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<', now())
            ->whereHas('status', function ($query) {
                $query->whereIn('code', ['accepted', 'payment_pending']);
            })
            ->get();

        foreach ($registrations as $registration) {
            $statusService->transition($registration, 'registration', $expiredStatusId, null, 'payment_expired');
            $registration->cancelled_at = now();
            $registration->save();
        }
    }
}
