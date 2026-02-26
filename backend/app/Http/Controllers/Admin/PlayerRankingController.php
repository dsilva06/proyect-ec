<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternalRankingRule;
use App\Models\PlayerPrizePayout;
use App\Models\Registration;
use App\Models\TournamentCategory;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\AcceptanceService;
use App\Services\RankingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlayerRankingController extends Controller
{
    public function index(Request $request)
    {
        $categoryId = $request->filled('category_id') ? (int) $request->query('category_id') : null;

        $query = User::query()
            ->where('role', 'player')
            ->with('playerProfile')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->whereHas('teams.registrations.tournamentCategory', function ($builder) use ($categoryId) {
                $builder->where('category_id', $categoryId);
            });
        }

        $players = $query->get();

        $stats = $this->collectStats($categoryId);
        $leaderboard = $categoryId ? $this->buildLeaderboard($stats['participant_ids'], $stats['points']) : [];

        return response()->json($players->map(function (User $player) use ($stats, $leaderboard, $categoryId) {
            $profile = $player->playerProfile;
            $isFep = strtoupper((string) ($profile?->ranking_source ?: '')) === 'FEP';

            return [
                'id' => $player->id,
                'name' => $player->name,
                'email' => $player->email,
                'phone' => $player->phone,
                'is_active' => (bool) $player->is_active,
                'ranking_fep_value' => $isFep ? $profile?->ranking_value : null,
                'ranking_fep_updated_at' => optional($profile?->ranking_updated_at)->toIso8601String(),
                'internal_points' => $categoryId ? ($stats['points'][$player->id] ?? 0) : null,
                'internal_rank' => $categoryId ? ($leaderboard[$player->id] ?? null) : null,
                'tournaments_played' => $categoryId ? ($stats['tournaments_played'][$player->id] ?? 0) : null,
                'matches_played' => $categoryId ? ($stats['matches_played'][$player->id] ?? 0) : null,
                'matches_won' => $categoryId ? ($stats['matches_won'][$player->id] ?? 0) : null,
                'finals_played' => $categoryId ? ($stats['finals_played'][$player->id] ?? 0) : null,
                'finals_won' => $categoryId ? ($stats['finals_won'][$player->id] ?? 0) : null,
                'prize_total_eur_cents' => $stats['prize_totals'][$player->id] ?? 0,
            ];
        }));
    }

    public function showPalmares(Request $request, User $user)
    {
        if ($user->role !== 'player') {
            return response()->json(['message' => 'Jugador no encontrado.'], 404);
        }

        $categoryId = $request->filled('category_id') ? (int) $request->query('category_id') : null;
        $stats = $this->collectStats($categoryId);
        $leaderboard = $categoryId ? $this->buildLeaderboard($stats['participant_ids'], $stats['points']) : [];

        $profile = $user->playerProfile;
        $isFep = strtoupper((string) ($profile?->ranking_source ?: '')) === 'FEP';

        return response()->json([
            'player' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'ranking_fep_value' => $isFep ? $profile?->ranking_value : null,
                'ranking_fep_updated_at' => optional($profile?->ranking_updated_at)->toIso8601String(),
            ],
            'category_id' => $categoryId,
            'internal_rank' => $categoryId ? ($leaderboard[$user->id] ?? null) : null,
            'internal_points' => $categoryId ? ($stats['points'][$user->id] ?? 0) : null,
            'tournaments_played' => $stats['tournaments_played'][$user->id] ?? 0,
            'matches_played' => $stats['matches_played'][$user->id] ?? 0,
            'matches_won' => $stats['matches_won'][$user->id] ?? 0,
            'finals_played' => $stats['finals_played'][$user->id] ?? 0,
            'finals_won' => $stats['finals_won'][$user->id] ?? 0,
            'prize_total_eur_cents' => $stats['prize_totals'][$user->id] ?? 0,
            'rule' => $this->getInternalRule(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'ranking_value' => ['nullable', 'integer', 'min:1'],
            'ranking_source' => ['nullable', 'string', 'in:FEP,FIP,NONE'],
        ]);

        $value = $data['ranking_value'] ?? null;
        $source = strtoupper((string) ($data['ranking_source'] ?? 'FEP'));
        if ($source !== 'FEP') {
            $value = null;
            $source = 'NONE';
        }

        return $this->persistFepRanking($user, $value, $source, $request);
    }

    public function updateFep(Request $request, User $user)
    {
        $data = $request->validate([
            'ranking_fep_value' => ['nullable', 'integer', 'min:1'],
        ]);

        $value = $data['ranking_fep_value'] ?? null;
        $source = $value ? 'FEP' : 'NONE';

        return $this->persistFepRanking($user, $value, $source, $request);
    }

    public function listPrizePayouts(Request $request, User $user)
    {
        if ($user->role !== 'player') {
            return response()->json(['message' => 'Jugador no encontrado.'], 404);
        }

        $query = PlayerPrizePayout::query()
            ->where('user_id', $user->id)
            ->with(['tournament', 'tournamentCategory.category'])
            ->orderByDesc('created_at');

        if ($request->filled('category_id')) {
            $categoryId = (int) $request->query('category_id');
            $query->whereHas('tournamentCategory', function ($builder) use ($categoryId) {
                $builder->where('category_id', $categoryId);
            });
        }

        $items = $query->get();

        return response()->json([
            'items' => $items->map(fn (PlayerPrizePayout $item) => $this->serializePayout($item)),
            'total_eur_cents' => (int) $items->sum('amount_eur_cents'),
        ]);
    }

    public function storePrizePayout(Request $request, User $user)
    {
        if ($user->role !== 'player') {
            return response()->json(['message' => 'Jugador no encontrado.'], 404);
        }

        $data = $request->validate([
            'tournament_id' => ['required', 'exists:tournaments,id'],
            'tournament_category_id' => ['required', 'exists:tournament_categories,id'],
            'position' => ['required', 'in:champion,runner_up,semifinalist'],
            'amount_eur_cents' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $category = TournamentCategory::query()->with('category')->findOrFail((int) $data['tournament_category_id']);
        if ((int) $category->tournament_id !== (int) $data['tournament_id']) {
            throw ValidationException::withMessages([
                'tournament_category_id' => 'La categoría seleccionada no pertenece al torneo indicado.',
            ]);
        }
        $this->validatePrizePosition($category, (string) $data['position']);

        $payout = PlayerPrizePayout::create([
            'user_id' => $user->id,
            'tournament_id' => (int) $data['tournament_id'],
            'tournament_category_id' => (int) $data['tournament_category_id'],
            'position' => (string) $data['position'],
            'amount_eur_cents' => (int) $data['amount_eur_cents'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        $payout->load(['tournament', 'tournamentCategory.category']);

        return response()->json($this->serializePayout($payout), 201);
    }

    public function updatePrizePayout(Request $request, PlayerPrizePayout $playerPrizePayout)
    {
        $data = $request->validate([
            'tournament_id' => ['sometimes', 'exists:tournaments,id'],
            'tournament_category_id' => ['sometimes', 'exists:tournament_categories,id'],
            'position' => ['sometimes', 'in:champion,runner_up,semifinalist'],
            'amount_eur_cents' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $nextTournamentId = (int) ($data['tournament_id'] ?? $playerPrizePayout->tournament_id);
        $nextCategoryId = (int) ($data['tournament_category_id'] ?? $playerPrizePayout->tournament_category_id);
        $nextPosition = (string) ($data['position'] ?? $playerPrizePayout->position);

        $category = TournamentCategory::query()->with('category')->findOrFail($nextCategoryId);
        if ((int) $category->tournament_id !== $nextTournamentId) {
            throw ValidationException::withMessages([
                'tournament_category_id' => 'La categoría seleccionada no pertenece al torneo indicado.',
            ]);
        }
        $this->validatePrizePosition($category, $nextPosition);

        $playerPrizePayout->fill([
            'tournament_id' => $nextTournamentId,
            'tournament_category_id' => $nextCategoryId,
            'position' => $nextPosition,
            'amount_eur_cents' => array_key_exists('amount_eur_cents', $data)
                ? (int) $data['amount_eur_cents']
                : $playerPrizePayout->amount_eur_cents,
            'notes' => array_key_exists('notes', $data) ? ($data['notes'] ?? null) : $playerPrizePayout->notes,
        ]);
        $playerPrizePayout->save();

        $playerPrizePayout->load(['tournament', 'tournamentCategory.category']);

        return response()->json($this->serializePayout($playerPrizePayout));
    }

    public function destroyPrizePayout(PlayerPrizePayout $playerPrizePayout)
    {
        $playerPrizePayout->delete();

        return response()->noContent();
    }

    public function showInternalRule()
    {
        return response()->json($this->getInternalRule());
    }

    public function updateInternalRule(Request $request)
    {
        $data = $request->validate([
            'win_points' => ['required', 'integer', 'min:0'],
            'final_played_bonus' => ['required', 'integer', 'min:0'],
            'final_won_bonus' => ['required', 'integer', 'min:0'],
        ]);

        $rule = InternalRankingRule::query()->firstOrCreate(
            ['id' => 1],
            [
                'win_points' => 10,
                'final_played_bonus' => 5,
                'final_won_bonus' => 8,
            ]
        );

        $rule->fill($data);
        $rule->updated_by = $request->user()?->id;
        $rule->save();

        return response()->json($this->getInternalRule());
    }

    private function persistFepRanking(User $user, ?int $value, string $source, Request $request)
    {
        if ($user->role !== 'player') {
            return response()->json(['message' => 'Solo se permite actualizar rankings de jugadores.'], 404);
        }

        app(RankingService::class)->updateRanking($user, $value, $source);
        app(AcceptanceService::class)->recalculateForUser($user);

        $user->load('playerProfile');
        $profile = $user->playerProfile;

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'ranking_fep_value' => strtoupper((string) ($profile?->ranking_source ?: '')) === 'FEP'
                ? $profile?->ranking_value
                : null,
            'ranking_fep_updated_at' => optional($profile?->ranking_updated_at)->toIso8601String(),
        ]);
    }

    private function getInternalRule(): array
    {
        $rule = InternalRankingRule::query()->firstOrCreate(
            ['id' => 1],
            [
                'win_points' => 10,
                'final_played_bonus' => 5,
                'final_won_bonus' => 8,
                'updated_by' => null,
            ]
        );

        return [
            'id' => $rule->id,
            'win_points' => (int) $rule->win_points,
            'final_played_bonus' => (int) $rule->final_played_bonus,
            'final_won_bonus' => (int) $rule->final_won_bonus,
            'updated_by' => $rule->updated_by,
            'updated_at' => optional($rule->updated_at)->toIso8601String(),
        ];
    }

    private function collectStats(?int $categoryId): array
    {
        $rule = $this->getInternalRule();

        $registrationsQuery = Registration::query()
            ->with(['team.users:id', 'tournamentCategory:id,tournament_id,category_id']);

        if ($categoryId) {
            $registrationsQuery->whereHas('tournamentCategory', function ($builder) use ($categoryId) {
                $builder->where('category_id', $categoryId);
            });
        }

        $registrations = $registrationsQuery->get();

        $registrationUsers = [];
        $participantIds = [];
        $tournamentSets = [];

        foreach ($registrations as $registration) {
            $userIds = $registration->team?->users?->pluck('id')->map(fn ($id) => (int) $id)->all() ?: [];
            $registrationUsers[(int) $registration->id] = $userIds;

            $tournamentId = $registration->tournamentCategory?->tournament_id ? (int) $registration->tournamentCategory->tournament_id : null;
            foreach ($userIds as $userId) {
                $participantIds[$userId] = true;
                if ($tournamentId) {
                    $tournamentSets[$userId][$tournamentId] = true;
                }
            }
        }

        $tournamentCategoryIds = $registrations->pluck('tournament_category_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $matches = collect();

        if ($tournamentCategoryIds->isNotEmpty()) {
            $matches = TournamentMatch::query()
                ->whereIn('tournament_category_id', $tournamentCategoryIds)
                ->whereNotNull('winner_registration_id')
                ->get([
                    'id',
                    'tournament_category_id',
                    'round_number',
                    'registration_a_id',
                    'registration_b_id',
                    'winner_registration_id',
                ]);
        }

        $maxRoundByCategory = $matches
            ->groupBy('tournament_category_id')
            ->map(fn ($group) => (int) $group->max('round_number'));

        $matchesPlayed = [];
        $matchesWon = [];
        $finalsPlayed = [];
        $finalsWon = [];
        $points = [];

        foreach ($matches as $match) {
            $usersA = $registrationUsers[(int) $match->registration_a_id] ?? [];
            $usersB = $registrationUsers[(int) $match->registration_b_id] ?? [];
            $playedUsers = array_values(array_unique(array_merge($usersA, $usersB)));
            $winnerUsers = $registrationUsers[(int) $match->winner_registration_id] ?? [];

            foreach ($playedUsers as $userId) {
                $matchesPlayed[$userId] = ($matchesPlayed[$userId] ?? 0) + 1;
            }

            foreach ($winnerUsers as $userId) {
                $matchesWon[$userId] = ($matchesWon[$userId] ?? 0) + 1;
                $points[$userId] = ($points[$userId] ?? 0) + (int) $rule['win_points'];
            }

            $isFinal = (int) $match->round_number === (int) ($maxRoundByCategory[(int) $match->tournament_category_id] ?? -1);
            if ($isFinal) {
                foreach ($playedUsers as $userId) {
                    $finalsPlayed[$userId] = ($finalsPlayed[$userId] ?? 0) + 1;
                    $points[$userId] = ($points[$userId] ?? 0) + (int) $rule['final_played_bonus'];
                }
                foreach ($winnerUsers as $userId) {
                    $finalsWon[$userId] = ($finalsWon[$userId] ?? 0) + 1;
                    $points[$userId] = ($points[$userId] ?? 0) + (int) $rule['final_won_bonus'];
                }
            }
        }

        $prizeQuery = PlayerPrizePayout::query();
        if ($categoryId) {
            $prizeQuery->whereHas('tournamentCategory', function ($builder) use ($categoryId) {
                $builder->where('category_id', $categoryId);
            });
        }

        $prizeTotals = $prizeQuery
            ->selectRaw('user_id, SUM(amount_eur_cents) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id')
            ->map(fn ($value) => (int) $value)
            ->all();

        $tournamentsPlayed = [];
        foreach ($tournamentSets as $userId => $set) {
            $tournamentsPlayed[$userId] = count($set);
        }

        return [
            'participant_ids' => array_map('intval', array_keys($participantIds)),
            'tournaments_played' => $tournamentsPlayed,
            'matches_played' => $matchesPlayed,
            'matches_won' => $matchesWon,
            'finals_played' => $finalsPlayed,
            'finals_won' => $finalsWon,
            'points' => $points,
            'prize_totals' => $prizeTotals,
        ];
    }

    /**
     * @param  array<int>  $participantIds
     * @param  array<int, int>  $points
     * @return array<int, int>
     */
    private function buildLeaderboard(array $participantIds, array $points): array
    {
        if (empty($participantIds)) {
            return [];
        }

        $names = User::query()
            ->whereIn('id', $participantIds)
            ->pluck('name', 'id')
            ->all();

        usort($participantIds, function ($a, $b) use ($points, $names) {
            $pointsA = (int) ($points[$a] ?? 0);
            $pointsB = (int) ($points[$b] ?? 0);
            if ($pointsA !== $pointsB) {
                return $pointsB <=> $pointsA;
            }
            return strcasecmp((string) ($names[$a] ?? ''), (string) ($names[$b] ?? ''));
        });

        $ranking = [];
        foreach ($participantIds as $index => $userId) {
            $ranking[$userId] = $index + 1;
        }

        return $ranking;
    }

    private function validatePrizePosition(TournamentCategory $category, string $position): void
    {
        $level = strtolower((string) ($category->category?->level_code ?? ''));

        if ($level === 'segunda') {
            return;
        }

        if ($position === 'semifinalist') {
            throw ValidationException::withMessages([
                'position' => 'En esta categoría solo finalistas pueden recibir premios.',
            ]);
        }
    }

    private function serializePayout(PlayerPrizePayout $item): array
    {
        return [
            'id' => $item->id,
            'user_id' => $item->user_id,
            'tournament_id' => $item->tournament_id,
            'tournament_name' => $item->tournament?->name,
            'tournament_category_id' => $item->tournament_category_id,
            'category_name' => $item->tournamentCategory?->category?->display_name
                ?: $item->tournamentCategory?->category?->name,
            'position' => $item->position,
            'amount_eur_cents' => (int) $item->amount_eur_cents,
            'notes' => $item->notes,
            'created_at' => optional($item->created_at)->toIso8601String(),
        ];
    }
}
