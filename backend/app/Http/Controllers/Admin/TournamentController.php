<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTournamentRequest;
use App\Http\Requests\Admin\UpdateTournamentRequest;
use App\Http\Requests\Admin\UpdateTournamentStatusRequest;
use App\Http\Resources\TournamentResource;
use App\Models\Tournament;
use App\Services\StatusService;
use App\Support\StatusResolver;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function index(Request $request)
    {
        $query = Tournament::query()
            ->with(['status', 'categories.category', 'categories.status'])
            ->orderByDesc('start_date');

        if ($request->filled('status')) {
            $status = $request->query('status');
            if (is_numeric($status)) {
                $query->where('status_id', (int) $status);
            } else {
                $statusId = StatusResolver::getId('tournament', $status);
                if ($statusId) {
                    $query->where('status_id', $statusId);
                }
            }
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('start_date', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('end_date', '<=', $request->query('date_to'));
        }

        return TournamentResource::collection($query->get());
    }

    public function store(StoreTournamentRequest $request)
    {
        $this->authorize('create', Tournament::class);
        $data = $request->validated();

        if (! isset($data['status_id'])) {
            $data['status_id'] = StatusResolver::getId('tournament', 'draft');
        } else {
            app(StatusService::class)->validateStatusForModule((int) $data['status_id'], 'tournament');
        }

        $data['created_by'] = $request->user()?->id;

        $tournament = Tournament::create($data);
        $tournament->load(['status', 'categories.category', 'categories.status']);

        return new TournamentResource($tournament);
    }

    public function show(Tournament $tournament)
    {
        $tournament->load(['status', 'categories.category', 'categories.status']);

        return new TournamentResource($tournament);
    }

    public function update(UpdateTournamentRequest $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);
        $data = $request->validated();
        $modeChanged = array_key_exists('mode', $data) && $data['mode'] !== $tournament->mode;
        if (! empty($data['status_id'])) {
            app(StatusService::class)->transition($tournament, 'tournament', (int) $data['status_id'], $request->user()?->id, 'admin_update');
            unset($data['status_id']);
        }

        if ($data) {
            $tournament->update($data);
        }
        if ($modeChanged) {
            $seedingRule = $tournament->mode === 'amateur' ? 'fifo' : 'ranking_desc';
            $tournament->categories()
                ->where('seeding_rule', '!=', 'manual')
                ->update(['seeding_rule' => $seedingRule]);
        }
        $tournament->load(['status', 'categories.category', 'categories.status']);

        return new TournamentResource($tournament);
    }

    public function updateStatus(UpdateTournamentStatusRequest $request, Tournament $tournament)
    {
        $statusId = (int) $request->validated()['status_id'];
        app(StatusService::class)->transition($tournament, 'tournament', $statusId, $request->user()?->id, 'admin_update');

        $tournament->load(['status', 'categories.category', 'categories.status']);

        return new TournamentResource($tournament);
    }

    public function destroy(Tournament $tournament)
    {
        $this->authorize('delete', $tournament);
        $tournament->delete();

        return response()->noContent();
    }
}
