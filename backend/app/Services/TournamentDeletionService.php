<?php

namespace App\Services;

use App\Models\Bracket;
use App\Models\CalendarEvent;
use App\Models\Invitation;
use App\Models\OpenEntry;
use App\Models\PlayerPrizePayout;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TournamentDeletionService
{
    public function deleteTournament(Tournament $tournament): void
    {
        $blockers = $this->tournamentDeletionBlockers($tournament);

        if ($blockers !== []) {
            throw ValidationException::withMessages([
                'tournament' => 'No se puede eliminar este torneo porque ya tiene '.$this->formatBlockers($blockers).'.',
            ]);
        }

        DB::transaction(function () use ($tournament): void {
            $tournament->delete();
        });
    }

    public function deleteTournamentCategory(TournamentCategory $tournamentCategory): void
    {
        $blockers = $this->tournamentCategoryDeletionBlockers($tournamentCategory);

        if ($blockers !== []) {
            throw ValidationException::withMessages([
                'tournament_category' => 'No se puede eliminar esta categoría porque ya tiene '.$this->formatBlockers($blockers).'.',
            ]);
        }

        DB::transaction(function () use ($tournamentCategory): void {
            $tournamentCategory->delete();
        });
    }

    /**
     * @return list<string>
     */
    private function tournamentDeletionBlockers(Tournament $tournament): array
    {
        $categoryIds = $tournament->categories()->pluck('id');
        $blockers = [];

        if ($categoryIds->isNotEmpty()) {
            if (Registration::query()->whereIn('tournament_category_id', $categoryIds)->exists()) {
                $blockers[] = 'inscripciones';
            }

            if (Bracket::query()->whereIn('tournament_category_id', $categoryIds)->exists()) {
                $blockers[] = 'cuadros';
            }

            if (Invitation::query()->whereIn('tournament_category_id', $categoryIds)->exists()) {
                $blockers[] = 'wildcards o invitaciones';
            }

            if (PlayerPrizePayout::query()->whereIn('tournament_category_id', $categoryIds)->exists()) {
                $blockers[] = 'premios cargados';
            }
        }

        if (OpenEntry::query()->where('tournament_id', $tournament->id)->exists()) {
            $blockers[] = 'entradas OPEN';
        }

        if (CalendarEvent::query()->where('tournament_id', $tournament->id)->exists()) {
            $blockers[] = 'eventos de calendario';
        }

        return $blockers;
    }

    /**
     * @return list<string>
     */
    private function tournamentCategoryDeletionBlockers(TournamentCategory $tournamentCategory): array
    {
        $blockers = [];

        if ($tournamentCategory->registrations()->exists()) {
            $blockers[] = 'inscripciones';
        }

        if ($tournamentCategory->brackets()->exists()) {
            $blockers[] = 'cuadros';
        }

        if ($tournamentCategory->invitations()->exists()) {
            $blockers[] = 'wildcards o invitaciones';
        }

        if ($tournamentCategory->prizePayouts()->exists()) {
            $blockers[] = 'premios cargados';
        }

        return $blockers;
    }

    /**
     * @param list<string> $blockers
     */
    private function formatBlockers(array $blockers): string
    {
        if (count($blockers) === 1) {
            return $blockers[0];
        }

        $last = array_pop($blockers);

        return implode(', ', $blockers).' y '.$last;
    }
}
