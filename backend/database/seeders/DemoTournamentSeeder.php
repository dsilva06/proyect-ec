<?php

namespace Database\Seeders;

use App\Models\Bracket;
use App\Models\Category;
use App\Models\Circuit;
use App\Models\Registration;
use App\Models\RegistrationRanking;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Services\BracketGenerationService;
use App\Support\StatusResolver;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoTournamentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(StatusSeeder::class);
        $this->call(CircuitSeeder::class);
        $this->call(CategorySeeder::class);

        $tournamentName = 'Demo Cuadro 32 Parejas';
        $categoryName = 'Masculino Open';

        $existing = Tournament::query()->where('name', $tournamentName)->first();
        if ($existing) {
            $existing->delete();
        }

        $circuitId = Circuit::query()->value('id');
        $tournamentStatus = StatusResolver::getId('tournament', 'registration_open')
            ?? StatusResolver::getId('tournament', 'published');
        if (! $tournamentStatus) {
            throw new \RuntimeException('No se encontraron status de torneo. Ejecuta StatusSeeder.');
        }

        $startDate = Carbon::now()->addDays(7)->startOfDay();
        $endDate = $startDate->copy()->addDays(2);

        $tournament = Tournament::create([
            'circuit_id' => $circuitId,
            'name' => $tournamentName,
            'description' => 'Torneo demo con 32 parejas para probar el cuadro.',
            'mode' => 'pro',
            'status_id' => $tournamentStatus,
            'venue_name' => 'Club Demo',
            'venue_address' => 'Calle Principal 123',
            'city' => 'Caracas',
            'province_state' => 'Distrito Capital',
            'country' => 'Venezuela',
            'timezone' => 'America/Caracas',
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'entry_fee_amount' => 20,
            'entry_fee_currency' => 'USD',
            'registration_open_at' => $startDate->copy()->subDays(5),
            'registration_close_at' => $startDate->copy()->subDay(),
            'day_start_time' => '08:00',
            'day_end_time' => '22:00',
            'match_duration_minutes' => 90,
            'courts_count' => 4,
        ]);

        $category = Category::query()
            ->where('name', $categoryName)
            ->first() ?? Category::query()->first();

        if (! $category) {
            $category = Category::create([
                'name' => $categoryName,
                'display_name' => $categoryName,
                'group_code' => 'masculino',
                'level_code' => 'open',
                'sort_order' => 1,
            ]);
        }

        $tournamentCategory = TournamentCategory::create([
            'tournament_id' => $tournament->id,
            'category_id' => $category->id,
            'max_teams' => 32,
            'wildcard_slots' => 0,
            'entry_fee_amount' => 20,
            'currency' => 'USD',
            'acceptance_type' => 'immediate',
            'acceptance_window_hours' => 24,
            'seeding_rule' => 'ranking_desc',
        ]);

        $registrationStatus = StatusResolver::getId('registration', 'paid')
            ?? StatusResolver::getId('registration', 'accepted');
        if (! $registrationStatus) {
            throw new \RuntimeException('No se encontraron status de registro. Ejecuta StatusSeeder.');
        }

        DB::transaction(function () use ($tournamentCategory, $registrationStatus) {
            $rankingValue = 1;

            for ($i = 1; $i <= 32; $i++) {
                $userA = User::updateOrCreate(
                    ['email' => "demo.player{$i}a@proyect-ec.test"],
                    [
                        'name' => "Jugador {$i}A",
                        'password_hash' => Hash::make('password'),
                        'role' => 'player',
                        'is_active' => true,
                    ]
                );

                $userB = User::updateOrCreate(
                    ['email' => "demo.player{$i}b@proyect-ec.test"],
                    [
                        'name' => "Jugador {$i}B",
                        'password_hash' => Hash::make('password'),
                        'role' => 'player',
                        'is_active' => true,
                    ]
                );

                $team = Team::create([
                    'display_name' => "{$userA->name} / {$userB->name}",
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

                $rankingA = $rankingValue++;
                $rankingB = $rankingValue++;
                $teamScore = (int) round(($rankingA + $rankingB) / 2);

                $registration = Registration::create([
                    'tournament_category_id' => $tournamentCategory->id,
                    'team_id' => $team->id,
                    'status_id' => $registrationStatus,
                    'team_ranking_score' => $teamScore,
                    'accepted_at' => now(),
                ]);

                RegistrationRanking::create([
                    'registration_id' => $registration->id,
                    'tournament_category_id' => $tournamentCategory->id,
                    'slot' => 1,
                    'user_id' => $userA->id,
                    'ranking_value' => $rankingA,
                    'ranking_source' => 'NONE',
                ]);

                RegistrationRanking::create([
                    'registration_id' => $registration->id,
                    'tournament_category_id' => $tournamentCategory->id,
                    'slot' => 2,
                    'user_id' => $userB->id,
                    'ranking_value' => $rankingB,
                    'ranking_source' => 'NONE',
                ]);
            }
        });

        $bracketStatus = StatusResolver::getId('bracket', 'published')
            ?? StatusResolver::getId('bracket', 'draft');
        if (! $bracketStatus) {
            throw new \RuntimeException('No se encontraron status de cuadros. Ejecuta StatusSeeder.');
        }

        $bracket = Bracket::create([
            'tournament_category_id' => $tournamentCategory->id,
            'type' => Bracket::TYPE_SINGLE_ELIMINATION,
            'status_id' => $bracketStatus,
            'published_at' => now(),
        ]);

        app(BracketGenerationService::class)->generate($bracket, false);
    }
}
