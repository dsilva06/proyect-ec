<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationRankingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slot' => $this->slot,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'invited_email' => $this->invited_email,
            'ranking_value' => $this->ranking_value,
            'ranking_source' => $this->ranking_source,
            'is_verified' => (bool) $this->is_verified,
            'verified_at' => optional($this->verified_at)->toIso8601String(),
            'verified_by_user_id' => $this->verified_by_user_id,
            'verifier' => new UserResource($this->whenLoaded('verifier')),
        ];
    }
}
