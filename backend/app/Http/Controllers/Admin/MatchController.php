<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Models\TournamentMatch;
use App\Services\MatchService;
use App\Services\MatchScheduleService;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        $query = TournamentMatch::query()
            ->with([
                'status',
                'registrationA.team',
                'registrationB.team',
                'winnerRegistration.team',
                'tournamentCategory.category',
                'tournamentCategory.tournament.status',
            ])
            ->orderByDesc('scheduled_at');

        if ($request->filled('tournament_category_id')) {
            $query->where('tournament_category_id', $request->query('tournament_category_id'));
        }

        if ($request->filled('tournament_id')) {
            $tournamentId = $request->query('tournament_id');
            $query->whereHas('tournamentCategory.tournament', function ($builder) use ($tournamentId) {
                $builder->where('tournaments.id', $tournamentId);
            });
        }

        if ($request->filled('category_id')) {
            $categoryId = $request->query('category_id');
            $query->whereHas('tournamentCategory.category', function ($builder) use ($categoryId) {
                $builder->where('categories.id', $categoryId);
            });
        }

        return MatchResource::collection($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tournament_category_id' => ['required', 'exists:tournament_categories,id'],
            'bracket_id' => ['nullable', 'exists:brackets,id'],
            'round_number' => ['required', 'integer', 'min:1'],
            'match_number' => ['required', 'integer', 'min:1'],
            'registration_a_id' => ['nullable', 'exists:registrations,id'],
            'registration_b_id' => ['nullable', 'exists:registrations,id'],
            'status_id' => ['required', 'exists:statuses,id'],
            'scheduled_at' => ['nullable', 'date'],
            'not_before_at' => ['nullable', 'date'],
            'court' => ['nullable', 'string', 'max:50'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:10'],
            'score_json' => ['nullable', 'array'],
            'winner_registration_id' => ['nullable', 'exists:registrations,id'],
        ]);

        $match = app(MatchService::class)->create($data, $request->user()?->id);
        $match->load([
            'status',
            'registrationA.team',
            'registrationB.team',
            'winnerRegistration.team',
            'tournamentCategory.category',
            'tournamentCategory.tournament.status',
        ]);

        return new MatchResource($match);
    }

    public function update(Request $request, TournamentMatch $match)
    {
        $data = $request->validate([
            'round_number' => ['sometimes', 'integer', 'min:1'],
            'match_number' => ['sometimes', 'integer', 'min:1'],
            'registration_a_id' => ['nullable', 'exists:registrations,id'],
            'registration_b_id' => ['nullable', 'exists:registrations,id'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'scheduled_at' => ['nullable', 'date'],
            'not_before_at' => ['nullable', 'date'],
            'court' => ['nullable', 'string', 'max:50'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:10'],
            'score_json' => ['nullable', 'array'],
            'winner_registration_id' => ['nullable', 'exists:registrations,id'],
        ]);

        $match = app(MatchService::class)->update($match, $data, $request->user()?->id);
        $match->load([
            'status',
            'registrationA.team',
            'registrationB.team',
            'winnerRegistration.team',
            'tournamentCategory.category',
            'tournamentCategory.tournament.status',
        ]);

        return new MatchResource($match);
    }

    public function delay(TournamentMatch $match)
    {
        app(MatchScheduleService::class)->delay($match);

        $match->load([
            'status',
            'registrationA.team',
            'registrationB.team',
            'winnerRegistration.team',
            'tournamentCategory.category',
            'tournamentCategory.tournament.status',
        ]);

        return new MatchResource($match);
    }

    public function destroy(TournamentMatch $match)
    {
        app(MatchService::class)->delete($match);

        return response()->noContent();
    }
}
