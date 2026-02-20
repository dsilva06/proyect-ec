<?php

namespace App\Policies;

use App\Models\Tournament;
use App\Models\User;

class TournamentPolicy
{
    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Tournament $tournament): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, Tournament $tournament): bool
    {
        return $user->role === 'admin';
    }
}
