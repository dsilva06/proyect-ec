<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BracketResource;
use App\Models\Bracket;
use App\Services\BracketGenerationService;
use App\Services\StatusService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BracketController extends Controller
{
    public function index(Request $request)
    {
        $query = Bracket::query()
            ->with([
                'status',
                'slots.registration.team',
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
            $tournamentId = $request->query('tournament_id');
            $query->whereHas('tournamentCategory', function ($builder) use ($tournamentId) {
                $builder->where('tournament_id', $tournamentId);
            });
        }

        if ($request->filled('category_id')) {
            $categoryId = $request->query('category_id');
            $query->whereHas('tournamentCategory', function ($builder) use ($categoryId) {
                $builder->where('category_id', $categoryId);
            });
        }

        return BracketResource::collection($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tournament_category_id' => ['required', 'exists:tournament_categories,id'],
            'status_id' => ['required', 'exists:statuses,id'],
            'published_at' => ['nullable', 'date'],
        ]);

        $data['type'] = Bracket::TYPE_SINGLE_ELIMINATION;
        app(StatusService::class)->validateStatusForModule((int) $data['status_id'], 'bracket');

        $bracket = Bracket::create($data);
        $bracket->load([
            'status',
            'slots.registration.team',
            'matches.status',
            'matches.registrationA.team',
            'matches.registrationB.team',
            'matches.winnerRegistration.team',
            'tournamentCategory.category',
            'tournamentCategory.tournament.status',
        ]);

        return new BracketResource($bracket);
    }

    public function update(Request $request, Bracket $bracket)
    {
        $data = $request->validate([
            'status_id' => ['nullable', 'exists:statuses,id'],
            'published_at' => ['nullable', 'date'],
        ]);

        if (! empty($data['status_id'])) {
            app(StatusService::class)->transition($bracket, 'bracket', (int) $data['status_id'], $request->user()?->id, 'admin_update');
            unset($data['status_id']);
        }

        if ($data) {
            $bracket->update($data);
        }
        $bracket->load([
            'status',
            'slots.registration.team',
            'matches.status',
            'matches.registrationA.team',
            'matches.registrationB.team',
            'matches.winnerRegistration.team',
            'tournamentCategory.category',
            'tournamentCategory.tournament.status',
        ]);

        return new BracketResource($bracket);
    }

    public function generate(Request $request, Bracket $bracket)
    {
        $bracket = app(BracketGenerationService::class)->generate($bracket);

        return new BracketResource($bracket);
    }

    public function destroy(Bracket $bracket)
    {
        $bracket->loadMissing('status');
        if ($bracket->status?->code !== 'draft') {
            throw ValidationException::withMessages([
                'bracket' => 'Solo puedes eliminar cuadros en estado borrador.',
            ]);
        }

        $bracket->delete();

        return response()->noContent();
    }
}
