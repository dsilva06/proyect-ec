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
        return [
            'id' => $this->id,
            'invited_email' => $this->invited_email,
            'invited_ranking_value' => $this->invited_ranking_value,
            'invited_ranking_source' => $this->invited_ranking_source,
            'status' => new StatusResource($this->whenLoaded('status')),
            'team' => new TeamResource($this->whenLoaded('team')),
            'expires_at' => optional($this->expires_at)->toDateTimeString(),
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
