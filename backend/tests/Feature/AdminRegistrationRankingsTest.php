<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Registration;
use App\Models\RegistrationRanking;
use App\Models\Status;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRegistrationRankingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_and_verify_registration_rankings(): void
    {
        $this->seed(StatusSeeder::class);

        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@test.dev',
            'password_hash' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $playerA = User::query()->create([
            'name' => 'Player A',
            'email' => 'a@test.dev',
            'password_hash' => bcrypt('secret'),
            'role' => 'player',
            'is_active' => true,
        ]);
        $playerB = User::query()->create([
            'name' => 'Player B',
            'email' => 'b@test.dev',
            'password_hash' => bcrypt('secret'),
            'role' => 'player',
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Masculino Open',
            'display_name' => 'Masculino Open',
            'group_code' => 'masculino',
            'level_code' => 'open',
            'sort_order' => 1,
        ]);

        $tournamentStatusId = (int) Status::query()->where('module', 'tournament')->where('code', 'registration_open')->value('id');
        $registrationStatusId = (int) Status::query()->where('module', 'registration')->where('code', 'pending')->value('id');

        $tournament = Tournament::query()->create([
            'name' => 'Test',
            'mode' => 'amateur',
            'status_id' => $tournamentStatusId,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'created_by' => $admin->id,
        ]);

        $tournamentCategory = TournamentCategory::query()->create([
            'tournament_id' => $tournament->id,
            'category_id' => $category->id,
            'max_teams' => 32,
            'wildcard_slots' => 0,
            'entry_fee_amount' => 20,
            'currency' => 'EUR',
            'acceptance_type' => 'waitlist',
            'seeding_rule' => 'ranking_desc',
            'status_id' => null,
        ]);

        $team = Team::query()->create([
            'display_name' => 'A / B',
            'created_by' => $playerA->id,
        ]);

        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $playerA->id, 'slot' => 1]);
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $playerB->id, 'slot' => 2]);

        $registration = Registration::query()->create([
            'tournament_category_id' => $tournamentCategory->id,
            'team_id' => $team->id,
            'status_id' => $registrationStatusId,
            'is_wildcard' => false,
            'wildcard_fee_waived' => false,
        ]);

        RegistrationRanking::query()->create([
            'registration_id' => $registration->id,
            'tournament_category_id' => $tournamentCategory->id,
            'slot' => 1,
            'user_id' => $playerA->id,
            'ranking_value' => 60,
            'ranking_source' => 'FEP',
        ]);

        RegistrationRanking::query()->create([
            'registration_id' => $registration->id,
            'tournament_category_id' => $tournamentCategory->id,
            'slot' => 2,
            'user_id' => $playerB->id,
            'ranking_value' => 80,
            'ranking_source' => 'FEP',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/registrations/{$registration->id}/rankings", [
            'rankings' => [
                ['slot' => 1, 'ranking_value' => 70, 'ranking_source' => 'FEP', 'is_verified' => true],
                ['slot' => 2, 'ranking_value' => 95, 'ranking_source' => 'FEP', 'is_verified' => false],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('registration_rankings', [
            'registration_id' => $registration->id,
            'slot' => 1,
            'ranking_value' => 70,
            'is_verified' => true,
            'verified_by_user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('registration_rankings', [
            'registration_id' => $registration->id,
            'slot' => 2,
            'ranking_value' => 95,
            'is_verified' => false,
            'verified_by_user_id' => null,
        ]);
    }
}
