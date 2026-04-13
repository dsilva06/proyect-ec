<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpenEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paymentsLoaded = $this->relationLoaded('payments');
        $latestPayment = $paymentsLoaded
            ? $this->payments->sortByDesc('created_at')->first()
            : null;

        return [
            'id' => $this->id,
            'tournament_id' => $this->tournament_id,
            'tournament' => new TournamentResource($this->whenLoaded('tournament')),
            'team_id' => $this->team_id,
            'team' => new TeamResource($this->whenLoaded('team')),
            'submitted_by_user_id' => $this->submitted_by_user_id,
            'submitted_by' => new UserResource($this->whenLoaded('submittedBy')),
            'segment' => $this->segment,
            'partner_email' => $this->partner_email,
            'partner_first_name' => $this->partner_first_name,
            'partner_last_name' => $this->partner_last_name,
            'partner_dni' => $this->partner_dni,
            'assignment_status' => $this->assignment_status,
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'payment_status_code' => $latestPayment?->status?->code,
            'payment_is_covered' => $this->paid_at !== null || ($paymentsLoaded && $this->payments->contains(fn ($payment) => $payment->status?->code === 'succeeded')),
            'assigned_tournament_category_id' => $this->assigned_tournament_category_id,
            'assigned_tournament_category' => new TournamentCategoryResource($this->whenLoaded('assignedTournamentCategory')),
            'registration_id' => $this->registration_id,
            'registration' => new RegistrationResource($this->whenLoaded('registration')),
            'assigned_by_user_id' => $this->assigned_by_user_id,
            'assigned_by' => new UserResource($this->whenLoaded('assignedBy')),
            'assigned_at' => optional($this->assigned_at)->toIso8601String(),
            'notes_admin' => $this->notes_admin,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
