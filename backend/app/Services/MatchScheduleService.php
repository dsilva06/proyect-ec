<?php

namespace App\Services;

use App\Models\TournamentMatch;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class MatchScheduleService
{
    public function delay(TournamentMatch $match): void
    {
        $match->loadMissing('tournamentCategory.tournament');
        $tournament = $match->tournamentCategory?->tournament;

        if (! $tournament) {
            throw ValidationException::withMessages([
                'match' => 'El torneo no existe.',
            ]);
        }

        if (! $match->scheduled_at || ! $match->court) {
            throw ValidationException::withMessages([
                'match' => 'El partido debe tener fecha/hora y cancha para reprogramar.',
            ]);
        }

        $duration = $match->estimated_duration_minutes
            ?: $tournament->match_duration_minutes
            ?: 90;

        $timezone = $tournament->timezone ?: 'UTC';
        $dayStart = $tournament->day_start_time;
        $dayEnd = $tournament->day_end_time;

        $matches = TournamentMatch::query()
            ->whereHas('tournamentCategory.tournament', function ($query) use ($tournament) {
                $query->where('tournaments.id', $tournament->id);
            })
            ->where('court', $match->court)
            ->whereDate('scheduled_at', $match->scheduled_at->toDateString())
            ->where('scheduled_at', '>=', $match->scheduled_at)
            ->orderBy('scheduled_at')
            ->get();

        $nextTime = Carbon::parse($match->scheduled_at, $timezone)->addMinutes($duration);

        foreach ($matches as $index => $item) {
            if ($index > 0) {
                $nextTime = $nextTime->copy()->addMinutes($duration);
            }

            if ($dayEnd && $dayStart) {
                $endOfDay = Carbon::parse($nextTime->toDateString().' '.$dayEnd, $timezone);
                if ($nextTime->gt($endOfDay)) {
                    $nextTime = Carbon::parse($nextTime->copy()->addDay()->toDateString().' '.$dayStart, $timezone);
                }
            }

            $item->scheduled_at = $nextTime;
            $item->not_before_at = $nextTime;
            if (! $item->estimated_duration_minutes) {
                $item->estimated_duration_minutes = $duration;
            }
            $item->save();
        }
    }
}
