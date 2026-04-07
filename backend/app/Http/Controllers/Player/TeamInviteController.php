<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamInviteResource;
use App\Models\TeamInvite;
use App\Services\TeamService;
use Illuminate\Http\Request;

class TeamInviteController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $invites = TeamInvite::query()
            ->with(['team.users', 'status'])
            ->where(function ($query) use ($user) {
                $query->where('invited_user_id', $user->id)
                    ->orWhere(function ($inner) use ($user) {
                        $inner->whereNull('invited_user_id')
                            ->where('invited_email', $user->email);
                    });
            })
            ->orderByDesc('created_at')
            ->get();

        $teamService = app(TeamService::class);
        $invites = $invites->map(fn (TeamInvite $invite) => $teamService->refreshInviteState($invite->load([
            'status',
            'team.status',
            'team.creator',
            'team.users',
            'team.registration.status',
            'team.registration.payments.status',
            'team.registration.tournamentCategory.tournament',
            'team.registration.tournamentCategory.category',
        ])));

        return TeamInviteResource::collection($invites);
    }

    public function claim(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $invite = app(TeamService::class)->claimInvite($request->user(), $data['token']);

        return new TeamInviteResource($invite);
    }

    public function accept(Request $request, TeamInvite $teamInvite)
    {
        $invite = app(TeamService::class)->acceptInvite($request->user(), $teamInvite);

        return new TeamInviteResource($invite->load([
            'status',
            'team.status',
            'team.creator',
            'team.users',
            'team.registration.status',
            'team.registration.payments.status',
            'team.registration.tournamentCategory.tournament',
            'team.registration.tournamentCategory.category',
        ]));
    }

    public function reject(Request $request, TeamInvite $teamInvite)
    {
        $invite = app(TeamService::class)->rejectInvite($request->user(), $teamInvite);

        return new TeamInviteResource($invite->load([
            'status',
            'team.status',
            'team.creator',
            'team.users',
            'team.registration.status',
            'team.registration.payments.status',
            'team.registration.tournamentCategory.tournament',
            'team.registration.tournamentCategory.category',
        ]));
    }

    public function resend(Request $request, TeamInvite $teamInvite)
    {
        $invite = app(TeamService::class)->resendInvite($request->user(), $teamInvite);

        return new TeamInviteResource($invite->load([
            'status',
            'team.status',
            'team.creator',
            'team.users',
            'team.registration.status',
            'team.registration.payments.status',
            'team.registration.tournamentCategory.tournament',
            'team.registration.tournamentCategory.category',
        ]));
    }
}
