<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTournamentCategoryRequest;
use App\Http\Requests\Admin\UpdateTournamentCategoryRequest;
use App\Http\Resources\TournamentCategoryResource;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Services\AcceptanceService;
use App\Services\TournamentDeletionService;

class TournamentCategoryController extends Controller
{
    public function store(StoreTournamentCategoryRequest $request, Tournament $tournament)
    {
        $data = $request->validated();
        $data['tournament_id'] = $tournament->id;
        $data['entry_fee_amount'] = array_key_exists('entry_fee_amount', $data)
            ? (int) $data['entry_fee_amount']
            : (int) ($tournament->entry_fee_amount ?? 0);
        $data['currency'] = $data['currency'] ?? (string) ($tournament->entry_fee_currency ?: 'EUR');
        if (! isset($data['acceptance_type'])) {
            $data['acceptance_type'] = 'waitlist';
        }
        if (! isset($data['seeding_rule'])) {
            $data['seeding_rule'] = in_array($tournament->mode, ['amateur', 'open'], true) ? 'fifo' : 'ranking_desc';
        }

        $category = TournamentCategory::create($data);
        $category->load(['category', 'status']);

        return new TournamentCategoryResource($category);
    }

    public function update(UpdateTournamentCategoryRequest $request, TournamentCategory $tournamentCategory)
    {
        $tournamentCategory->update($request->validated());
        $tournamentCategory->load(['category', 'status']);

        app(AcceptanceService::class)->recalculateForTournamentCategory($tournamentCategory->id);

        return new TournamentCategoryResource($tournamentCategory);
    }

    public function destroy(TournamentCategory $tournamentCategory)
    {
        app(TournamentDeletionService::class)->deleteTournamentCategory($tournamentCategory);

        return response()->noContent();
    }
}
