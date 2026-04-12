<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registration_id' => $this->registration_id,
            'registration' => new RegistrationResource($this->whenLoaded('registration')),
            'open_entry_id' => $this->open_entry_id,
            'open_entry' => new OpenEntryResource($this->whenLoaded('openEntry')),
            'provider' => $this->provider,
            'provider_intent_id' => $this->provider_intent_id,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'status' => new StatusResource($this->whenLoaded('status')),
            'paid_by' => new UserResource($this->whenLoaded('paidBy')),
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'failure_code' => $this->failure_code,
            'failure_message' => $this->failure_message,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
