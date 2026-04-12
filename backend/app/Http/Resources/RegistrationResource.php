<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationResource extends JsonResource
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
            'team' => new TeamResource($this->whenLoaded('team')),
            'status' => new StatusResource($this->whenLoaded('status')),
            'queue_position' => $this->queue_position,
            'seed_number' => $this->seed_number,
            'team_ranking_score' => $this->team_ranking_score,
            'is_wildcard' => (bool) $this->is_wildcard,
            'wildcard_fee_waived' => (bool) $this->wildcard_fee_waived,
            'wildcard_invitation_id' => $this->wildcard_invitation_id,
            'rankings' => RegistrationRankingResource::collection($this->whenLoaded('rankings')),
            'has_ranking' => $this->whenLoaded('rankings', function () {
                return $this->rankings->contains(fn ($ranking) => $ranking->ranking_value !== null);
            }, null),
            'payment_is_covered' => $this->whenLoaded('payments', function () {
                return $this->payments->contains(fn ($payment) => $payment->status?->code === 'succeeded');
            }, null),
            'accepted_at' => optional($this->accepted_at)->toIso8601String(),
            'payment_due_at' => optional($this->payment_due_at)->toIso8601String(),
            'cancelled_at' => optional($this->cancelled_at)->toIso8601String(),
            'notes_admin' => $this->notes_admin,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
