<?php

namespace App\Services;

use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeCheckoutGateway
{
    public const API_VERSION = '2026-02-25.clover';

    public function isConfigured(): bool
    {
        return filled((string) config('services.stripe.secret'))
            && filled((string) config('services.stripe.webhook_secret'));
    }

    public function createCheckoutSession(array $payload): array
    {
        $session = $this->client()->checkout->sessions->create($payload);

        return [
            'id' => (string) $session->id,
            'url' => (string) $session->url,
            'status' => (string) $session->status,
            'payment_status' => (string) $session->payment_status,
            'payment_intent' => is_string($session->payment_intent) ? $session->payment_intent : null,
        ];
    }

    public function constructWebhookEvent(string $payload, ?string $signature): array
    {
        $secret = (string) config('services.stripe.webhook_secret');

        $event = Webhook::constructEvent($payload, (string) $signature, $secret);

        return [
            'id' => (string) $event->id,
            'type' => (string) $event->type,
            'data' => json_decode(json_encode($event->data->object, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    private function client(): StripeClient
    {
        return new StripeClient([
            'api_key' => (string) config('services.stripe.secret'),
            'stripe_version' => self::API_VERSION,
        ]);
    }
}
