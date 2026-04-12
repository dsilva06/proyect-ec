<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(protected StatusService $statusService)
    {
    }

    public function updatePayment(Payment $payment, array $data, ?User $actor = null): Payment
    {
        if (array_key_exists('status_id', $data) && $data['status_id']) {
            $status = $this->statusService->validateStatusForModule((int) $data['status_id'], 'payment');

            if ($status->code === 'succeeded') {
                $registration = $payment->registration;
                $openEntry = $payment->openEntry;
                $exists = $registration
                    ? $registration->payments()
                        ->where('payments.id', '!=', $payment->id)
                        ->whereHas('status', fn ($sub) => $sub->where('code', 'succeeded'))
                        ->exists()
                    : false;

                if (! $exists && $openEntry) {
                    $exists = $openEntry->payments()
                        ->where('payments.id', '!=', $payment->id)
                        ->whereHas('status', fn ($sub) => $sub->where('code', 'succeeded'))
                        ->exists();
                }

                if ($exists) {
                    throw ValidationException::withMessages([
                        'status_id' => 'Ya existe un pago exitoso para esta inscripción.',
                    ]);
                }
            }

            $this->statusService->transition(
                $payment,
                'payment',
                (int) $data['status_id'],
                $actor?->id,
                'admin_update'
            );

            if ($status->code === 'succeeded' && ! $payment->paid_at) {
                $payment->paid_at = now();
            }

            if ($status->code === 'succeeded' && $registration) {
                if (! $registration->accepted_at) {
                    $registration->accepted_at = now();
                }
                $registration->payment_due_at = null;
                $nextStatusId = $this->statusService->resolveStatusId('registration', 'paid');

                if ((int) $registration->status_id !== (int) $nextStatusId) {
                    $this->statusService->transition(
                        $registration,
                        'registration',
                        $nextStatusId,
                        $actor?->id,
                        'payment_succeeded'
                    );
                } else {
                    $registration->save();
                }
            }

            if ($status->code === 'succeeded' && $openEntry) {
                if (! $openEntry->paid_at) {
                    $openEntry->paid_at = $payment->paid_at ?: now();
                    $openEntry->save();
                }
            }

            unset($data['status_id']);
        }

        if ($data) {
            $payment->fill($data);
            $payment->save();
        }

        return $payment->fresh();
    }
}
