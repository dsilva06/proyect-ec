<?php

namespace Tests\Feature;

use App\Jobs\SendTeamInviteEmailJob;
use App\Models\Category;
use App\Models\Registration;
use App\Models\RegistrationRanking;
use App\Models\Status;
use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamTournamentRegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
    }

    public function test_captain_creates_pending_team_registration_and_queues_invite_email(): void
    {
        Queue::fake();

        $captain = $this->makePlayer('captain@test.dev');
        $partner = $this->makePlayer('partner@test.dev');
        $category = $this->makeTournamentCategory();

        Sanctum::actingAs($captain);

        $response = $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ]);

        $response->assertOk()
            ->assertJsonPath('tournament_category_id', $category->id)
            ->assertJsonPath('team.status.code', Team::STATUS_PENDING_PARTNER_ACCEPTANCE);

        $registration = Registration::query()->firstOrFail();
        $invite = TeamInvite::query()->firstOrFail();
        $captainMember = TeamMember::query()
            ->where('team_id', $registration->team_id)
            ->where('role', TeamMember::ROLE_CAPTAIN)
            ->first();

        $this->assertSame('pending', $registration->status?->code);
        $this->assertSame(TeamInvite::STATUS_PENDING, $invite->status?->code);
        $this->assertNotNull($captainMember);
        $this->assertSame($captain->id, (int) $captainMember->user_id);
        Queue::assertPushed(SendTeamInviteEmailJob::class, 1);
    }

    public function test_registration_rejects_partner_without_account(): void
    {
        $captain = $this->makePlayer('captain-no-partner@test.dev');
        $category = $this->makeTournamentCategory();

        Sanctum::actingAs($captain);

        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => 'missing-user@test.dev',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['partner_email'])
            ->assertJsonPath(
                'errors.partner_email.0',
                'This player does not have an account yet. Your partner must register first.'
            );
    }

    public function test_pending_invite_creation_is_idempotent_for_same_captain_tournament_and_partner(): void
    {
        Queue::fake();

        $captain = $this->makePlayer('captain-idempotent@test.dev');
        $partner = $this->makePlayer('partner-idempotent@test.dev');
        $category = $this->makeTournamentCategory();

        Sanctum::actingAs($captain);

        $first = $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->assertOk();

        $second = $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->assertOk();

        $this->assertSame(
            (int) $first->json('id'),
            (int) $second->json('id')
        );
        $this->assertSame(1, Registration::query()->count());
        $this->assertSame(1, Team::query()->count());
        $this->assertSame(1, TeamInvite::query()->count());
    }

    public function test_open_tournament_registration_does_not_require_or_store_rankings(): void
    {
        Queue::fake();

        $captain = $this->makePlayer('captain-open@test.dev');
        $partner = $this->makePlayer('partner-open@test.dev');
        $category = $this->makeTournamentCategory('open');

        Sanctum::actingAs($captain);

        $response = $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ]);

        $response->assertOk()
            ->assertJsonPath('tournament_category_id', $category->id)
            ->assertJsonPath('team.status.code', Team::STATUS_PENDING_PARTNER_ACCEPTANCE);

        $rankings = RegistrationRanking::query()
            ->where('tournament_category_id', $category->id)
            ->orderBy('slot')
            ->get();

        $this->assertCount(2, $rankings);
        $this->assertTrue($rankings->every(fn (RegistrationRanking $ranking) => $ranking->ranking_value === null));
        $this->assertTrue($rankings->every(fn (RegistrationRanking $ranking) => $ranking->ranking_source === null));
        Queue::assertPushed(SendTeamInviteEmailJob::class, 1);
    }

    public function test_partner_can_accept_invite_when_authenticated_and_verified(): void
    {
        Queue::fake();

        $captain = $this->makePlayer('captain-accept@test.dev');
        $partner = $this->makePlayer('partner-accept@test.dev');
        $category = $this->makeTournamentCategory();

        Sanctum::actingAs($captain);
        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->assertOk();

        $invite = TeamInvite::query()->firstOrFail();
        Sanctum::actingAs($partner);

        $this->postJson("/api/player/team-invites/{$invite->id}/accept")
            ->assertOk()
            ->assertJsonPath('status.code', TeamInvite::STATUS_ACCEPTED);

        $invite->refresh();
        $team = $invite->team()->with(['status', 'members'])->firstOrFail();

        $this->assertSame(Team::STATUS_CONFIRMED, $team->status?->code);
        $this->assertTrue(
            $team->members->contains(fn (TeamMember $member) => $member->role === TeamMember::ROLE_PARTNER && (int) $member->user_id === (int) $partner->id)
        );
    }

    public function test_non_invited_authenticated_user_cannot_accept_invite(): void
    {
        Queue::fake();

        $captain = $this->makePlayer('captain-not-invited@test.dev');
        $partner = $this->makePlayer('partner-not-invited@test.dev');
        $outsider = $this->makePlayer('outsider-not-invited@test.dev');
        $category = $this->makeTournamentCategory();

        Sanctum::actingAs($captain);
        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->assertOk();

        $invite = TeamInvite::query()->firstOrFail();

        Sanctum::actingAs($outsider);
        $this->postJson("/api/player/team-invites/{$invite->id}/accept")
            ->assertStatus(403);
    }

    public function test_partner_can_reject_pending_invite_and_registration_is_cancelled(): void
    {
        Queue::fake();

        $captain = $this->makePlayer('captain-reject@test.dev');
        $partner = $this->makePlayer('partner-reject@test.dev');
        $category = $this->makeTournamentCategory();

        Sanctum::actingAs($captain);
        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->assertOk();

        $invite = TeamInvite::query()->firstOrFail();

        Sanctum::actingAs($partner);
        $this->postJson("/api/player/team-invites/{$invite->id}/reject")
            ->assertOk()
            ->assertJsonPath('status.code', TeamInvite::STATUS_REJECTED);

        $registration = Registration::query()->firstOrFail();
        $team = Team::query()->with('status')->findOrFail($registration->team_id);

        $this->assertSame('cancelled', $registration->fresh('status')->status?->code);
        $this->assertSame(Team::STATUS_CANCELLED, $team->status?->code);
    }

    public function test_captain_can_resend_pending_invite_and_non_captain_is_forbidden(): void
    {
        Queue::fake();

        $captain = $this->makePlayer('captain-resend@test.dev');
        $partner = $this->makePlayer('partner-resend@test.dev');
        $outsider = $this->makePlayer('outsider-resend@test.dev');
        $category = $this->makeTournamentCategory();

        Sanctum::actingAs($captain);
        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->assertOk();

        $invite = TeamInvite::query()->firstOrFail();

        Sanctum::actingAs($captain);
        $this->postJson("/api/player/team-invites/{$invite->id}/resend")
            ->assertOk()
            ->assertJsonPath('id', $invite->id);

        Sanctum::actingAs($outsider);
        $this->postJson("/api/player/team-invites/{$invite->id}/resend")
            ->assertStatus(403);

        Queue::assertPushed(SendTeamInviteEmailJob::class, 2);
    }

    public function test_resend_invite_endpoint_is_rate_limited(): void
    {
        Queue::fake();

        $captain = $this->makePlayer('captain-throttle@test.dev');
        $partner = $this->makePlayer('partner-throttle@test.dev');
        $category = $this->makeTournamentCategory();

        Sanctum::actingAs($captain);
        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->assertOk();

        $invite = TeamInvite::query()->firstOrFail();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson("/api/player/team-invites/{$invite->id}/resend")
                ->assertOk();
        }

        $this->postJson("/api/player/team-invites/{$invite->id}/resend")
            ->assertStatus(429);
    }

    public function test_unverified_partner_cannot_accept_invite(): void
    {
        Queue::fake();

        $captain = $this->makePlayer('captain-unverified@test.dev');
        $partner = User::factory()->unverified()->create([
            'email' => 'partner-unverified@test.dev',
            'role' => 'player',
            'is_active' => true,
        ]);
        $category = $this->makeTournamentCategory();

        Sanctum::actingAs($captain);
        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->assertOk();

        $invite = TeamInvite::query()->firstOrFail();

        Sanctum::actingAs($partner);
        $this->postJson("/api/player/team-invites/{$invite->id}/accept")
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Please verify your email before accessing this resource.',
            ]);
    }

    public function test_acceptance_revalidates_tournament_conflicts(): void
    {
        Queue::fake();

        $captain = $this->makePlayer('captain-conflict@test.dev');
        $partner = $this->makePlayer('partner-conflict@test.dev');
        $category = $this->makeTournamentCategory();

        Sanctum::actingAs($captain);
        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->assertOk();

        $conflictingTeam = Team::query()->create([
            'display_name' => 'Conflicting Team',
            'created_by' => $partner->id,
            'status_id' => $this->statusId('team', Team::STATUS_CONFIRMED),
        ]);

        TeamMember::query()->create([
            'team_id' => $conflictingTeam->id,
            'user_id' => $partner->id,
            'slot' => 1,
            'role' => TeamMember::ROLE_CAPTAIN,
        ]);

        Registration::query()->create([
            'tournament_category_id' => $category->id,
            'team_id' => $conflictingTeam->id,
            'status_id' => $this->statusId('registration', 'pending'),
        ]);

        $invite = TeamInvite::query()->firstOrFail();

        Sanctum::actingAs($partner);
        $this->postJson("/api/player/team-invites/{$invite->id}/accept")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['partner_email'])
            ->assertJsonPath(
                'errors.partner_email.0',
                'A player cannot participate in more than one team in the same tournament.'
            );
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

    private function makeTournamentCategory(string $mode = 'amateur'): TournamentCategory
    {
        $category = Category::query()->create([
            'name' => 'Masculino 2da',
            'group_code' => 'masculino',
            'level_code' => 'segunda',
            'display_name' => 'Masculino 2da',
            'sort_order' => 1,
        ]);

        $tournament = Tournament::query()->create([
            'name' => 'Test Tournament',
            'mode' => $mode,
            'status_id' => $this->statusId('tournament', 'registration_open'),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);

        return TournamentCategory::query()->create([
            'tournament_id' => $tournament->id,
            'category_id' => $category->id,
            'max_teams' => 32,
            'entry_fee_amount' => 50,
            'currency' => 'USD',
            'acceptance_type' => 'waitlist',
            'seeding_rule' => 'ranking_desc',
            'status_id' => null,
        ]);
    }

    private function statusId(string $module, string $code): int
    {
        return (int) Status::query()
            ->where('module', $module)
            ->where('code', $code)
            ->firstOrFail()
            ->id;
    }
}
