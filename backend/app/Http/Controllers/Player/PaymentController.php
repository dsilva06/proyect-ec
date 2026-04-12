<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $payments = Payment::query()
            ->where(function ($query) use ($user) {
                $query->whereHas('registration.team.users', fn ($subQuery) => $subQuery->where('users.id', $user->id))
                    ->orWhereHas('openEntry', function ($subQuery) use ($user) {
                        $subQuery->where('submitted_by_user_id', $user->id)
                            ->orWhereHas('team.users', fn ($teamUsersQuery) => $teamUsersQuery->where('users.id', $user->id));
                    });
            })
            ->with([
                'status',
                'paidBy',
                'registration.status',
                'registration.team',
                'registration.tournamentCategory.tournament',
                'registration.tournamentCategory.category',
                'openEntry.tournament.status',
                'openEntry.team.status',
                'openEntry.team.users.playerProfile',
                'openEntry.submittedBy.playerProfile',
            ])
            ->orderByDesc('created_at')
            ->get();

        return PaymentResource::collection($payments);
    }
}
