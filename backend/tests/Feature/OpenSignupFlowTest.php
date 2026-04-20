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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OpenSignupFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
    }

    public function test_open_referee_assigned_signup_creates_open_entry_instead_of_registration(): void
    {
        $captain = $this->makePlayer('captain-open-signup@test.dev');
        $tournament = $this->makeOpenTournament(Tournament::CLASSIFICATION_REFEREE_ASSIGNED);

        Sanctum::actingAs($captain);

        $this->postJson('/api/player/registrations', [
            'tournament_id' => $tournament->id,
            'segment' => OpenEntry::SEGMENT_MEN,
            'partner_email' => 'partner-open-signup@test.dev',
            'partner_first_name' => 'Partner',
            'partner_last_name' => 'Signup',
            'partner_dni' => 'V-10002001',
        ])->assertOk()
            ->assertJsonPath('tournament.id', $tournament->id)
            ->assertJsonPath('assignment_status', OpenEntry::ASSIGNMENT_PENDING)
            ->assertJsonPath('partner_email', 'partner-open-signup@test.dev');

        $entry = OpenEntry::query()->with(['team.members'])->firstOrFail();

        $this->assertSame(0, Registration::query()->count());
        $this->assertSame(OpenEntry::SEGMENT_MEN, $entry->segment);
        $this->assertSame('partner-open-signup@test.dev', $entry->partner_email);
        $this->assertSame(1, $entry->team->members->count());
        $this->assertSame(TeamMember::ROLE_CAPTAIN, $entry->team->members->first()?->role);
    }

    public function test_open_referee_assigned_signup_returns_existing_open_entry_for_same_captain_and_partner(): void
    {
        $captain = $this->makePlayer('captain-open-duplicate@test.dev');
        $tournament = $this->makeOpenTournament(Tournament::CLASSIFICATION_REFEREE_ASSIGNED);

        Sanctum::actingAs($captain);

        $payload = [
            'tournament_id' => $tournament->id,
            'segment' => OpenEntry::SEGMENT_WOMEN,
            'partner_email' => 'partner-open-duplicate@test.dev',
            'partner_first_name' => 'Partner',
            'partner_last_name' => 'Duplicate',
            'partner_dni' => 'V-10002002',
        ];

        $firstId = $this->postJson('/api/player/registrations', $payload)->json('id');
        $secondId = $this->postJson('/api/player/registrations', $payload)->json('id');

        $this->assertSame($firstId, $secondId);
        $this->assertSame(1, OpenEntry::query()->count());
        $this->assertSame(1, Team::query()->count());
    }

    public function test_amateur_signup_flow_creates_registration_without_rankings(): void
    {
        $captain = $this->makePlayer('captain-non-open@test.dev');
        $category = $this->makeTournamentCategory('amateur');

        Sanctum::actingAs($captain);

        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => 'partner-non-open@test.dev',
        ])->assertOk()
            ->assertJsonPath('tournament_category.id', $category->id);

        $registration = Registration::query()->with('rankings')->firstOrFail();
        $this->assertSame(1, Registration::query()->count());
        $this->assertTrue($registration->rankings->every(fn ($ranking) => $ranking->ranking_value === null));
        $this->assertSame(0, OpenEntry::query()->count());
    }

    public function test_open_referee_assigned_signup_requires_segment_and_partner_snapshot(): void
    {
        $captain = $this->makePlayer('captain-open-invalid@test.dev');
        $tournament = $this->makeOpenTournament(Tournament::CLASSIFICATION_REFEREE_ASSIGNED);

        Sanctum::actingAs($captain);

        $this->postJson('/api/player/registrations', [
            'tournament_id' => $tournament->id,
            'partner_email' => 'partner-open-invalid@test.dev',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'segment',
                'partner_first_name',
                'partner_last_name',
                'partner_dni',
            ]);
    }

    public function test_open_signup_rejects_non_open_tournament_id(): void
    {
        $captain = $this->makePlayer('captain-open-non-open@test.dev');
        $tournament = Tournament::query()->create([
            'name' => 'Amateur Tournament',
            'mode' => 'amateur',
            'classification_method' => Tournament::CLASSIFICATION_SELF_SELECTED,
            'status_id' => $this->statusId('tournament', 'registration_open'),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'registration_close_at' => now()->addDays(2),
        ]);

        Sanctum::actingAs($captain);

        $this->postJson('/api/player/registrations', [
            'tournament_id' => $tournament->id,
            'segment' => OpenEntry::SEGMENT_MEN,
            'partner_email' => 'partner-open-non-open@test.dev',
            'partner_first_name' => 'Partner',
            'partner_last_name' => 'WrongTournament',
            'partner_dni' => 'V-10002003',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['tournament_id']);
    }

    public function test_open_signup_rejects_self_selected_open_tournament(): void
    {
        $captain = $this->makePlayer('captain-open-self-selected@test.dev');
        $tournament = $this->makeOpenTournament(Tournament::CLASSIFICATION_SELF_SELECTED);

        Sanctum::actingAs($captain);

        $this->postJson('/api/player/registrations', [
            'tournament_id' => $tournament->id,
            'segment' => OpenEntry::SEGMENT_MEN,
            'partner_email' => 'partner-open-self-selected@test.dev',
            'partner_first_name' => 'Partner',
            'partner_last_name' => 'SelfSelected',
            'partner_dni' => 'V-10002004',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['tournament_id']);
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

    private function makeOpenTournament(string $classificationMethod): Tournament
    {
        return Tournament::query()->create([
            'name' => 'Open Signup Tournament',
            'mode' => 'open',
            'classification_method' => $classificationMethod,
            'status_id' => $this->statusId('tournament', 'registration_open'),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'registration_close_at' => now()->addDays(2),
        ]);
    }

    private function makeTournamentCategory(string $mode): TournamentCategory
    {
        $category = Category::query()->create([
            'name' => 'Masculino 2da',
            'group_code' => 'masculino',
            'level_code' => $mode === 'open' ? 'open' : 'segunda',
            'display_name' => $mode === 'open' ? 'Open' : 'Masculino 2da',
            'sort_order' => 1,
        ]);

        $tournament = Tournament::query()->create([
            'name' => 'Category Registration Tournament',
            'mode' => $mode,
            'classification_method' => Tournament::CLASSIFICATION_SELF_SELECTED,
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

    private function statusId(string $module, string $code): int
    {
        return (int) Status::query()
            ->where('module', $module)
            ->where('code', $code)
            ->value('id');
    }
}
