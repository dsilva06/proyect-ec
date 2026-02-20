<?php

namespace App\Services;

use App\Models\Bracket;
use App\Models\Registration;
use App\Models\TournamentCategory;
use App\Models\TournamentMatch;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class MatchService
{
    public function __construct(protected StatusService $statusService)
    {
    }

    public function create(array $data, ?int $actorId = null): TournamentMatch
    {
        $data = $this->normalizeWithBracket($data);
        $data = $this->applyScheduleDefaults($data);

        if (array_key_exists('status_id', $data)) {
            $this->statusService->validateStatusForModule((int) $data['status_id'], 'match');
        }

        $this->validateRegistrations($data);
        $this->validateSchedule($data);

        if ($actorId) {
            $data['updated_by'] = $actorId;
        }

        return TournamentMatch::create($data);
    }

    public function update(TournamentMatch $match, array $data, ?int $actorId = null): TournamentMatch
    {
        $data = $this->normalizeWithBracket($data, $match);
        $data = $this->applyScheduleDefaults($data, $match);

        if (array_key_exists('status_id', $data) && $data['status_id']) {
            $this->statusService->transition($match, 'match', (int) $data['status_id'], $actorId, 'admin_update');
            unset($data['status_id']);
        }

        if ($actorId) {
            $data['updated_by'] = $actorId;
        }

        if ($data) {
            $this->validateRegistrations($data, $match);
            $this->validateSchedule($data, $match);
            $match->update($data);
        }

        $match = $match->fresh(['status']);
        $this->advanceWinnerIfReady($match);

        return $match->fresh();
    }

    private function normalizeWithBracket(array $data, ?TournamentMatch $match = null): array
    {
        if (! empty($data['bracket_id'])) {
            $bracket = Bracket::query()->findOrFail($data['bracket_id']);
            if (! empty($data['tournament_category_id']) && (int) $data['tournament_category_id'] !== (int) $bracket->tournament_category_id) {
                throw ValidationException::withMessages([
                    'tournament_category_id' => 'El bracket pertenece a otra categoría.',
                ]);
            }
            $data['tournament_category_id'] = $bracket->tournament_category_id;
        } elseif ($match && empty($data['tournament_category_id'])) {
            $data['tournament_category_id'] = $match->tournament_category_id;
        }

        return $data;
    }

    private function applyScheduleDefaults(array $data, ?TournamentMatch $match = null): array
    {
        if (empty($data['scheduled_at']) && ! empty($data['not_before_at'])) {
            $data['scheduled_at'] = $data['not_before_at'];
        }

        if (! empty($data['scheduled_at']) && empty($data['not_before_at'])) {
            $data['not_before_at'] = $data['scheduled_at'];
        }

        if (empty($data['estimated_duration_minutes'])) {
            $categoryId = $data['tournament_category_id'] ?? $match?->tournament_category_id;
            if ($categoryId) {
                $category = TournamentCategory::query()
                    ->where('id', $categoryId)
                    ->with('tournament')
                    ->first();
                $duration = $category?->tournament?->match_duration_minutes;
                if ($duration) {
                    $data['estimated_duration_minutes'] = (int) $duration;
                }
            }
        }

        return $data;
    }

    private function validateSchedule(array $data, ?TournamentMatch $match = null): void
    {
        if (empty($data['scheduled_at']) || empty($data['court'])) {
            return;
        }

        $categoryId = $data['tournament_category_id'] ?? $match?->tournament_category_id;
        if (! $categoryId) {
            return;
        }

        $tournament = TournamentCategory::query()
            ->where('id', $categoryId)
            ->with('tournament')
            ->first()?->tournament;

        if (! $tournament) {
            return;
        }

        if ($tournament->day_start_time) {
            $scheduledAt = Carbon::parse($data['scheduled_at'], $tournament->timezone);
            $startOfDay = Carbon::parse($scheduledAt->toDateString().' '.$tournament->day_start_time, $tournament->timezone);
            if ($scheduledAt->lt($startOfDay)) {
                throw ValidationException::withMessages([
                    'scheduled_at' => 'El partido no puede programarse antes de la hora de inicio.',
                ]);
            }
        }

        $conflict = TournamentMatch::query()
            ->when($match, fn ($query) => $query->where('id', '!=', $match->id))
            ->where('scheduled_at', $data['scheduled_at'])
            ->where('court', $data['court'])
            ->whereHas('tournamentCategory.tournament', function ($query) use ($tournament) {
                $query->where('tournaments.id', $tournament->id);
            })
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'court' => 'Ya existe un partido en esa cancha y horario para este torneo.',
            ]);
        }
    }

    private function advanceWinnerIfReady(TournamentMatch $match): void
    {
        if (! $match->bracket_id || ! $match->winner_registration_id) {
            return;
        }

        $statusCode = $match->status?->code;
        if (! in_array($statusCode, ['completed', 'walkover'], true)) {
            return;
        }

        $nextRound = $match->round_number + 1;
        $nextMatchNumber = (int) ceil($match->match_number / 2);

        $nextMatch = TournamentMatch::query()
            ->where('bracket_id', $match->bracket_id)
            ->where('round_number', $nextRound)
            ->where('match_number', $nextMatchNumber)
            ->first();

        if (! $nextMatch) {
            return;
        }

        $field = $match->match_number % 2 === 1 ? 'registration_a_id' : 'registration_b_id';
        if ($nextMatch->{$field} && (int) $nextMatch->{$field} === (int) $match->winner_registration_id) {
            return;
        }

        $nextMatch->{$field} = $match->winner_registration_id;
        $nextMatch->save();
    }

    private function validateRegistrations(array $data, ?TournamentMatch $match = null): void
    {
        $categoryId = $data['tournament_category_id'] ?? $match?->tournament_category_id;

        foreach (['registration_a_id', 'registration_b_id', 'winner_registration_id'] as $field) {
            if (! array_key_exists($field, $data) || ! $data[$field]) {
                continue;
            }

            $registration = Registration::query()->findOrFail($data[$field]);
            if ((int) $registration->tournament_category_id !== (int) $categoryId) {
                throw ValidationException::withMessages([
                    $field => 'La inscripción no pertenece a esta categoría.',
                ]);
            }
        }
    }
}
