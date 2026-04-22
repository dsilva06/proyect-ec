<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\OpenEntry;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StripePaymentService
{
    public function __construct(
        protected StripeCheckoutGateway $gateway,
        protected StatusService $statusService,
        protected PaymentService $paymentService,
        protected TeamService $teamService
    ) {
    }

    public function createCheckoutSession(User $actor, Registration|int $registration): array
    {
        if (! $this->gateway->isConfigured()) {
            throw ValidationException::withMessages([
                'payment' => 'Stripe no está configurado todavía.',
            ]);
        }

        $registrationModel = $registration instanceof Registration
            ? $registration->loadMissing(['status', 'payments.status', 'team.status', 'tournamentCategory.tournament', 'tournamentCategory.category'])
            : Registration::query()
                ->with(['status', 'payments.status', 'team.status', 'tournamentCategory.tournament', 'tournamentCategory.category'])
                ->findOrFail((int) $registration);

        if ((int) ($registrationModel->team?->created_by ?? 0) !== (int) $actor->id) {
            throw new AuthorizationException('No autorizado para pagar esta inscripción.');
        }

        $statusCode = (string) ($registrationModel->status?->code ?? '');
        if (in_array($statusCode, ['cancelled', 'expired', 'rejected', 'paid'], true)) {
            throw ValidationException::withMessages([
                'payment' => 'Esta inscripción no admite un nuevo cobro.',
            ]);
        }

        if (in_array($statusCode, ['pending', 'waitlisted'], true)) {
            throw ValidationException::withMessages([
                'payment' => 'Esta inscripción todavía no está lista para pago.',
            ]);
        }

        $hasSuccessfulPayment = $registrationModel->payments->contains(
            fn (Payment $payment) => $payment->status?->code === 'succeeded'
        );

        if ($hasSuccessfulPayment) {
            throw ValidationException::withMessages([
                'payment' => 'El pago del equipo ya fue completado.',
            ]);
        }

        $existingPendingPayment = $registrationModel->payments
            ->sortByDesc('created_at')
            ->first(function (Payment $payment) {
                return $payment->provider === 'stripe_checkout'
                    && $payment->status?->code === 'pending'
                    && filled($payment->raw_payload['checkout_url'] ?? null);
            });

        if ($existingPendingPayment) {
            return [
                'checkout_url' => (string) ($existingPendingPayment->raw_payload['checkout_url'] ?? ''),
                'session_id' => (string) $existingPendingPayment->provider_intent_id,
            ];
        }

        $amountCents = max(0, (int) ($registrationModel->tournamentCategory?->entry_fee_amount ?? $registrationModel->tournamentCategory?->tournament?->entry_fee_amount ?? 0)) * 100;
        $currency = strtolower((string) ($registrationModel->tournamentCategory?->currency ?: $registrationModel->tournamentCategory?->tournament?->entry_fee_currency ?: 'eur'));
        $tournamentName = (string) ($registrationModel->tournamentCategory?->tournament?->name ?: 'ESTARS PADEL TOUR');
        $categoryName = (string) (
            $registrationModel->tournamentCategory?->category?->display_name
            ?: $registrationModel->tournamentCategory?->category?->name
            ?: 'Categoria'
        );
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        $session = $this->gateway->createCheckoutSession([
            'mode' => 'payment',
            'client_reference_id' => (string) $registrationModel->id,
            'customer_email' => $actor->email,
            'success_url' => $frontendUrl.'/player/registrations?checkout=success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $frontendUrl.'/player/registrations?checkout=cancelled&registration_id='.$registrationModel->id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amountCents,
                    'product_data' => [
                        'name' => "{$tournamentName} · {$categoryName}",
                        'description' => 'Pago único por inscripción del equipo',
                    ],
                ],
            ]],
            'metadata' => [
                'registration_id' => (string) $registrationModel->id,
                'captain_user_id' => (string) $actor->id,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'registration_id' => (string) $registrationModel->id,
                    'captain_user_id' => (string) $actor->id,
                ],
            ],
        ]);

        DB::transaction(function () use ($registrationModel, $actor, $session, $amountCents, $currency): void {
            Payment::query()->create([
                'registration_id' => $registrationModel->id,
                'provider' => 'stripe_checkout',
                'provider_intent_id' => (string) $session['id'],
                'amount_cents' => $amountCents,
                'currency' => strtoupper($currency),
                'status_id' => $this->statusService->resolveStatusId('payment', 'pending'),
                'paid_by_user_id' => $actor->id,
                'raw_payload' => [
                    'checkout_url' => $session['url'],
                    'checkout_status' => $session['status'],
                    'checkout_payment_status' => $session['payment_status'],
                    'payment_intent' => $session['payment_intent'],
                ],
            ]);

            if ($registrationModel->status?->code === 'accepted') {
                $this->statusService->transition(
                    $registrationModel,
                    'registration',
                    $this->statusService->resolveStatusId('registration', 'payment_pending'),
                    $actor->id,
                    'stripe_checkout_started'
                );
            }
        });

        return [
            'checkout_url' => (string) $session['url'],
            'session_id' => (string) $session['id'],
        ];
    }

    public function createOpenEntryCheckoutSession(User $actor, OpenEntry|int $openEntry): array
    {
        if (! $this->gateway->isConfigured()) {
            throw ValidationException::withMessages([
                'payment' => 'Stripe no está configurado todavía.',
            ]);
        }

        $openEntryModel = $openEntry instanceof OpenEntry
            ? $openEntry->loadMissing(['payments.status', 'team.status', 'team.creator', 'tournament.status'])
            : OpenEntry::query()
                ->with(['payments.status', 'team.status', 'team.creator', 'tournament.status'])
                ->findOrFail((int) $openEntry);

        if ((int) $openEntryModel->submitted_by_user_id !== (int) $actor->id) {
            throw new AuthorizationException('No autorizado para pagar esta inscripción OPEN.');
        }

        if ($openEntryModel->registration_id) {
            throw ValidationException::withMessages([
                'payment' => 'Esta inscripción OPEN ya fue asignada a una categoría.',
            ]);
        }

        if ($openEntryModel->paid_at) {
            throw ValidationException::withMessages([
                'payment' => 'El pago del equipo ya fue completado.',
            ]);
        }

        $hasSuccessfulPayment = $openEntryModel->payments->contains(
            fn (Payment $payment) => $payment->status?->code === 'succeeded'
        );

        if ($hasSuccessfulPayment) {
            throw ValidationException::withMessages([
                'payment' => 'El pago del equipo ya fue completado.',
            ]);
        }

        $existingPendingPayment = $openEntryModel->payments
            ->sortByDesc('created_at')
            ->first(function (Payment $payment) {
                return $payment->provider === 'stripe_checkout'
                    && $payment->status?->code === 'pending'
                    && filled($payment->raw_payload['checkout_url'] ?? null);
            });

        if ($existingPendingPayment) {
            return [
                'checkout_url' => (string) ($existingPendingPayment->raw_payload['checkout_url'] ?? ''),
                'session_id' => (string) $existingPendingPayment->provider_intent_id,
            ];
        }

        $amountCents = max(0, (int) ($openEntryModel->tournament?->entry_fee_amount ?? 0)) * 100;
        $currency = strtolower((string) ($openEntryModel->tournament?->entry_fee_currency ?: 'eur'));
        $tournamentName = (string) ($openEntryModel->tournament?->name ?: 'ESTARS PADEL TOUR');
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        $session = $this->gateway->createCheckoutSession([
            'mode' => 'payment',
            'client_reference_id' => 'open-entry-'.$openEntryModel->id,
            'customer_email' => $actor->email,
            'success_url' => $frontendUrl.'/player/registrations?checkout=success&session_id={CHECKOUT_SESSION_ID}&open_entry_id='.$openEntryModel->id,
            'cancel_url' => $frontendUrl.'/player/registrations?checkout=cancelled&open_entry_id='.$openEntryModel->id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amountCents,
                    'product_data' => [
                        'name' => "{$tournamentName} · OPEN",
                        'description' => 'Pago único por ingreso al torneo OPEN',
                    ],
                ],
            ]],
            'metadata' => [
                'open_entry_id' => (string) $openEntryModel->id,
                'captain_user_id' => (string) $actor->id,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'open_entry_id' => (string) $openEntryModel->id,
                    'captain_user_id' => (string) $actor->id,
                ],
            ],
        ]);

        DB::transaction(function () use ($openEntryModel, $actor, $session, $amountCents, $currency): void {
            Payment::query()->create([
                'registration_id' => null,
                'open_entry_id' => $openEntryModel->id,
                'provider' => 'stripe_checkout',
                'provider_intent_id' => (string) $session['id'],
                'amount_cents' => $amountCents,
                'currency' => strtoupper($currency),
                'status_id' => $this->statusService->resolveStatusId('payment', 'pending'),
                'paid_by_user_id' => $actor->id,
                'raw_payload' => [
                    'checkout_url' => $session['url'],
                    'checkout_status' => $session['status'],
                    'checkout_payment_status' => $session['payment_status'],
                    'payment_intent' => $session['payment_intent'],
                ],
            ]);
        });

        return [
            'checkout_url' => (string) $session['url'],
            'session_id' => (string) $session['id'],
        ];
    }

    public function handleWebhook(array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        $session = is_array($event['data'] ?? null) ? $event['data'] : [];

        if (! in_array($type, [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'checkout.session.async_payment_failed',
            'checkout.session.expired',
        ], true)) {
            return;
        }

        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            if (($session['payment_status'] ?? null) === 'paid' || $type === 'checkout.session.async_payment_succeeded') {
                $this->markPaymentSucceeded($session);
            }
            return;
        }

        if ($type === 'checkout.session.async_payment_failed') {
            $this->markPaymentAsTerminal($session, 'failed');
            return;
        }

        if ($type === 'checkout.session.expired') {
            $this->markPaymentAsTerminal($session, 'cancelled');
        }
    }

    private function markPaymentSucceeded(array $session): void
    {
        $sessionId = (string) ($session['id'] ?? '');
        $registrationId = (int) ($session['metadata']['registration_id'] ?? 0);
        $openEntryId = (int) ($session['metadata']['open_entry_id'] ?? 0);

        DB::transaction(function () use ($sessionId, $registrationId, $openEntryId, $session): void {
            $payment = Payment::query()
                ->where('provider_intent_id', $sessionId)
                ->with(['status', 'registration.team.status', 'openEntry.team.status'])
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                if ($openEntryId > 0) {
                    $openEntry = OpenEntry::query()->findOrFail($openEntryId);
                    $payment = Payment::query()->create([
                        'registration_id' => null,
                        'open_entry_id' => $openEntry->id,
                        'provider' => 'stripe_checkout',
                        'provider_intent_id' => $sessionId,
                        'amount_cents' => (int) ($session['amount_total'] ?? 0),
                        'currency' => strtoupper((string) ($session['currency'] ?? 'USD')),
                        'status_id' => $this->statusService->resolveStatusId('payment', 'pending'),
                        'paid_by_user_id' => isset($session['metadata']['captain_user_id'])
                            ? (int) $session['metadata']['captain_user_id']
                            : null,
                        'raw_payload' => $session,
                    ]);
                } else {
                    $registration = Registration::query()->findOrFail($registrationId);
                    $payment = Payment::query()->create([
                        'registration_id' => $registration->id,
                        'open_entry_id' => null,
                        'provider' => 'stripe_checkout',
                        'provider_intent_id' => $sessionId,
                        'amount_cents' => (int) ($session['amount_total'] ?? 0),
                        'currency' => strtoupper((string) ($session['currency'] ?? 'USD')),
                        'status_id' => $this->statusService->resolveStatusId('payment', 'pending'),
                        'paid_by_user_id' => isset($session['metadata']['captain_user_id'])
                            ? (int) $session['metadata']['captain_user_id']
                            : null,
                        'raw_payload' => $session,
                    ]);
                }
            }

            if ($payment->status?->code !== 'succeeded') {
                $this->paymentService->updatePayment($payment, [
                    'status_id' => $this->statusService->resolveStatusId('payment', 'succeeded'),
                    'raw_payload' => $session,
                ]);
            }

            if ($payment->registration_id) {
                $this->teamService->finalizeRegistrationAfterSuccessfulPayment((int) $payment->registration_id);
            }
        });
    }

    private function markPaymentAsTerminal(array $session, string $statusCode): void
    {
        $sessionId = (string) ($session['id'] ?? '');

        DB::transaction(function () use ($sessionId, $session, $statusCode): void {
            $payment = Payment::query()
                ->where('provider_intent_id', $sessionId)
                ->with(['status', 'registration.status'])
                ->lockForUpdate()
                ->first();

            if (! $payment || in_array((string) $payment->status?->code, ['succeeded', 'failed', 'cancelled'], true)) {
                return;
            }

            $this->paymentService->updatePayment($payment, [
                'status_id' => $this->statusService->resolveStatusId('payment', $statusCode),
                'failure_code' => $statusCode,
                'failure_message' => $statusCode === 'failed'
                    ? 'Stripe no pudo completar el pago.'
                    : 'La sesión de pago expiró antes de completarse.',
                'raw_payload' => $session,
            ]);

            $registration = $payment->registration;
            if ($registration && $registration->status?->code === 'payment_pending') {
                $this->statusService->transition(
                    $registration,
                    'registration',
                    $this->statusService->resolveStatusId('registration', 'accepted'),
                    null,
                    'stripe_checkout_'.$statusCode
                );
            }
        });
    }
}
