<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournament_id' => $this->tournament_id,
            'tournament' => new TournamentSummaryResource($this->whenLoaded('tournament')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'max_teams' => $this->max_teams,
            'wildcard_slots' => $this->wildcard_slots,
            'entry_fee_amount' => $this->entry_fee_amount,
            'currency' => $this->currency,
            'acceptance_type' => $this->acceptance_type,
            'acceptance_window_hours' => $this->acceptance_window_hours,
            'seeding_rule' => $this->seeding_rule,
            'min_fip_rank' => $this->min_fip_rank,
            'max_fip_rank' => $this->max_fip_rank,
            'min_fep_rank' => $this->min_fep_rank,
            'max_fep_rank' => $this->max_fep_rank,
            'status' => new StatusResource($this->whenLoaded('status')),
        ];
    }
}
