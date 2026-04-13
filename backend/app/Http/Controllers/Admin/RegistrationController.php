<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRegistrationRankingsRequest;
use App\Http\Requests\Admin\UpdateRegistrationRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\Registration;
use App\Services\RegistrationService;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function index(Request $request)
    {
        $query = Registration::query()
            ->with([
                'status',
                'team.users.playerProfile',
                'rankings.user.playerProfile',
                'rankings.verifier',
                'openEntry',
                'tournamentCategory.tournament.status',
                'tournamentCategory.category',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('tournament_id')) {
            $tournamentId = $request->query('tournament_id');
            $query->whereHas('tournamentCategory.tournament', function ($builder) use ($tournamentId) {
                $builder->where('tournaments.id', $tournamentId);
            });
        }

        if ($request->filled('category_id')) {
            $query->whereHas('tournamentCategory.category', function ($builder) use ($request) {
                $builder->where('categories.id', $request->query('category_id'));
            });
        }

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->query('status_id'));
        }

        return RegistrationResource::collection($query->get());
    }

    public function update(UpdateRegistrationRequest $request, Registration $registration)
    {
        $registration = app(RegistrationService::class)->updateFromAdmin(
            $registration,
            $request->validated(),
            $request->user()
        );
        $registration->load([
            'status',
            'team.users.playerProfile',
            'rankings.user.playerProfile',
            'rankings.verifier',
            'openEntry',
            'tournamentCategory.tournament.status',
            'tournamentCategory.category',
        ]);

        return new RegistrationResource($registration);
    }

    public function updateRankings(UpdateRegistrationRankingsRequest $request, Registration $registration)
    {
        $registration = app(RegistrationService::class)->updateRankingsFromAdmin(
            $registration,
            $request->validated()['rankings'],
            $request->user()
        );

        return new RegistrationResource($registration);
    }
}
