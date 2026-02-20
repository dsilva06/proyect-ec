<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\TournamentCategory;
use App\Models\User;

class AcceptanceService
{
    public function __construct(
        protected StatusService $statusService
    ) {
    }

    public function recalculateForTournamentCategory(int|TournamentCategory $tournamentCategory): void
    {
        $category = $tournamentCategory instanceof TournamentCategory
            ? $tournamentCategory->loadMissing('tournament')
            : TournamentCategory::query()->with('tournament')->findOrFail($tournamentCategory);

        $capacity = max(0, (int) $category->max_teams);
        $acceptanceType = $category->acceptance_type ?: 'waitlist';
        $windowHours = $category->acceptance_window_hours;
        $seedingRule = $category->seeding_rule ?: 'ranking_desc';
        $wildcardSlots = max(0, (int) $category->wildcard_slots);

        $registrations = Registration::query()
            ->where('tournament_category_id', $category->id)
            ->with(['rankings', 'status'])
            ->get();

        $wildcards = [];
        $eligible = [];
        $pending = [];
        $locked = [];

        foreach ($registrations as $registration) {
            $statusCode = $registration->status?->code;
            if ($registration->is_wildcard) {
                $wildcards[] = $registration;
                continue;
            }
            if (in_array($statusCode, ['paid', 'payment_pending'], true)) {
                $locked[] = $registration;
                continue;
            }

            $rankings = $registration->rankings
                ->sortBy('slot')
                ->values()
                ->take(2);

            $rankingA = $rankings->get(0)?->ranking_value;
            $rankingB = $rankings->get(1)?->ranking_value;

            if ($rankingA === null || $rankingB === null) {
                $pending[] = $registration;
                continue;
            }

            $avg = ($rankingA + $rankingB) / 2;
            $best = min($rankingA, $rankingB);

            $eligible[] = [
                'registration' => $registration,
                'avg' => $avg,
                'best' => $best,
            ];
        }

        usort($eligible, function ($a, $b) use ($seedingRule) {
            if ($seedingRule === 'fifo') {
                return $a['registration']->created_at <=> $b['registration']->created_at;
            }

            if ($a['avg'] !== $b['avg']) {
                return $a['avg'] <=> $b['avg'];
            }
            if ($a['best'] !== $b['best']) {
                return $a['best'] <=> $b['best'];
            }
            return $a['registration']->created_at <=> $b['registration']->created_at;
        });

        $wildcardCount = min(count($wildcards), $wildcardSlots);
        $availableSlots = max(0, $capacity - count($locked) - $wildcardCount);
        $accepted = array_slice($eligible, 0, $availableSlots);
        $waitlisted = array_slice($eligible, $availableSlots);

        $pendingStatusId = $this->statusService->resolveStatusId('registration', 'pending');
        $acceptedStatusId = $this->statusService->resolveStatusId('registration', 'accepted');
        $waitlistStatusId = $this->statusService->resolveStatusId('registration', 'waitlisted');
        $paymentPendingStatusId = $this->statusService->resolveStatusId('registration', 'payment_pending');
        $paidStatusId = $this->statusService->resolveStatusId('registration', 'paid');

        foreach ($locked as $index => $registration) {
            $registration->queue_position = $index + 1;
            $registration->save();
        }

        foreach (array_slice($wildcards, 0, $wildcardCount) as $index => $registration) {
            $nextStatus = $registration->wildcard_fee_waived
                ? $paidStatusId
                : ($acceptanceType === 'immediate' ? $paymentPendingStatusId : $acceptedStatusId);

            $this->statusService->transition($registration, 'registration', $nextStatus);

            $registration->queue_position = count($locked) + $index + 1;
            $registration->team_ranking_score = $registration->team_ranking_score ?: null;
            $registration->accepted_at = $registration->accepted_at ?? now();
            if ($windowHours && ! $registration->payment_due_at && ! $registration->wildcard_fee_waived) {
                $registration->payment_due_at = now()->addHours((int) $windowHours);
            }
            $registration->save();
        }

        $acceptedOffset = count($locked);

        foreach ($accepted as $index => $item) {
            $registration = $item['registration'];
            $nextStatus = $acceptanceType === 'immediate' ? $paymentPendingStatusId : $acceptedStatusId;

            $this->statusService->transition($registration, 'registration', $nextStatus);

            $registration->queue_position = $acceptedOffset + $wildcardCount + $index + 1;
            $registration->team_ranking_score = (int) round($item['avg']);
            $registration->accepted_at = $registration->accepted_at ?? now();
            if ($windowHours && ! $registration->payment_due_at) {
                $registration->payment_due_at = now()->addHours((int) $windowHours);
            }
            $registration->save();
        }

        foreach ($waitlisted as $index => $item) {
            $registration = $item['registration'];

            $this->statusService->transition($registration, 'registration', $waitlistStatusId);

            $registration->queue_position = $index + 1;
            $registration->team_ranking_score = (int) round($item['avg']);
            $registration->accepted_at = null;
            $registration->payment_due_at = null;
            $registration->save();
        }

        foreach ($pending as $registration) {
            $this->statusService->transition($registration, 'registration', $pendingStatusId);
            $registration->queue_position = null;
            $registration->team_ranking_score = null;
            $registration->accepted_at = null;
            $registration->payment_due_at = null;
            $registration->save();
        }
    }

    public function recalculateForUser(User $user): void
    {
        $categoryIds = Registration::query()
            ->whereHas('team.users', fn ($query) => $query->where('users.id', $user->id))
            ->pluck('tournament_category_id')
            ->unique();

        foreach ($categoryIds as $categoryId) {
            $this->recalculateForTournamentCategory($categoryId);
        }
    }

}
