<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'created_by' => $this->created_by,
            'status' => new StatusResource($this->whenLoaded('status')),
            'members' => UserResource::collection($this->whenLoaded('users')),
        ];
    }
}
