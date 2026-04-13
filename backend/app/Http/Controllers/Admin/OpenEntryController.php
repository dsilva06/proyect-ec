<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignOpenEntryCategoryRequest;
use App\Http\Resources\OpenEntryResource;
use App\Models\OpenEntry;
use App\Services\OpenEntryService;
use Illuminate\Http\Request;

class OpenEntryController extends Controller
{
    public function index(Request $request)
    {
        $query = OpenEntry::query()
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
            ->orderByDesc('created_at');

        if ($request->filled('tournament_id')) {
            $query->where('tournament_id', (int) $request->query('tournament_id'));
        }

        if ($request->filled('segment')) {
            $query->where('segment', (string) $request->query('segment'));
        }

        if ($request->filled('paid')) {
            $request->boolean('paid')
                ? $query->whereNotNull('paid_at')
                : $query->whereNull('paid_at');
        }

        if ($request->filled('assigned')) {
            if ($request->boolean('assigned')) {
                $query->where('assignment_status', OpenEntry::ASSIGNMENT_ASSIGNED)
                    ->whereNotNull('registration_id');
            } else {
                $query->where('assignment_status', OpenEntry::ASSIGNMENT_PENDING)
                    ->whereNull('registration_id');
            }
        }

        return OpenEntryResource::collection($query->get());
    }

    public function show(OpenEntry $openEntry)
    {
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

    public function assignCategory(AssignOpenEntryCategoryRequest $request, OpenEntry $openEntry)
    {
        $entry = app(OpenEntryService::class)->assignCategory(
            $openEntry,
            (int) $request->validated()['tournament_category_id'],
            $request->user()
        );

        return new OpenEntryResource($entry);
    }
}
