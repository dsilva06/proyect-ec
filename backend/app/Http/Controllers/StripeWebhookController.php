<?php

namespace App\Http\Controllers;

use App\Services\StripeCheckoutGateway;
use App\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        StripeCheckoutGateway $gateway,
        StripePaymentService $stripePaymentService
    ): JsonResponse {
        try {
            $event = $gateway->constructWebhookEvent(
                (string) $request->getContent(),
                $request->header('Stripe-Signature')
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response()->json([
                'message' => 'Invalid Stripe webhook signature.',
            ], 400);
        }

        $stripePaymentService->handleWebhook($event);

        return response()->json([
            'received' => true,
        ]);
    }
}
