<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamInviteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $registration = $this->team?->registration;
        $latestPayment = $registration?->payments
            ?->sortByDesc('created_at')
            ?->first();

        return [
            'id' => $this->id,
            'token' => $this->token,
            'invited_email' => $this->invited_email,
            'invited_user_id' => $this->invited_user_id,
            'invited_ranking_value' => $this->invited_ranking_value,
            'invited_ranking_source' => $this->invited_ranking_source,
            'captain_name' => $this->team?->creator?->name,
            'captain_email' => $this->team?->creator?->email,
            'tournament_name' => $registration?->tournamentCategory?->tournament?->name,
            'category_name' => $registration?->tournamentCategory?->category?->display_name
                ?: $registration?->tournamentCategory?->category?->name,
            'registration_status_code' => $registration?->status?->code,
            'payment_status_code' => $latestPayment?->status?->code,
            'payment_is_covered' => $registration?->payments?->contains(
                fn ($payment) => $payment->status?->code === 'succeeded'
            ) ?? false,
            'requires_registration' => ! $this->invited_user_id,
            'status' => new StatusResource($this->whenLoaded('status')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'expires_at' => optional($this->expires_at)->toDateTimeString(),
            'email_sent_at' => optional($this->email_sent_at)->toDateTimeString(),
            'email_last_error' => $this->email_last_error,
            'email_attempts' => (int) ($this->email_attempts ?? 0),
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
