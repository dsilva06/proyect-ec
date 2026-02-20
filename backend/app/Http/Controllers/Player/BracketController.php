<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Http\Resources\BracketResource;
use App\Models\Bracket;
use App\Support\StatusResolver;
use Illuminate\Http\Request;

class BracketController extends Controller
{
    public function index(Request $request)
    {
        $publishedId = StatusResolver::getId('bracket', 'published');

        $query = Bracket::query()
            ->with([
                'status',
                'slots.registration.team.users.playerProfile',
                'matches.status',
                'matches.registrationA.team',
                'matches.registrationB.team',
                'matches.winnerRegistration.team',
                'tournamentCategory.category',
                'tournamentCategory.tournament.status',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('tournament_category_id')) {
            $query->where('tournament_category_id', $request->query('tournament_category_id'));
        }

        if ($request->filled('tournament_id')) {
            $query->whereHas('tournamentCategory.tournament', function ($builder) use ($request) {
                $builder->where('tournaments.id', $request->query('tournament_id'));
            });
        }

        if ($publishedId) {
            $query->where('status_id', $publishedId);
        }

        return BracketResource::collection($query->get());
    }
}
