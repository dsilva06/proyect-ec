<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
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
            'bracket_id' => $this->bracket_id,
            'round_number' => $this->round_number,
            'match_number' => $this->match_number,
            'registration_a' => new RegistrationResource($this->whenLoaded('registrationA')),
            'registration_b' => new RegistrationResource($this->whenLoaded('registrationB')),
            'status' => new StatusResource($this->whenLoaded('status')),
            'scheduled_at' => optional($this->scheduled_at)->toIso8601String(),
            'not_before_at' => optional($this->not_before_at)->toIso8601String(),
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'court' => $this->court,
            'score_json' => $this->score_json,
            'winner_registration' => new RegistrationResource($this->whenLoaded('winnerRegistration')),
            'updated_at_daily' => optional($this->updated_at_daily)->toDateString(),
        ];
    }
}
