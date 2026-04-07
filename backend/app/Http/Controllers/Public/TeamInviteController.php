<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamInviteResource;
use App\Models\TeamInvite;
use App\Services\TeamService;
use Illuminate\Http\Request;

class TeamInviteController extends Controller
{
    public function show(Request $request, string $token)
    {
        $invite = TeamInvite::query()
            ->where('token', $token)
            ->with([
                'status',
                'team.status',
                'team.creator',
                'team.users',
                'team.registration.status',
                'team.registration.payments.status',
                'team.registration.tournamentCategory.tournament',
                'team.registration.tournamentCategory.category',
            ])
            ->firstOrFail();

        $invite = app(TeamService::class)->refreshInviteState($invite);

        return new TeamInviteResource($invite);
    }
}
