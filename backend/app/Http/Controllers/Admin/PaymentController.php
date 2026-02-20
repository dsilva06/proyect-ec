<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::query()
            ->with([
                'status',
                'paidBy',
                'registration.team.users',
                'registration.tournamentCategory.tournament',
                'registration.tournamentCategory.category',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->query('status_id'));
        }

        if ($request->filled('tournament_id')) {
            $tournamentId = $request->query('tournament_id');
            $query->whereHas('registration.tournamentCategory.tournament', function ($builder) use ($tournamentId) {
                $builder->where('tournaments.id', $tournamentId);
            });
        }

        return PaymentResource::collection($query->get());
    }

    public function update(UpdatePaymentRequest $request, Payment $payment)
    {
        $payment = app(PaymentService::class)->updatePayment(
            $payment,
            $request->validated(),
            $request->user()
        );
        $payment->load([
            'status',
            'paidBy',
            'registration.team.users',
            'registration.tournamentCategory.tournament',
            'registration.tournamentCategory.category',
        ]);

        return new PaymentResource($payment);
    }
}
