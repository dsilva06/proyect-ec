<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BracketSlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bracket_id' => $this->bracket_id,
            'slot_number' => $this->slot_number,
            'seed_number' => $this->seed_number,
            'registration' => new RegistrationResource($this->whenLoaded('registration')),
        ];
    }
}
