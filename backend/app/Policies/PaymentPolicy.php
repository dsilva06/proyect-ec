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
            ->exists();
    }
}
