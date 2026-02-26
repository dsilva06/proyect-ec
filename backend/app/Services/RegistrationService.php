<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\RegistrationRanking;
use App\Models\Team;
use App\Models\TournamentCategory;
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
        $defaultSource = $this->defaultRankingSourceForCategory($category);
        $selfSource = strtoupper((string) ($rankingData['self_ranking_source'] ?? $defaultSource));
        $partnerSource = strtoupper((string) ($rankingData['partner_ranking_source'] ?? $defaultSource));

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

            $this->assertRankingSourceMatchesCategory($selfSource, $category, 'self_ranking_source');
            $this->assertRankingSourceMatchesCategory($partnerSource, $category, 'partner_ranking_source');
            $this->validateGlobalRanking($category, $selfRanking, $partnerRanking, $selfSource, $partnerSource);
            $this->validateLowerCategoryDrawEligibility($team, $category);
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
            $selfSource,
            $partnerSource,
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
                'ranking_source' => ($isWildcard && $selfRanking <= 0) ? null : $selfSource,
            ]);

            RegistrationRanking::create([
                'registration_id' => $registration->id,
                'tournament_category_id' => $tournamentCategoryId,
                'slot' => 2,
                'user_id' => $partnerUserId,
                'invited_email' => $partnerEmail,
                'ranking_value' => $isWildcard ? ($partnerRanking > 0 ? $partnerRanking : null) : $partnerRanking,
                'ranking_source' => ($isWildcard && $partnerRanking <= 0) ? null : $partnerSource,
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

    public function updateRankingsFromAdmin(Registration $registration, array $rankingsData, ?User $actor = null): Registration
    {
        $registration->loadMissing([
            'rankings',
            'team.users',
            'team.members',
            'tournamentCategory.category',
        ]);

        $category = $registration->tournamentCategory;
        if (! $category) {
            throw ValidationException::withMessages([
                'registration' => 'La inscripción no tiene categoría asociada.',
            ]);
        }

        $normalized = collect($rankingsData)
            ->keyBy(fn ($item) => (int) ($item['slot'] ?? 0));

        $slotOneValue = $normalized->get(1)['ranking_value'] ?? null;
        $slotTwoValue = $normalized->get(2)['ranking_value'] ?? null;

        if (! $registration->is_wildcard) {
            if (! $slotOneValue || ! $slotTwoValue) {
                throw ValidationException::withMessages([
                    'rankings' => 'Debes ingresar ranking para ambos jugadores.',
                ]);
            }

            if ((int) $slotOneValue === (int) $slotTwoValue) {
                throw ValidationException::withMessages([
                    'rankings' => 'No puede haber dos jugadores con el mismo ranking en la categoría.',
                ]);
            }
        }

        $rankingValues = $normalized
            ->pluck('ranking_value')
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->values();

        if ($rankingValues->isNotEmpty()) {
            $exists = RegistrationRanking::query()
                ->where('tournament_category_id', $registration->tournament_category_id)
                ->where('registration_id', '!=', $registration->id)
                ->whereIn('ranking_value', $rankingValues)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'rankings' => 'Uno de los rankings ya está ocupado en esta categoría.',
                ]);
            }
        }

        DB::transaction(function () use ($registration, $normalized, $category, $actor) {
            $defaultSource = $this->defaultRankingSourceForCategory($category);

            foreach ([1, 2] as $slot) {
                $input = $normalized->get($slot);
                if (! is_array($input)) {
                    continue;
                }

                $ranking = $registration->rankings->firstWhere('slot', $slot);
                if (! $ranking) {
                    $ranking = new RegistrationRanking([
                        'registration_id' => $registration->id,
                        'tournament_category_id' => $registration->tournament_category_id,
                        'slot' => $slot,
                    ]);
                }

                $value = array_key_exists('ranking_value', $input) && $input['ranking_value'] !== null && $input['ranking_value'] !== ''
                    ? (int) $input['ranking_value']
                    : null;
                $inputSource = strtoupper((string) ($input['ranking_source'] ?? $ranking->ranking_source ?? $defaultSource));
                $this->assertRankingSourceMatchesCategory($inputSource, $category, 'rankings');
                $source = $value ? $inputSource : null;

                if ($value) {
                    $this->enforceRankingRange($value, $source, $category, 'rankings');
                }

                $ranking->ranking_value = $value;
                $ranking->ranking_source = $source;

                $isVerified = (bool) ($input['is_verified'] ?? false);
                $ranking->is_verified = $isVerified;
                if ($isVerified) {
                    $ranking->verified_at = $ranking->verified_at ?: now();
                    $ranking->verified_by_user_id = $actor?->id ?: $ranking->verified_by_user_id;
                } else {
                    $ranking->verified_at = null;
                    $ranking->verified_by_user_id = null;
                }

                if (! $ranking->user_id) {
                    $ranking->user_id = $registration->team?->members?->firstWhere('slot', $slot)?->user_id;
                }

                $ranking->save();
            }
        });

        if (! $registration->is_wildcard) {
            $this->validateLowerCategoryDrawEligibility($registration->team, $category);
        }

        $this->acceptanceService->recalculateForTournamentCategory($registration->tournament_category_id);

        return $registration->fresh([
            'status',
            'team.users.playerProfile',
            'rankings.user.playerProfile',
            'rankings.verifier',
            'tournamentCategory.tournament.status',
            'tournamentCategory.category',
        ]);
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

    private function validateGlobalRanking(
        TournamentCategory $category,
        int $selfRanking,
        int $partnerRanking,
        string $selfSource,
        string $partnerSource
    ): void
    {
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

    private function validateLowerCategoryDrawEligibility(Team $team, TournamentCategory $category): void
    {
        $category->loadMissing('category');
        $group = $category->category?->group_code;
        $currentOrder = $category->category?->sort_order;
        $drawSize = (int) ($category->max_teams ?? 0);

        if (! $group || $currentOrder === null || $drawSize <= 0) {
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
            $this->assertUserEligibleForLowerCategoryDraw($member, $higherCategoryIds, $drawSize);
        }
    }

    private function assertUserEligibleForLowerCategoryDraw(User $user, $higherCategoryIds, int $drawSize): void
    {
        $bestHigherCategoryRanking = RegistrationRanking::query()
            ->whereIn('tournament_category_id', $higherCategoryIds)
            ->where('user_id', $user->id)
            ->whereNotNull('ranking_value')
            ->min('ranking_value');

        if ($bestHigherCategoryRanking === null) {
            return;
        }

        if ((int) $bestHigherCategoryRanking <= $drawSize) {
            throw ValidationException::withMessages([
                'ranking' => "No elegible: para draw {$drawSize} necesitas ranking mayor a {$drawSize}. Tu ranking es {$bestHigherCategoryRanking}.",
            ]);
        }
    }

    private function defaultRankingSourceForCategory(TournamentCategory $category): string
    {
        $levelCode = strtolower((string) ($category->category?->level_code ?? ''));

        return $levelCode === 'open' ? 'FIP' : 'FEP';
    }

    /**
     * @return array<int, string>
     */
    private function allowedRankingSourcesForCategory(TournamentCategory $category): array
    {
        $levelCode = strtolower((string) ($category->category?->level_code ?? ''));

        return $levelCode === 'open' ? ['FIP', 'FEP'] : ['FEP'];
    }

    private function assertRankingSourceMatchesCategory(string $source, TournamentCategory $category, string $field): void
    {
        $allowed = $this->allowedRankingSourcesForCategory($category);
        if (in_array($source, $allowed, true)) {
            return;
        }

        $levelCode = strtolower((string) ($category->category?->level_code ?? ''));
        $label = $levelCode === 'open' ? 'categoría Open' : 'categorías no Open';
        $expectedText = $levelCode === 'open' ? 'FIP o FEP' : 'FEP';

        throw ValidationException::withMessages([
            $field => "Fuente de ranking inválida: para {$label} debes usar {$expectedText}.",
        ]);
    }
}
