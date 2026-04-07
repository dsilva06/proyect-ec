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
            ->whereHas('registration.team.users', fn ($query) => $query->where('users.id', $user->id))
            ->with([
                'status',
                'paidBy',
                'registration.status',
                'registration.team',
                'registration.tournamentCategory.tournament',
                'registration.tournamentCategory.category',
            ])
            ->orderByDesc('created_at')
            ->get();

        return PaymentResource::collection($payments);
    }
}
