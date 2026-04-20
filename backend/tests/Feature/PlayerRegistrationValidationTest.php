<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Registration;
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

class PlayerRegistrationValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
    }

    public function test_team_creation_flow_requires_partner_email(): void
    {
        $captain = $this->makePlayer('captain-no-partner@test.dev');
        $category = $this->makeTournamentCategory('open');

        Sanctum::actingAs($captain);

        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['partner_email']);
    }

    public function test_team_creation_flow_accepts_partner_email_when_creating_team(): void
    {
        $captain = $this->makePlayer('captain-create-team@test.dev');
        $category = $this->makeTournamentCategory('open');

        Sanctum::actingAs($captain);

        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => 'partner-create-team@test.dev',
        ])->assertOk();
    }

    public function test_amateur_registration_does_not_require_rankings(): void
    {
        $captain = $this->makePlayer('captain-amateur-no-ranking@test.dev');
        $category = $this->makeTournamentCategory('amateur');

        Sanctum::actingAs($captain);

        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => 'partner-amateur-no-ranking@test.dev',
        ])->assertOk()
            ->assertJsonPath('has_ranking', false);
    }

    public function test_pro_registration_still_requires_rankings(): void
    {
        $captain = $this->makePlayer('captain-pro-no-ranking@test.dev');
        $category = $this->makeTournamentCategory('pro');

        Sanctum::actingAs($captain);

        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => 'partner-pro-no-ranking@test.dev',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ranking']);
    }

    public function test_existing_team_flow_allows_missing_partner_email(): void
    {
        $captain = $this->makePlayer('captain-existing-team@test.dev');
        $category = $this->makeTournamentCategory('open');
        $team = $this->makeTeamForCaptain($captain);

        Sanctum::actingAs($captain);

        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'team_id' => $team->id,
        ])->assertOk()
            ->assertJsonPath('team.id', $team->id);

        $this->assertSame(1, Registration::query()->count());
        $this->assertDatabaseHas('registration_rankings', [
            'registration_id' => Registration::query()->firstOrFail()->id,
            'slot' => 2,
            'invited_email' => null,
        ]);
    }

    public function test_existing_team_flow_still_validates_team_id(): void
    {
        $captain = $this->makePlayer('captain-invalid-team@test.dev');
        $category = $this->makeTournamentCategory('open');

        Sanctum::actingAs($captain);

        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'team_id' => 999999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['team_id'])
            ->assertJsonMissingValidationErrors(['partner_email']);
    }

    private function makePlayer(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'role' => 'player',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function makeTournamentCategory(string $mode = 'open'): TournamentCategory
    {
        $category = Category::query()->create([
            'name' => $mode === 'open' ? 'Open' : 'Masculino 2da',
            'group_code' => 'masculino',
            'level_code' => $mode === 'open' ? 'open' : 'segunda',
            'display_name' => $mode === 'open' ? 'Open' : 'Masculino 2da',
            'sort_order' => 1,
        ]);

        $tournament = Tournament::query()->create([
            'name' => 'Validation Tournament',
            'mode' => $mode,
            'status_id' => $this->statusId('tournament', 'registration_open'),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'registration_close_at' => now()->addDays(3),
        ]);

        return TournamentCategory::query()->create([
            'tournament_id' => $tournament->id,
            'category_id' => $category->id,
            'max_teams' => 32,
            'entry_fee_amount' => 50,
            'currency' => 'USD',
            'acceptance_type' => 'waitlist',
            'seeding_rule' => $mode === 'open' ? 'fifo' : 'ranking_desc',
        ]);
    }

    private function makeTeamForCaptain(User $captain): Team
    {
        $team = Team::query()->create([
            'display_name' => 'Existing Team',
            'created_by' => $captain->id,
            'status_id' => $this->statusId('team', Team::STATUS_CONFIRMED),
        ]);

        TeamMember::query()->create([
            'team_id' => $team->id,
            'user_id' => $captain->id,
            'slot' => 1,
            'role' => TeamMember::ROLE_CAPTAIN,
        ]);

        return $team;
    }

    private function statusId(string $module, string $code): int
    {
        return (int) Status::query()
            ->where('module', $module)
            ->where('code', $code)
            ->value('id');
    }
}
