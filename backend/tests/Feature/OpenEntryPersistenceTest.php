<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\OpenEntry;
use App\Models\Registration;
use App\Models\Status;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenEntryPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
    }

    public function test_open_entry_persists_pending_open_signup_data_without_category_registration(): void
    {
        $captain = $this->makePlayer('captain-open-entry@test.dev');
        $tournament = $this->makeOpenTournament();
        $team = $this->makeTeamForCaptain($captain);

        $entry = OpenEntry::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'submitted_by_user_id' => $captain->id,
            'segment' => OpenEntry::SEGMENT_MEN,
            'partner_email' => 'partner-open-entry@test.dev',
            'partner_first_name' => 'Partner',
            'partner_last_name' => 'Open',
            'partner_dni' => 'V-10001001',
        ]);

        $entry->load(['tournament', 'team', 'submittedBy', 'registration']);

        $this->assertSame(OpenEntry::ASSIGNMENT_PENDING, $entry->assignment_status);
        $this->assertSame($tournament->id, $entry->tournament?->id);
        $this->assertSame($team->id, $entry->team?->id);
        $this->assertSame($captain->id, $entry->submittedBy?->id);
        $this->assertNull($entry->registration);
        $this->assertSame($entry->id, $tournament->openEntries()->first()?->id);
        $this->assertSame($entry->id, $team->openEntries()->first()?->id);
    }

    public function test_referee_assignment_can_create_real_category_registration_for_open_entry(): void
    {
        $captain = $this->makePlayer('captain-open-assigned@test.dev');
        $referee = $this->makePlayer('referee-open-assigned@test.dev');
        $tournament = $this->makeOpenTournament();
        $category = $this->makeTournamentCategory($tournament);
        $team = $this->makeTeamForCaptain($captain);

        $entry = OpenEntry::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'submitted_by_user_id' => $captain->id,
            'segment' => OpenEntry::SEGMENT_WOMEN,
            'partner_email' => 'partner-open-assigned@test.dev',
            'partner_first_name' => 'Partner',
            'partner_last_name' => 'Assigned',
            'partner_dni' => 'V-10001002',
        ]);

        $this->assertSame(0, Registration::query()->count());
        $this->assertSame(OpenEntry::ASSIGNMENT_PENDING, $entry->assignment_status);

        $registration = Registration::query()->create([
            'tournament_category_id' => $category->id,
            'team_id' => $team->id,
            'status_id' => $this->statusId('registration', 'accepted'),
        ]);

        $entry->forceFill([
            'assignment_status' => OpenEntry::ASSIGNMENT_ASSIGNED,
            'assigned_tournament_category_id' => $category->id,
            'registration_id' => $registration->id,
            'assigned_by_user_id' => $referee->id,
            'assigned_at' => now(),
        ])->save();

        $entry->load(['assignedTournamentCategory', 'registration', 'assignedBy']);
        $registration->load('openEntry');

        $this->assertSame(1, Registration::query()->count());
        $this->assertSame($category->id, $entry->assignedTournamentCategory?->id);
        $this->assertSame($registration->id, $entry->registration?->id);
        $this->assertSame($referee->id, $entry->assignedBy?->id);
        $this->assertSame($entry->id, $registration->openEntry?->id);
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

    private function makeOpenTournament(): Tournament
    {
        return Tournament::query()->create([
            'name' => 'Open Intake Tournament',
            'mode' => 'open',
            'classification_method' => Tournament::CLASSIFICATION_REFEREE_ASSIGNED,
            'status_id' => $this->statusId('tournament', 'registration_open'),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'registration_close_at' => now()->addDays(2),
        ]);
    }

    private function makeTournamentCategory(Tournament $tournament): TournamentCategory
    {
        $category = Category::query()->create([
            'name' => 'Masculino Open',
            'group_code' => 'masculino',
            'level_code' => 'open',
            'display_name' => 'Masculino Open',
            'sort_order' => 1,
        ]);

        return TournamentCategory::query()->create([
            'tournament_id' => $tournament->id,
            'category_id' => $category->id,
            'max_teams' => 32,
            'entry_fee_amount' => 50,
            'currency' => 'USD',
            'acceptance_type' => 'waitlist',
            'seeding_rule' => 'fifo',
        ]);
    }

    private function makeTeamForCaptain(User $captain): Team
    {
        $team = Team::query()->create([
            'display_name' => 'Open Entry Team',
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
