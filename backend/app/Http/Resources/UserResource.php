<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->whenLoaded('playerProfile');
        if ($profile instanceof MissingValue) {
            $profile = $this->playerProfile;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'is_active' => (bool) $this->is_active,
            'ranking_source' => $profile?->ranking_source,
            'ranking_value' => $profile?->ranking_value,
            'ranking_updated_at' => optional($profile?->ranking_updated_at)->toIso8601String(),
            'ranking_verified_at' => optional($profile?->ranking_updated_at)->toIso8601String(),
            'player_profile' => $profile ? [
                'first_name' => $profile->first_name,
                'last_name' => $profile->last_name,
                'dni' => $profile->dni,
                'province_state' => $profile->province_state,
                'ranking_source' => $profile->ranking_source,
                'ranking_value' => $profile->ranking_value,
                'ranking_updated_at' => optional($profile->ranking_updated_at)->toIso8601String(),
            ] : null,
        ];
    }
}
