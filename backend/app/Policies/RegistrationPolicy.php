<?php

namespace App\Policies;

use App\Models\Registration;
use App\Models\User;

class RegistrationPolicy
{
    public function view(User $user, Registration $registration): bool
    {
        return $registration->team()
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->exists();
    }
}
