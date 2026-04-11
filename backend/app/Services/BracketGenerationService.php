<?php

namespace App\Services;

use App\Models\Bracket;
use App\Models\BracketSlot;
use App\Models\Registration;
use App\Models\TournamentMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BracketGenerationService
{
    public function __construct(protected StatusService $statusService)
    {
    }

    public function generate(Bracket $bracket, bool $randomize = false): Bracket
    {
        $bracket->loadMissing(['tournamentCategory']);
        $category = $bracket->tournamentCategory;
        if (! $category) {
            throw ValidationException::withMessages([
                'bracket' => 'La categoría del cuadro no existe.',
            ]);
        }

        $maxTeams = (int) $category->max_teams;
        if (! in_array($maxTeams, [2, 4, 8, 16, 32, 64, 128], true)) {
            throw ValidationException::withMessages([
                'draw_size' => 'El tamaño del cuadro debe ser 2, 4, 8, 16, 32, 64 o 128.',
            ]);
        }

        if (! Schema::hasColumn('registrations', 'is_wildcard')) {
            throw ValidationException::withMessages([
                'database' => 'La base de datos está desactualizada. Ejecuta las migraciones y vuelve a intentar.',
            ]);
        }

        $registrations = Registration::query()
            ->where('tournament_category_id', $category->id)
            ->whereHas('status', function ($statusQuery) {
                $statusQuery->whereIn('code', ['accepted', 'paid']);
            })
            ->get();

        $totalEligible = $registrations->count();
        if ($totalEligible < 2) {
            throw ValidationException::withMessages([
                'registrations' => 'No hay parejas suficientes para generar el cuadro.',
            ]);
        }

        $drawSize = min($maxTeams, max(8, $this->nextPowerOfTwo(max(2, $totalEligible))));
        $seedCount = $this->seedCountForSize($drawSize);
        $seedPositions = array_slice($this->seedPositions($drawSize), 0, $seedCount);

        $registrations = $registrations->sort(function ($a, $b) {
            $rankA = $a->team_ranking_score ?? PHP_INT_MAX;
            $rankB = $b->team_ranking_score ?? PHP_INT_MAX;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }
            return $a->created_at <=> $b->created_at;
        })->values();

        if ($registrations->count() > $drawSize) {
            $registrations = $registrations->take($drawSize);
        }

        $seeded = $registrations
            ->filter(fn ($registration) => ! $registration->is_wildcard && $registration->team_ranking_score !== null)
            ->take($seedCount)
            ->values();

        $remaining = $registrations
            ->reject(fn ($registration) => $seeded->contains('id', $registration->id))
            ->values()
            ->all();

        if ($randomize) {
            shuffle($remaining);
        }

        $slots = [];
        $seedMap = [];
        $seededPositions = [];
        foreach ($seedPositions as $index => $position) {
            $registration = $seeded->get($index);
            if ($registration) {
                $seedNumber = $index + 1;
                $seedMap[$registration->id] = $seedNumber;
                $slots[$position] = $registration;
                $seededPositions[] = $position;
            }
        }

        $byes = max(0, $drawSize - $registrations->count());
        $byeSlots = $this->resolveByeSlots($seededPositions, $drawSize, $byes);

        $cursor = 0;
        for ($slotNumber = 1; $slotNumber <= $drawSize; $slotNumber++) {
            if (array_key_exists($slotNumber, $slots)) {
                continue;
            }

            if (in_array($slotNumber, $byeSlots, true)) {
                $slots[$slotNumber] = null;
                continue;
            }

            $slots[$slotNumber] = $remaining[$cursor] ?? null;
            $cursor++;
        }

        DB::transaction(function () use ($bracket, $slots, $drawSize, $seedMap) {
            $bracket->slots()->delete();
            $bracket->matches()->delete();

            foreach ($slots as $slotNumber => $registration) {
                BracketSlot::create([
                    'bracket_id' => $bracket->id,
                    'slot_number' => $slotNumber,
                    'registration_id' => $registration?->id,
                    'seed_number' => $registration ? ($seedMap[$registration->id] ?? null) : null,
                ]);
            }

            if ($seedMap) {
                Registration::query()
                    ->whereIn('id', array_keys($seedMap))
                    ->get()
                    ->each(function ($registration) use ($seedMap) {
                        $registration->seed_number = $seedMap[$registration->id] ?? null;
                        $registration->save();
                    });
            }

            Registration::query()
                ->where('tournament_category_id', $bracket->tournament_category_id)
                ->when($seedMap, fn ($query) => $query->whereNotIn('id', array_keys($seedMap)))
                ->update(['seed_number' => null]);

            $statusId = $this->statusService->resolveStatusId('match', 'scheduled');
            $walkoverStatusId = $this->statusService->resolveStatusId('match', 'walkover');
            $walkovers = [];
            $rounds = (int) round(log($drawSize, 2));
            for ($round = 1; $round <= $rounds; $round++) {
                $matchesInRound = $drawSize / (2 ** $round);
                for ($matchNumber = 1; $matchNumber <= $matchesInRound; $matchNumber++) {
                    $registrationAId = null;
                    $registrationBId = null;
                    if ($round === 1) {
                        $slotA = ($matchNumber * 2) - 1;
                        $slotB = $slotA + 1;
                        $registrationAId = $slots[$slotA]?->id;
                        $registrationBId = $slots[$slotB]?->id;
                    }

                    $match = TournamentMatch::create([
                        'bracket_id' => $bracket->id,
                        'tournament_category_id' => $bracket->tournament_category_id,
                        'round_number' => $round,
                        'match_number' => $matchNumber,
                        'registration_a_id' => $registrationAId,
                        'registration_b_id' => $registrationBId,
                        'status_id' => $statusId,
                    ]);

                    if ($round === 1) {
                        $hasA = ! empty($registrationAId);
                        $hasB = ! empty($registrationBId);
                        if ($hasA xor $hasB) {
                            $walkovers[] = [
                                'match_id' => $match->id,
                                'winner_registration_id' => $hasA ? $registrationAId : $registrationBId,
                            ];
                        }
                    }
                }
            }

            foreach ($walkovers as $walkover) {
                $match = TournamentMatch::query()->find($walkover['match_id']);
                if (! $match) {
                    continue;
                }
                $match->status_id = $walkoverStatusId;
                $match->winner_registration_id = $walkover['winner_registration_id'];
                $match->save();
                $this->advanceWinner($match);
            }
        });

        return $bracket->fresh(['status', 'slots.registration.team', 'tournamentCategory.category', 'tournamentCategory.tournament.status']);
    }

    private function seedCountForSize(int $size): int
    {
        return match ($size) {
            4 => 2,
            8 => 2,
            16 => 4,
            32 => 4,
            64 => 6,
            128 => 8,
            default => 0,
        };
    }

    private function seedPositions(int $size): array
    {
        $positions = [1, 2];
        while (count($positions) < $size) {
            $nextSize = count($positions) * 2;
            $positions = collect($positions)
                ->flatMap(fn ($value) => [$value, $nextSize + 1 - $value])
                ->all();
        }

        return $positions;
    }

    private function resolveByeSlots(array $seedPositions, int $drawSize, int $byes): array
    {
        if ($byes <= 0) {
            return [];
        }

        $byeSlots = [];
        foreach ($seedPositions as $position) {
            if (count($byeSlots) >= $byes) {
                break;
            }

            $opponent = $position % 2 === 1 ? $position + 1 : $position - 1;
            if ($opponent < 1 || $opponent > $drawSize) {
                continue;
            }
            if (in_array($opponent, $seedPositions, true) || in_array($opponent, $byeSlots, true)) {
                continue;
            }
            $byeSlots[] = $opponent;
        }

        if (count($byeSlots) < $byes) {
            $available = array_values(array_diff(range(1, $drawSize), $seedPositions, $byeSlots));
            $extra = array_slice($available, -($byes - count($byeSlots)));
            $byeSlots = array_merge($byeSlots, $extra);
        }

        return $byeSlots;
    }

    private function nextPowerOfTwo(int $value): int
    {
        $power = 1;
        while ($power < $value) {
            $power *= 2;
        }
        return $power;
    }

    private function advanceWinner(TournamentMatch $match): void
    {
        if (! $match->bracket_id || ! $match->winner_registration_id) {
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
}
