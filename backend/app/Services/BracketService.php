<?php

namespace App\Services;

use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Registration;
use Illuminate\Validation\ValidationException;

class BracketService
{

    public function assignSlot(array $data): BracketSlot
    {
        $bracket = Bracket::query()->findOrFail($data['bracket_id']);
        $registrationId = $data['registration_id'] ?? null;

        if ($registrationId) {
            $registration = Registration::query()
                ->with('status')
                ->findOrFail($registrationId);

            if ($registration->tournament_category_id !== $bracket->tournament_category_id) {
                throw ValidationException::withMessages([
                    'registration_id' => 'La inscripción no pertenece a esta categoría.',
                ]);
            }

            $statusCode = $registration->status?->code;
            $isAllowed = in_array($statusCode, ['accepted', 'paid'], true);

            if (! $isAllowed) {
                throw ValidationException::withMessages([
                    'registration_id' => 'Solo se pueden asignar inscripciones aceptadas o pagadas.',
                ]);
            }

            $exists = BracketSlot::query()
                ->where('bracket_id', $bracket->id)
                ->where('registration_id', $registrationId)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'registration_id' => 'La inscripción ya está asignada en otro slot.',
                ]);
            }
        }

        $slot = BracketSlot::create($data);

        return $slot->fresh(['registration.team']);
    }

    public function updateSlot(BracketSlot $slot, array $data): BracketSlot
    {
        if (array_key_exists('registration_id', $data) && $data['registration_id']) {
            $bracket = $slot->bracket()->first();
            $registration = Registration::query()
                ->with('status')
                ->findOrFail($data['registration_id']);

            if ($registration->tournament_category_id !== $bracket->tournament_category_id) {
                throw ValidationException::withMessages([
                    'registration_id' => 'La inscripción no pertenece a esta categoría.',
                ]);
            }

            $statusCode = $registration->status?->code;
            $isAllowed = in_array($statusCode, ['accepted', 'paid'], true);

            if (! $isAllowed) {
                throw ValidationException::withMessages([
                    'registration_id' => 'Solo se pueden asignar inscripciones aceptadas o pagadas.',
                ]);
            }

            $exists = BracketSlot::query()
                ->where('bracket_id', $bracket->id)
                ->where('registration_id', $data['registration_id'])
                ->where('id', '!=', $slot->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'registration_id' => 'La inscripción ya está asignada en otro slot.',
                ]);
            }
        }

        $slot->update($data);

        return $slot->fresh(['registration.team']);
    }
}
