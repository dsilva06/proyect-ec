<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WildcardInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournament_category_id' => $this->tournament_category_id,
            'tournament_category' => new TournamentCategoryResource($this->whenLoaded('tournamentCategory')),
            'email' => $this->email,
            'partner_email' => $this->partner_email,
            'partner_name' => $this->partner_name,
            'wildcard_fee_waived' => (bool) $this->wildcard_fee_waived,
            'purpose' => $this->purpose,
            'status' => new StatusResource($this->whenLoaded('status')),
            'token' => $this->token,
            'expires_at' => optional($this->expires_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
