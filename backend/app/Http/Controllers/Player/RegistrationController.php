<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Http\Requests\Player\StoreRegistrationRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\Registration;
use App\Services\RegistrationService;
use App\Services\TeamService;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $registrations = Registration::query()
            ->whereHas('team.users', fn ($query) => $query->where('users.id', $user->id))
            ->with([
                'status',
                'team.users.playerProfile',
                'rankings.user.playerProfile',
                'rankings.verifier',
                'tournamentCategory.tournament.status',
                'tournamentCategory.category',
            ])
            ->orderByDesc('created_at')
            ->get();

        return RegistrationResource::collection($registrations);
    }

    public function store(StoreRegistrationRequest $request)
    {
        $data = $request->validated();

        if (! empty($data['team_id'])) {
            $registration = app(RegistrationService::class)
                ->create(
                    $request->user(),
                    (int) $data['team_id'],
                    (int) $data['tournament_category_id'],
                    $data
                );

            $registration->load([
                'status',
                'team.users.playerProfile',
                'rankings.user.playerProfile',
                'rankings.verifier',
                'tournamentCategory.tournament.status',
                'tournamentCategory.category',
            ]);
        } else {
            $registration = app(TeamService::class)->createPendingTeamForTournament(
                $request->user(),
                (int) $data['tournament_category_id'],
                (string) $data['partner_email'],
                $data
            );
        }

        return new RegistrationResource($registration);
    }
}
