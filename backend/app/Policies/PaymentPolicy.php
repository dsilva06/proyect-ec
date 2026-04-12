<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $payment->registration()
            ->whereHas('team.users', fn ($query) => $query->where('users.id', $user->id))
            ->exists()
            || $payment->openEntry()
                ->where(function ($query) use ($user) {
                    $query->where('submitted_by_user_id', $user->id)
                        ->orWhereHas('team.users', fn ($teamUsersQuery) => $teamUsersQuery->where('users.id', $user->id));
                })
                ->exists();
    }
}
