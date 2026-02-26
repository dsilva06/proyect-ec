<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Circuit;
use App\Models\Registration;
use App\Models\RegistrationRanking;
use App\Models\Status;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Services\RegistrationService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegistrationDrawEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_blocks_when_superior_ranking_31_tries_draw_32(): void
    {
        [$captain, $partner, $higherCategory, $targetCategory] = $this->buildScenario(32);
        $this->createHigherCategoryRanking($captain, $higherCategory, 31);

        $team = $this->createTeam($captain, $partner);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('draw 32');

        app(RegistrationService::class)->create(
            $captain,
            $team->id,
            $targetCategory->id,
            [
                'self_ranking_value' => 50,
                'partner_ranking_value' => 80,
            ],
        );
    }

    public function test_it_allows_when_superior_ranking_33_tries_draw_32(): void
    {
        [$captain, $partner, $higherCategory, $targetCategory] = $this->buildScenario(32);
        $this->createHigherCategoryRanking($captain, $higherCategory, 33);

        $team = $this->createTeam($captain, $partner);

        $registration = app(RegistrationService::class)->create(
            $captain,
            $team->id,
            $targetCategory->id,
            [
                'self_ranking_value' => 50,
                'partner_ranking_value' => 80,
            ],
        );

        $this->assertNotNull($registration->id);
        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'tournament_category_id' => $targetCategory->id,
        ]);
    }

    public function test_it_blocks_when_superior_ranking_33_tries_draw_64(): void
    {
        [$captain, $partner, $higherCategory, $targetCategory] = $this->buildScenario(64);
        $this->createHigherCategoryRanking($captain, $higherCategory, 33);

        $team = $this->createTeam($captain, $partner);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('draw 64');

        app(RegistrationService::class)->create(
            $captain,
            $team->id,
            $targetCategory->id,
            [
                'self_ranking_value' => 90,
                'partner_ranking_value' => 120,
            ],
        );
    }

    public function test_it_blocks_pair_when_one_player_fails_draw_rule(): void
    {
        [$captain, $partner, $higherCategory, $targetCategory] = $this->buildScenario(32);
        $this->createHigherCategoryRanking($captain, $higherCategory, 70);
        $this->createHigherCategoryRanking($partner, $higherCategory, 31);

        $team = $this->createTeam($captain, $partner);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('draw 32');

        app(RegistrationService::class)->create(
            $captain,
            $team->id,
            $targetCategory->id,
            [
                'self_ranking_value' => 100,
                'partner_ranking_value' => 110,
            ],
        );
    }

    public function test_it_allows_pair_when_both_players_pass_draw_rule(): void
    {
        [$captain, $partner, $higherCategory, $targetCategory] = $this->buildScenario(32);
        $this->createHigherCategoryRanking($captain, $higherCategory, 70);
        $this->createHigherCategoryRanking($partner, $higherCategory, 33);

        $team = $this->createTeam($captain, $partner);

        $registration = app(RegistrationService::class)->create(
            $captain,
            $team->id,
            $targetCategory->id,
            [
                'self_ranking_value' => 100,
                'partner_ranking_value' => 110,
            ],
        );

        $this->assertNotNull($registration->id);
        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'tournament_category_id' => $targetCategory->id,
        ]);
    }

    private function buildScenario(int $drawSize): array
    {
        $this->seed(StatusSeeder::class);
        $this->seed(CategorySeeder::class);

        $captain = User::factory()->create(['role' => 'player']);
        $partner = User::factory()->create(['role' => 'player']);

        $tournamentStatusId = (int) Status::query()
            ->where('module', 'tournament')
            ->where('code', 'registration_open')
            ->value('id');

        $circuit = Circuit::create(['name' => 'Test Circuit']);
        $tournament = Tournament::create([
            'circuit_id' => $circuit->id,
            'name' => 'Test Tournament',
            'mode' => 'pro',
            'status_id' => $tournamentStatusId,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(12)->toDateString(),
        ]);

        $higherCategoryDefinition = Category::query()
            ->where('group_code', 'masculino')
            ->where('level_code', 'primera')
            ->firstOrFail();
        $targetCategoryDefinition = Category::query()
            ->where('group_code', 'masculino')
            ->where('level_code', 'segunda')
            ->firstOrFail();

        $higherCategory = TournamentCategory::create([
            'tournament_id' => $tournament->id,
            'category_id' => $higherCategoryDefinition->id,
            'max_teams' => 32,
            'wildcard_slots' => 0,
            'entry_fee_amount' => 20,
            'currency' => 'USD',
            'acceptance_type' => 'waitlist',
            'seeding_rule' => 'ranking_desc',
        ]);

        $targetCategory = TournamentCategory::create([
            'tournament_id' => $tournament->id,
            'category_id' => $targetCategoryDefinition->id,
            'max_teams' => $drawSize,
            'wildcard_slots' => 0,
            'entry_fee_amount' => 20,
            'currency' => 'USD',
            'acceptance_type' => 'waitlist',
            'seeding_rule' => 'ranking_desc',
        ]);

        return [$captain, $partner, $higherCategory, $targetCategory];
    }

    private function createTeam(User $captain, User $partner): Team
    {
        $team = Team::create([
            'display_name' => "{$captain->name} / {$partner->name}",
            'created_by' => $captain->id,
        ]);

        $team->users()->attach($captain->id, ['slot' => 1]);
        $team->users()->attach($partner->id, ['slot' => 2]);

        return $team;
    }

    private function createHigherCategoryRanking(User $user, TournamentCategory $higherCategory, int $ranking): void
    {
        $registrationStatusId = (int) Status::query()
            ->where('module', 'registration')
            ->where('code', 'pending')
            ->value('id');

        $partner = User::factory()->create(['role' => 'player']);
        $team = $this->createTeam($user, $partner);

        $registration = Registration::create([
            'tournament_category_id' => $higherCategory->id,
            'team_id' => $team->id,
            'status_id' => $registrationStatusId,
        ]);

        RegistrationRanking::create([
            'registration_id' => $registration->id,
            'tournament_category_id' => $higherCategory->id,
            'slot' => 1,
            'user_id' => $user->id,
            'ranking_value' => $ranking,
            'ranking_source' => 'NONE',
        ]);

        RegistrationRanking::create([
            'registration_id' => $registration->id,
            'tournament_category_id' => $higherCategory->id,
            'slot' => 2,
            'user_id' => $partner->id,
            'ranking_value' => $ranking + 200,
            'ranking_source' => 'NONE',
        ]);
    }
}
