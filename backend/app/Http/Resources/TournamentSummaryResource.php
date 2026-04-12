<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class TournamentSummaryResource extends JsonResource
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
            'name' => $this->name,
            'city' => $this->city,
            'venue_name' => $this->venue_name,
            'start_date' => optional($this->start_date)->toDateString(),
            'end_date' => optional($this->end_date)->toDateString(),
            'entry_fee_amount' => $this->entry_fee_amount,
            'entry_fee_currency' => $this->entry_fee_currency,
            'prize_money' => $this->prize_money,
            'prize_currency' => $this->prize_currency,
            'status' => $status ? new StatusResource($status) : null,
        ];
    }
}
