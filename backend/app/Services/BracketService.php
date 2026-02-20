<?php

namespace App\Services;

use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Registration;
use App\Models\TournamentMatch;
use Illuminate\Validation\ValidationException;

class BracketService
{
    private ?int $scheduledMatchStatusId = null;
    private ?int $walkoverMatchStatusId = null;

    public function __construct(protected StatusService $statusService)
    {
    }

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
        $this->syncRoundOneMatch((int) $slot->bracket_id, (int) $slot->slot_number, $slot->registration_id ? (int) $slot->registration_id : null);

        return $slot->fresh(['registration.team']);
    }

    public function updateSlot(BracketSlot $slot, array $data): BracketSlot
    {
        $originalSlotNumber = (int) $slot->slot_number;

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
        $slot = $slot->fresh(['registration.team']);

        if ((int) $slot->slot_number !== $originalSlotNumber) {
            $this->syncRoundOneMatch((int) $slot->bracket_id, $originalSlotNumber, null);
        }
        $this->syncRoundOneMatch((int) $slot->bracket_id, (int) $slot->slot_number, $slot->registration_id ? (int) $slot->registration_id : null);

        return $slot;
    }

    private function syncRoundOneMatch(int $bracketId, int $slotNumber, ?int $registrationId): void
    {
        $matchNumber = (int) ceil($slotNumber / 2);
        $field = $slotNumber % 2 === 1 ? 'registration_a_id' : 'registration_b_id';

        $match = TournamentMatch::query()
            ->where('bracket_id', $bracketId)
            ->where('round_number', 1)
            ->where('match_number', $matchNumber)
            ->first();

        if (! $match) {
            return;
        }

        $previousRegistrationId = $match->{$field} ? (int) $match->{$field} : null;
        $nextRegistrationId = $registrationId ?: null;
        $branchChanged = $previousRegistrationId !== $nextRegistrationId;

        $match->{$field} = $nextRegistrationId;

        if ($branchChanged) {
            $match->winner_registration_id = null;
            $match->score_json = null;
            $this->clearNextMatchPath($match);
        }

        $this->normalizeMatchState($match);
    }

    private function normalizeMatchState(TournamentMatch $match): void
    {
        $hasA = ! empty($match->registration_a_id);
        $hasB = ! empty($match->registration_b_id);
        $scheduledStatusId = $this->scheduledMatchStatusId();
        $walkoverStatusId = $this->walkoverMatchStatusId();

        if ($hasA xor $hasB) {
            $winnerId = $hasA ? (int) $match->registration_a_id : (int) $match->registration_b_id;
            $match->status_id = $walkoverStatusId;
            $match->winner_registration_id = $winnerId;
            $match->save();
            $this->advanceWinner($match);
            return;
        }

        $match->status_id = $scheduledStatusId;

        if (! $hasA && ! $hasB) {
            $match->winner_registration_id = null;
        } elseif (
            $match->winner_registration_id
            && ! in_array((int) $match->winner_registration_id, [(int) $match->registration_a_id, (int) $match->registration_b_id], true)
        ) {
            $match->winner_registration_id = null;
        }

        $match->save();
    }

    private function clearNextMatchPath(TournamentMatch $match): void
    {
        if (! $match->bracket_id) {
            return;
        }

        $nextMatch = TournamentMatch::query()
            ->where('bracket_id', $match->bracket_id)
            ->where('round_number', $match->round_number + 1)
            ->where('match_number', (int) ceil($match->match_number / 2))
            ->first();

        if (! $nextMatch) {
            return;
        }

        $field = $match->match_number % 2 === 1 ? 'registration_a_id' : 'registration_b_id';

        $nextMatch->{$field} = null;
        $nextMatch->winner_registration_id = null;
        $nextMatch->score_json = null;
        $nextMatch->status_id = $this->scheduledMatchStatusId();
        $nextMatch->save();

        $this->clearNextMatchPath($nextMatch);
    }

    private function advanceWinner(TournamentMatch $match): void
    {
        if (! $match->bracket_id || ! $match->winner_registration_id) {
            return;
        }

        $nextMatch = TournamentMatch::query()
            ->where('bracket_id', $match->bracket_id)
            ->where('round_number', $match->round_number + 1)
            ->where('match_number', (int) ceil($match->match_number / 2))
            ->first();

        if (! $nextMatch) {
            return;
        }

        $field = $match->match_number % 2 === 1 ? 'registration_a_id' : 'registration_b_id';
        $nextMatch->{$field} = (int) $match->winner_registration_id;
        $this->normalizeMatchState($nextMatch);
    }

    private function scheduledMatchStatusId(): int
    {
        if (! $this->scheduledMatchStatusId) {
            $this->scheduledMatchStatusId = $this->statusService->resolveStatusId('match', 'scheduled');
        }

        return $this->scheduledMatchStatusId;
    }

    private function walkoverMatchStatusId(): int
    {
        if (! $this->walkoverMatchStatusId) {
            $this->walkoverMatchStatusId = $this->statusService->resolveStatusId('match', 'walkover');
        }

        return $this->walkoverMatchStatusId;
    }
}
