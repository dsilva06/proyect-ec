<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BracketSlotResource;
use App\Models\BracketSlot;
use App\Services\BracketService;
use Illuminate\Http\Request;

class BracketSlotController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'bracket_id' => ['required', 'exists:brackets,id'],
            'slot_number' => ['required', 'integer', 'min:1'],
            'registration_id' => ['nullable', 'exists:registrations,id'],
            'seed_number' => ['nullable', 'integer', 'min:1'],
        ]);

        $slot = app(BracketService::class)->assignSlot($data);

        return new BracketSlotResource($slot);
    }

    public function update(Request $request, BracketSlot $bracketSlot)
    {
        $data = $request->validate([
            'slot_number' => ['sometimes', 'integer', 'min:1'],
            'registration_id' => ['nullable', 'exists:registrations,id'],
            'seed_number' => ['nullable', 'integer', 'min:1'],
        ]);

        $slot = app(BracketService::class)->updateSlot($bracketSlot, $data);

        return new BracketSlotResource($slot);
    }
}
