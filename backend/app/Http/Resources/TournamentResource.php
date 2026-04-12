<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class TournamentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->whenLoaded('status');
        if ($status instanceof MissingValue) {
            $status = $this->status;
        }

        return [
            'id' => $this->id,
            'circuit_id' => $this->circuit_id,
            'name' => $this->name,
            'description' => $this->description,
            'mode' => $this->mode,
            'classification_method' => $this->classification_method,
            'status' => $status ? new StatusResource($status) : null,
            'venue_name' => $this->venue_name,
            'venue_address' => $this->venue_address,
            'city' => $this->city,
            'province_state' => $this->province_state,
            'country' => $this->country,
            'timezone' => $this->timezone,
            'start_date' => optional($this->start_date)->toDateString(),
            'end_date' => optional($this->end_date)->toDateString(),
            'entry_fee_amount' => $this->entry_fee_amount,
            'entry_fee_currency' => $this->entry_fee_currency,
            'registration_open_at' => optional($this->registration_open_at)->toIso8601String(),
            'registration_close_at' => optional($this->registration_close_at)->toIso8601String(),
            'day_start_time' => $this->day_start_time,
            'day_end_time' => $this->day_end_time,
            'match_duration_minutes' => $this->match_duration_minutes,
            'courts_count' => $this->courts_count,
            'prize_money' => $this->prize_money,
            'prize_currency' => $this->prize_currency,
            'created_by' => $this->created_by,
            'categories' => TournamentCategoryResource::collection($this->whenLoaded('categories')),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
