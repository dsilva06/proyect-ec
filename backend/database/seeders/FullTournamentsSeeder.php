<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Circuit;
use App\Models\PlayerProfile;
use App\Models\Registration;
use App\Models\RegistrationRanking;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Support\StatusResolver;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FullTournamentsSeeder extends Seeder
{
    private const TEAMS_PER_CATEGORY = 32;

    private const WILDCARDS_PER_CATEGORY = 4;

    private const USER_EMAIL_PREFIX = 'fullseed';

    private const TEAM_NAME_PREFIX = '[SEED FULL]';

    public function run(): void
    {
        $this->call(StatusSeeder::class);
        $this->call(CircuitSeeder::class);
        $this->ensureCategories();

        $tournamentStatusId = StatusResolver::getId('tournament', 'registration_open')
            ?? StatusResolver::getId('tournament', 'published');
        $registrationStatusId = StatusResolver::getId('registration', 'accepted')
            ?? StatusResolver::getId('registration', 'paid');

        if (! $tournamentStatusId || ! $registrationStatusId) {
            throw new \RuntimeException('Faltan status requeridos. Ejecuta StatusSeeder.');
        }

        $categories = Category::query()->orderBy('sort_order')->orderBy('name')->get();
        if ($categories->isEmpty()) {
            throw new \RuntimeException('No hay categorias para sembrar torneos.');
        }

        $circuitId = Circuit::query()->value('id');
        $adminId = User::query()->where('email', 'admin@proyect-ec.test')->value('id');

        $definitions = [
            [
                'order' => 1,
                'name' => 'Estars Padel Tour - Apertura',
                'mode' => 'pro',
                'city' => 'Caracas',
                'province_state' => 'Distrito Capital',
                'country' => 'Venezuela',
                'venue_name' => 'Estars Club Norte',
                'venue_address' => 'Av. Principal Norte, Caracas',
                'start_date' => Carbon::now()->addDays(21)->startOfDay(),
            ],
            [
                'order' => 2,
                'name' => 'Estars Padel Tour - Clausura',
                'mode' => 'amateur',
                'city' => 'Valencia',
                'province_state' => 'Carabobo',
                'country' => 'Venezuela',
                'venue_name' => 'Estars Club Centro',
                'venue_address' => 'Calle 120, Valencia',
                'start_date' => Carbon::now()->addDays(56)->startOfDay(),
            ],
        ];

        $this->cleanupPreviousSeed($definitions);

        foreach ($definitions as $definition) {
            DB::transaction(function () use ($definition, $categories, $circuitId, $adminId, $tournamentStatusId, $registrationStatusId): void {
                $start = Carbon::parse($definition['start_date']);
                $end = $start->copy()->addDays(2);

                $tournament = Tournament::create([
                    'circuit_id' => $circuitId,
                    'name' => $definition['name'],
                    'description' => 'Torneo sembrado automaticamente con todas las categorias completas.',
                    'mode' => $definition['mode'],
                    'status_id' => $tournamentStatusId,
                    'venue_name' => $definition['venue_name'],
                    'venue_address' => $definition['venue_address'],
                    'city' => $definition['city'],
                    'province_state' => $definition['province_state'],
                    'country' => $definition['country'],
                    'timezone' => 'America/Caracas',
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'entry_fee_amount' => 30,
                    'entry_fee_currency' => 'EUR',
                    'registration_open_at' => $start->copy()->subDays(20),
                    'registration_close_at' => $start->copy()->subDays(1),
                    'day_start_time' => '08:00',
                    'day_end_time' => '22:00',
                    'match_duration_minutes' => 90,
                    'courts_count' => 6,
                    'created_by' => $adminId,
                ]);

                foreach ($categories as $category) {
                    $tournamentCategory = TournamentCategory::create([
                        'tournament_id' => $tournament->id,
                        'category_id' => $category->id,
                        'max_teams' => self::TEAMS_PER_CATEGORY,
                        'wildcard_slots' => self::WILDCARDS_PER_CATEGORY,
                        'entry_fee_amount' => 30,
                        'currency' => 'EUR',
                        'acceptance_type' => 'waitlist',
                        'acceptance_window_hours' => 24,
                        'seeding_rule' => 'ranking_desc',
                    ]);

                    $this->seedCategoryRegistrations(
                        $definition['order'],
                        $category,
                        $tournamentCategory,
                        $registrationStatusId,
                        $adminId
                    );
                }
            });
        }
    }

    private function ensureCategories(): void
    {
        $categories = [
            [
                'name' => 'Masculino Open',
                'display_name' => 'Masculino Open',
                'group_code' => 'masculino',
                'level_code' => 'open',
                'sort_order' => 1,
            ],
            [
                'name' => 'Masculino 1era',
                'display_name' => 'Masculino 1era',
                'group_code' => 'masculino',
                'level_code' => 'primera',
                'sort_order' => 2,
            ],
            [
                'name' => 'Masculino 2da',
                'display_name' => 'Masculino 2da',
                'group_code' => 'masculino',
                'level_code' => 'segunda',
                'sort_order' => 3,
            ],
            [
                'name' => 'Femenino Open',
                'display_name' => 'Femenino Open',
                'group_code' => 'femenino',
                'level_code' => 'open',
                'sort_order' => 4,
            ],
            [
                'name' => 'Femenino 1era',
                'display_name' => 'Femenino 1era',
                'group_code' => 'femenino',
                'level_code' => 'primera',
                'sort_order' => 5,
            ],
            [
                'name' => 'Femenino 2da',
                'display_name' => 'Femenino 2da',
                'group_code' => 'femenino',
                'level_code' => 'segunda',
                'sort_order' => 6,
            ],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }

    private function cleanupPreviousSeed(array $definitions): void
    {
        $tournamentNames = collect($definitions)
            ->pluck('name')
            ->values()
            ->all();

        Tournament::query()
            ->whereIn('name', $tournamentNames)
            ->get()
            ->each
            ->delete();

        Team::query()
            ->where('display_name', 'like', self::TEAM_NAME_PREFIX.'%')
            ->delete();

        User::query()
            ->where('email', 'like', self::USER_EMAIL_PREFIX.'.%@proyect-ec.test')
            ->delete();
    }

    private function seedCategoryRegistrations(
        int $tournamentOrder,
        Category $category,
        TournamentCategory $tournamentCategory,
        int $registrationStatusId,
        ?int $adminId
    ): void {
        $source = strtolower((string) $category->level_code) === 'open' ? 'FIP' : 'FEP';
        $regularPairIndex = 0;

        for ($teamNumber = 1; $teamNumber <= self::TEAMS_PER_CATEGORY; $teamNumber++) {
            $isWildcard = $teamNumber <= self::WILDCARDS_PER_CATEGORY;
            if (! $isWildcard) {
                $regularPairIndex++;
            }

            $userA = $this->upsertSeedUser($tournamentOrder, (int) $category->id, $teamNumber, 'a');
            $userB = $this->upsertSeedUser($tournamentOrder, (int) $category->id, $teamNumber, 'b');

            $team = Team::create([
                'display_name' => sprintf(
                    '%s T%d C%d Equipo %02d',
                    self::TEAM_NAME_PREFIX,
                    $tournamentOrder,
                    (int) $category->id,
                    $teamNumber
                ),
                'created_by' => $userA->id,
            ]);

            TeamMember::create([
                'team_id' => $team->id,
                'user_id' => $userA->id,
                'slot' => 1,
                'role' => \App\Models\TeamMember::ROLE_CAPTAIN,
            ]);
            TeamMember::create([
                'team_id' => $team->id,
                'user_id' => $userB->id,
                'slot' => 2,
                'role' => \App\Models\TeamMember::ROLE_PARTNER,
            ]);

            $rankingA = $isWildcard ? null : (($regularPairIndex * 2) - 1);
            $rankingB = $isWildcard ? null : ($regularPairIndex * 2);
            $teamScore = ($rankingA !== null && $rankingB !== null)
                ? (int) floor(($rankingA + $rankingB) / 2)
                : null;

            $registration = Registration::create([
                'tournament_category_id' => $tournamentCategory->id,
                'team_id' => $team->id,
                'status_id' => $registrationStatusId,
                'queue_position' => null,
                'seed_number' => $isWildcard ? null : $regularPairIndex,
                'team_ranking_score' => $teamScore,
                'is_wildcard' => $isWildcard,
                'wildcard_fee_waived' => $isWildcard && $teamNumber % 2 === 0,
                'accepted_at' => now(),
            ]);

            RegistrationRanking::create([
                'registration_id' => $registration->id,
                'tournament_category_id' => $tournamentCategory->id,
                'slot' => 1,
                'user_id' => $userA->id,
                'ranking_value' => $rankingA,
                'ranking_source' => $rankingA !== null ? $source : null,
                'is_verified' => ! $isWildcard,
                'verified_at' => ! $isWildcard ? now() : null,
                'verified_by_user_id' => ! $isWildcard ? $adminId : null,
            ]);

            RegistrationRanking::create([
                'registration_id' => $registration->id,
                'tournament_category_id' => $tournamentCategory->id,
                'slot' => 2,
                'user_id' => $userB->id,
                'ranking_value' => $rankingB,
                'ranking_source' => $rankingB !== null ? $source : null,
                'is_verified' => ! $isWildcard,
                'verified_at' => ! $isWildcard ? now() : null,
                'verified_by_user_id' => ! $isWildcard ? $adminId : null,
            ]);
        }
    }

    private function upsertSeedUser(int $tournamentOrder, int $categoryId, int $teamNumber, string $slot): User
    {
        $email = sprintf(
            '%s.t%d.c%d.e%02d%s@proyect-ec.test',
            self::USER_EMAIL_PREFIX,
            $tournamentOrder,
            $categoryId,
            $teamNumber,
            $slot
        );

        $name = sprintf(
            'Seed T%d C%d Equipo %02d%s',
            $tournamentOrder,
            $categoryId,
            $teamNumber,
            strtoupper($slot)
        );

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password_hash' => Hash::make('password'),
                'role' => 'player',
                'is_active' => true,
            ]
        );

        PlayerProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => 'Jugador',
                'last_name' => $name,
                'province_state' => 'Caracas',
                'country' => 'Venezuela',
                'ranking_source' => 'NONE',
                'ranking_value' => null,
                'ranking_updated_at' => null,
            ]
        );

        return $user;
    }
}
