<?php

namespace App\Http\Resources;

use App\Models\Registration;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BracketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournament_category_id' => $this->tournament_category_id,
            'tournament_category' => new TournamentCategoryResource($this->whenLoaded('tournamentCategory')),
            'type' => $this->type,
            'status' => new StatusResource($this->whenLoaded('status')),
            'published_at' => optional($this->published_at)->toIso8601String(),
            'slots' => BracketSlotResource::collection($this->whenLoaded('slots')),
            'matches' => MatchResource::collection($this->whenLoaded('matches')),
            'draw_size' => $this->whenLoaded('slots', fn () => $this->slots->count(), null),
            'draw_plan' => $this->when(
                $request->is('api/admin/*'),
                fn () => $this->drawPlan()
            ),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function drawPlan(): array
    {
        $tournamentCategory = $this->tournamentCategory;
        $tournament = $tournamentCategory?->tournament;
        $isOpen = strtolower((string) ($tournament?->mode ?? '')) === 'open';
        $eligibleRegistrations = $this->eligibleRegistrations();
        $eligibleCount = $eligibleRegistrations->count();
        $drawSize = $this->resolvedDrawSize($eligibleCount);
        $byeCount = max(0, $drawSize - min($eligibleCount, $drawSize));

        return [
            'is_open' => $isOpen,
            'eligible_pairs_count' => $eligibleCount,
            'computed_draw_size' => $drawSize,
            'bye_count' => $byeCount,
            'bye_recipients' => RegistrationResource::collection(
                $this->byeRecipients($eligibleRegistrations, $byeCount, $isOpen)
            ),
        ];
    }

    private function eligibleRegistrations()
    {
        if (! $this->tournament_category_id) {
            return collect();
        }

        return Registration::query()
            ->with(['team', 'payments.status', 'openEntry'])
            ->where('tournament_category_id', $this->tournament_category_id)
            ->whereHas('status', function ($statusQuery) {
                $statusQuery->whereIn('code', ['accepted', 'paid']);
            })
            ->get();
    }

    private function resolvedDrawSize(int $eligibleCount): int
    {
        if ($this->relationLoaded('slots') && $this->slots->count() > 0) {
            return $this->slots->count();
        }

        $maxTeams = (int) ($this->tournamentCategory?->max_teams ?? 0);
        if (! in_array($maxTeams, [2, 4, 8, 16, 32, 64, 128], true)) {
            return 0;
        }

        return min($maxTeams, $this->nextPowerOfTwo(max(2, $eligibleCount)));
    }

    private function byeRecipients($eligibleRegistrations, int $byeCount, bool $isOpen)
    {
        if ($byeCount <= 0) {
            return collect();
        }

        $generatedRecipients = $this->generatedByeRecipients();
        if ($generatedRecipients->isNotEmpty()) {
            return $generatedRecipients;
        }

        if (! $isOpen) {
            return collect();
        }

        return $eligibleRegistrations
            ->sort(fn ($a, $b) => $this->compareOpenRegistrationsForByeProposal($a, $b))
            ->take($byeCount)
            ->values();
    }

    private function generatedByeRecipients()
    {
        if (! $this->relationLoaded('matches')) {
            return collect();
        }

        return $this->matches
            ->filter(function ($match) {
                if ((int) $match->round_number !== 1) {
                    return false;
                }

                $hasA = (bool) $match->registration_a_id;
                $hasB = (bool) $match->registration_b_id;

                return $hasA !== $hasB;
            })
            ->map(fn ($match) => $match->registrationA ?: $match->registrationB)
            ->filter()
            ->values();
    }

    private function nextPowerOfTwo(int $value): int
    {
        $power = 1;
        while ($power < $value) {
            $power *= 2;
        }

        return $power;
    }

    private function compareOpenRegistrationsForByeProposal(Registration $a, Registration $b): int
    {
        $paidA = $this->paymentConfirmedAt($a)?->getTimestamp() ?? PHP_INT_MAX;
        $paidB = $this->paymentConfirmedAt($b)?->getTimestamp() ?? PHP_INT_MAX;

        if ($paidA !== $paidB) {
            return $paidA <=> $paidB;
        }

        $createdA = $a->created_at?->getTimestamp() ?? PHP_INT_MAX;
        $createdB = $b->created_at?->getTimestamp() ?? PHP_INT_MAX;

        if ($createdA !== $createdB) {
            return $createdA <=> $createdB;
        }

        return (int) $a->id <=> (int) $b->id;
    }

    private function paymentConfirmedAt(Registration $registration): ?CarbonInterface
    {
        if ($registration->relationLoaded('openEntry') && $registration->openEntry?->paid_at) {
            return $registration->openEntry->paid_at;
        }

        if (! $registration->relationLoaded('payments')) {
            return null;
        }

        $payment = $registration->payments
            ->filter(fn ($payment) => $payment->status?->code === 'succeeded')
            ->sortBy(fn ($payment) => $payment->paid_at?->getTimestamp() ?? $payment->created_at?->getTimestamp() ?? PHP_INT_MAX)
            ->first();

        return $payment?->paid_at ?? $payment?->created_at;
    }
}
