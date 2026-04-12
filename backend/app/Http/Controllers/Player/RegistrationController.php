<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Http\Resources\OpenEntryResource;
use App\Http\Requests\Player\StoreRegistrationRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\OpenEntry;
use App\Models\Registration;
use App\Services\OpenEntryService;
use App\Services\RegistrationService;
use App\Services\StripePaymentService;
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
                'payments.status',
                'team.status',
                'team.creator',
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

        if (! empty($data['tournament_id'])) {
            $entry = app(OpenEntryService::class)->create($request->user(), $data);

            return new OpenEntryResource($entry);
        }

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
                'payments.status',
                'team.status',
                'team.creator',
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

    public function pay(Request $request, Registration $registration)
    {
        return response()->json(
            app(StripePaymentService::class)->createCheckoutSession($request->user(), $registration)
        );
    }

    public function payOpenEntry(Request $request, OpenEntry $openEntry)
    {
        return response()->json(
            app(StripePaymentService::class)->createOpenEntryCheckoutSession($request->user(), $openEntry)
        );
    }
}
