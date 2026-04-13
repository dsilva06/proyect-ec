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
                'openEntry',
                'tournamentCategory.tournament.status',
                'tournamentCategory.category',
            ])
            ->orderByDesc('created_at')
            ->get();

        return RegistrationResource::collection($registrations);
    }

    public function indexOpenEntries(Request $request)
    {
        $entries = OpenEntry::query()
            ->where('submitted_by_user_id', $request->user()->id)
            ->with([
                'tournament.status',
                'team.status',
                'team.creator',
                'team.users.playerProfile',
                'payments.status',
                'payments.paidBy',
                'submittedBy.playerProfile',
                'assignedTournamentCategory.category',
                'assignedTournamentCategory.tournament.status',
                'registration.status',
                'registration.openEntry',
                'registration.team.users.playerProfile',
                'registration.rankings.user.playerProfile',
                'assignedBy.playerProfile',
            ])
            ->orderByDesc('created_at')
            ->get();

        return OpenEntryResource::collection($entries);
    }

    public function showOpenEntry(Request $request, OpenEntry $openEntry)
    {
        abort_unless((int) $openEntry->submitted_by_user_id === (int) $request->user()->id, 404);

        $openEntry->load([
            'tournament.status',
            'team.status',
            'team.creator',
            'team.users.playerProfile',
            'payments.status',
            'payments.paidBy',
            'submittedBy.playerProfile',
            'assignedTournamentCategory.category',
            'assignedTournamentCategory.tournament.status',
            'registration.status',
            'registration.openEntry',
            'registration.team.users.playerProfile',
            'registration.rankings.user.playerProfile',
            'assignedBy.playerProfile',
        ]);

        return new OpenEntryResource($openEntry);
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
