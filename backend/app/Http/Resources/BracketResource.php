<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BracketResource extends JsonResource
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
            'type' => $this->type,
            'status' => new StatusResource($this->whenLoaded('status')),
            'published_at' => optional($this->published_at)->toIso8601String(),
            'slots' => BracketSlotResource::collection($this->whenLoaded('slots')),
            'matches' => MatchResource::collection($this->whenLoaded('matches')),
            'draw_size' => $this->whenLoaded('slots', fn () => $this->slots->count(), null),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
