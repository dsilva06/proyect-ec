<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\RegistrationRanking;
use App\Models\Team;
use App\Models\TournamentCategory;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    public function __construct(
        protected AcceptanceService $acceptanceService,
        protected StatusService $statusService
    ) {
    }

    public function create(
        User $user,
        int $teamId,
        int $tournamentCategoryId,
        array $rankingData,
        bool $isWildcard = false,
        bool $wildcardFeeWaived = false,
        ?int $wildcardInvitationId = null
    ): Registration
    {
        $ownsTeam = Team::query()
            ->where('id', $teamId)
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->exists();

        if (! $ownsTeam) {
            throw ValidationException::withMessages([
                'team_id' => 'Team not found for user.',
            ]);
        }

        $team = Team::query()
            ->with(['users', 'invites', 'members'])
            ->find($teamId);

        if (! $team) {
            throw ValidationException::withMessages([
                'team_id' => 'Team not found for user.',
            ]);
        }

        $category = TournamentCategory::query()
            ->with(['category'])
            ->findOrFail($tournamentCategoryId);

        $selfRanking = (int) ($rankingData['self_ranking_value'] ?? 0);
        $partnerRanking = (int) ($rankingData['partner_ranking_value'] ?? 0);

        if (! $isWildcard) {
            if ($selfRanking <= 0 || $partnerRanking <= 0) {
                throw ValidationException::withMessages([
                    'ranking' => 'Debes ingresar el ranking de ambos jugadores.',
                ]);
            }

            if ($selfRanking === $partnerRanking) {
                throw ValidationException::withMessages([
                    'ranking' => 'No puede haber dos jugadores con el mismo ranking en la misma categoría.',
                ]);
            }

            $existingRanking = RegistrationRanking::query()
                ->where('tournament_category_id', $tournamentCategoryId)
                ->whereIn('ranking_value', [$selfRanking, $partnerRanking])
                ->exists();

            if ($existingRanking) {
                throw ValidationException::withMessages([
                    'ranking' => 'El ranking ya está tomado en esta categoría. Usa un número distinto.',
                ]);
            }

            $this->validateGlobalRanking($user, $team, $category, $rankingData);
            $this->validateInternalRanking($team, $category);
        } else {
            $this->ensureWildcardCapacity($category);
        }

        $existing = Registration::query()
            ->where('tournament_category_id', $tournamentCategoryId)
            ->where('team_id', $teamId)
            ->first();

        if ($existing) {
            return $existing->fresh();
        }

        return DB::transaction(function () use (
            $user,
            $team,
            $teamId,
            $tournamentCategoryId,
            $selfRanking,
            $partnerRanking,
            $rankingData,
            $isWildcard,
            $wildcardFeeWaived,
            $wildcardInvitationId
        ) {
            $registration = Registration::create([
                'tournament_category_id' => $tournamentCategoryId,
                'team_id' => $teamId,
                'status_id' => $this->statusService->resolveStatusId('registration', 'pending'),
                'is_wildcard' => $isWildcard,
                'wildcard_fee_waived' => $wildcardFeeWaived,
                'wildcard_invitation_id' => $wildcardInvitationId,
            ]);

            $partnerEmail = $rankingData['partner_email'] ?? null;
            $invite = $team->invites->sortByDesc('created_at')->first();
            if (! $partnerEmail && $invite?->invited_email) {
                $partnerEmail = $invite->invited_email;
            }

            $partnerUserId = $team->members->firstWhere('slot', 2)?->user_id;
            if (! $partnerUserId && $invite?->invited_user_id) {
                $partnerUserId = $invite->invited_user_id;
            }
            if (! $partnerUserId && $partnerEmail) {
                $partnerUserId = User::query()->where('email', $partnerEmail)->value('id');
            }

            RegistrationRanking::create([
                'registration_id' => $registration->id,
                'tournament_category_id' => $tournamentCategoryId,
                'slot' => 1,
                'user_id' => $user->id,
                'ranking_value' => $isWildcard ? ($selfRanking > 0 ? $selfRanking : null) : $selfRanking,
                'ranking_source' => $rankingData['self_ranking_source'] ?? null,
            ]);

            RegistrationRanking::create([
                'registration_id' => $registration->id,
                'tournament_category_id' => $tournamentCategoryId,
                'slot' => 2,
                'user_id' => $partnerUserId,
                'invited_email' => $partnerEmail,
                'ranking_value' => $isWildcard ? ($partnerRanking > 0 ? $partnerRanking : null) : $partnerRanking,
                'ranking_source' => $rankingData['partner_ranking_source'] ?? null,
            ]);

            $this->acceptanceService->recalculateForTournamentCategory($tournamentCategoryId);

            return $registration->fresh();
        });
    }

    public function updateFromAdmin(Registration $registration, array $data, ?User $actor = null): Registration
    {
        if (array_key_exists('status_id', $data) && $data['status_id']) {
            $this->statusService->transition(
                $registration,
                'registration',
                (int) $data['status_id'],
                $actor?->id,
                'admin_update'
            );
            unset($data['status_id']);
        }

        if ($data) {
            $registration->fill($data);
            $registration->save();
        }

        return $registration->fresh();
    }

    public function category(int $tournamentCategoryId): TournamentCategory
    {
        return TournamentCategory::query()->findOrFail($tournamentCategoryId);
    }

    private function ensureWildcardCapacity(TournamentCategory $category): void
    {
        $wildcardSlots = max(0, (int) $category->wildcard_slots);
        if ($wildcardSlots === 0) {
            throw ValidationException::withMessages([
                'wildcard' => 'La categoría no tiene cupos de wildcard disponibles.',
            ]);
        }

        $current = Registration::query()
            ->where('tournament_category_id', $category->id)
            ->where('is_wildcard', true)
            ->count();

        if ($current >= $wildcardSlots) {
            throw ValidationException::withMessages([
                'wildcard' => 'Ya no quedan cupos de wildcard para esta categoría.',
            ]);
        }
    }

    private function validateGlobalRanking(User $user, Team $team, TournamentCategory $category, array $rankingData): void
    {
        $team->loadMissing(['users', 'members']);
        $user->loadMissing('playerProfile');

        $partnerUserId = $team->members->firstWhere('slot', 2)?->user_id;
        $partnerUser = $partnerUserId ? User::query()->with('playerProfile')->find($partnerUserId) : null;

        $selfRanking = (int) ($rankingData['self_ranking_value'] ?? 0);
        $partnerRanking = (int) ($rankingData['partner_ranking_value'] ?? 0);

        $selfSource = $rankingData['self_ranking_source'] ?? $user->playerProfile?->ranking_source;
        $partnerSource = $rankingData['partner_ranking_source'] ?? $partnerUser?->playerProfile?->ranking_source;

        $this->enforceRankingRange($selfRanking, $selfSource, $category, 'ranking');
        $this->enforceRankingRange($partnerRanking, $partnerSource, $category, 'partner_ranking_value');
    }

    private function enforceRankingRange(int $rankingValue, ?string $rankingSource, TournamentCategory $category, string $field): void
    {
        if (! $rankingValue || ! $rankingSource) {
            return;
        }

        $source = strtoupper($rankingSource);
        if ($source === 'FIP') {
            $min = $category->min_fip_rank;
            $max = $category->max_fip_rank;
        } elseif ($source === 'FEP') {
            $min = $category->min_fep_rank;
            $max = $category->max_fep_rank;
        } else {
            return;
        }

        if ($min && $rankingValue < $min) {
            throw ValidationException::withMessages([
                $field => 'El ranking es demasiado alto para esta categoría.',
            ]);
        }

        if ($max && $rankingValue > $max) {
            throw ValidationException::withMessages([
                $field => 'El ranking es demasiado bajo para esta categoría.',
            ]);
        }
    }

    private function validateInternalRanking(Team $team, TournamentCategory $category): void
    {
        $category->loadMissing('category');
        $group = $category->category?->group_code;
        $currentOrder = $category->category?->sort_order;

        if (! $group || $currentOrder === null) {
            return;
        }

        $higherCategoryIds = TournamentCategory::query()
            ->whereHas('category', function ($query) use ($group, $currentOrder) {
                $query->where('group_code', $group)
                    ->where('sort_order', '<', $currentOrder);
            })
            ->pluck('id');

        if ($higherCategoryIds->isEmpty()) {
            return;
        }

        $team->loadMissing('users');
        foreach ($team->users as $member) {
            $this->assertUserNotLockedToHigherCategory($member, $higherCategoryIds);
        }
    }

    private function assertUserNotLockedToHigherCategory(User $user, $higherCategoryIds): void
    {
        $registrationIds = Registration::query()
            ->whereIn('tournament_category_id', $higherCategoryIds)
            ->whereHas('team.users', fn ($query) => $query->where('users.id', $user->id))
            ->pluck('id');

        if ($registrationIds->isEmpty()) {
            return;
        }

        $categorySizes = TournamentCategory::query()
            ->whereIn('id', $higherCategoryIds)
            ->pluck('max_teams', 'id');

        foreach ($registrationIds as $registrationId) {
            $categoryId = Registration::query()
                ->where('id', $registrationId)
                ->value('tournament_category_id');

            if (! $categoryId) {
                continue;
            }

            $drawSize = (int) ($categorySizes[$categoryId] ?? 0);
            if (! $drawSize) {
                continue;
            }

            $rounds = (int) round(log($drawSize, 2));
            $semiRound = max(1, $rounds - 1);

            $reachedSemis = TournamentMatch::query()
                ->where('tournament_category_id', $categoryId)
                ->where('round_number', '>=', $semiRound)
                ->where(function ($query) use ($registrationId) {
                    $query->where('registration_a_id', $registrationId)
                        ->orWhere('registration_b_id', $registrationId)
                        ->orWhere('winner_registration_id', $registrationId);
                })
                ->whereHas('status', function ($query) {
                    $query->whereIn('code', ['completed', 'walkover']);
                })
                ->exists();

            if ($reachedSemis) {
                throw ValidationException::withMessages([
                    'ranking' => 'No puedes inscribirte en una categoría inferior por historial competitivo.',
                ]);
            }
        }
    }
}
