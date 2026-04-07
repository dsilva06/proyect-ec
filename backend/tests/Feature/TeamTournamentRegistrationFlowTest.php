<?php

namespace Tests\Feature;

use App\Jobs\SendTeamInviteEmailJob;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Status;
use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Services\StripeCheckoutGateway;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class TeamTournamentRegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_captain_creates_registration_without_sending_invite_before_payment(): void
    {
        Queue::fake();

        $captain = $this->makePlayer('captain@test.dev');
        $category = $this->makeTournamentCategory('open');

        Sanctum::actingAs($captain);

        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => 'new-partner@test.dev',
        ])
            ->assertOk()
            ->assertJsonPath('team.status.code', Team::STATUS_PENDING_PARTNER_ACCEPTANCE)
            ->assertJsonPath('status.code', 'accepted');

        $this->assertSame(1, Registration::query()->count());
        $this->assertSame(0, TeamInvite::query()->count());
        $this->assertSame(0, Payment::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_captain_can_start_stripe_checkout_for_existing_partner_without_creating_invite_yet(): void
    {
        Queue::fake();
        $this->mockStripeCheckoutCreation('cs_existing_partner');

        $captain = $this->makePlayer('captain-pay@test.dev');
        $partner = $this->makePlayer('partner-pay@test.dev');
        $category = $this->makeTournamentCategory('open');

        Sanctum::actingAs($captain);

        $registrationId = $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->json('id');

        $this->postJson("/api/player/registrations/{$registrationId}/pay")
            ->assertOk()
            ->assertJsonPath('session_id', 'cs_existing_partner')
            ->assertJsonPath('checkout_url', 'https://checkout.stripe.test/cs_existing_partner');

        $payment = Payment::query()->firstOrFail();

        $this->assertSame('payment_pending', Registration::query()->firstOrFail()->fresh('status')->status?->code);
        $this->assertSame('pending', $payment->status?->code);
        $this->assertSame(0, TeamInvite::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_stripe_webhook_completion_creates_invite_for_existing_partner_account(): void
    {
        Queue::fake();
        $this->mockStripeCheckoutCreation('cs_partner_webhook');

        $captain = $this->makePlayer('captain-webhook@test.dev');
        $partner = $this->makePlayer('partner-webhook@test.dev');
        $category = $this->makeTournamentCategory('open');

        Sanctum::actingAs($captain);

        $registrationId = $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->json('id');

        $this->postJson("/api/player/registrations/{$registrationId}/pay")->assertOk();
        $this->mockStripeWebhookEvent($registrationId, 'cs_partner_webhook');

        $this->postJson('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'sig_test',
        ])->assertOk();

        $invite = TeamInvite::query()->firstOrFail();

        $this->assertSame('awaiting_partner_acceptance', Registration::query()->firstOrFail()->fresh('status')->status?->code);
        $this->assertSame($partner->id, (int) $invite->invited_user_id);
        $this->assertSame('succeeded', Payment::query()->firstOrFail()->fresh('status')->status?->code);
        Queue::assertPushed(SendTeamInviteEmailJob::class, 1);
    }

    public function test_stripe_webhook_completion_creates_registration_link_invite_for_partner_without_account(): void
    {
        Queue::fake();
        $this->mockStripeCheckoutCreation('cs_missing_account');

        $captain = $this->makePlayer('captain-no-account@test.dev');
        $category = $this->makeTournamentCategory('open');

        Sanctum::actingAs($captain);

        $registrationId = $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => 'missing-user@test.dev',
        ])->json('id');

        $this->postJson("/api/player/registrations/{$registrationId}/pay")->assertOk();
        $this->mockStripeWebhookEvent($registrationId, 'cs_missing_account');

        $this->postJson('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'sig_test',
        ])->assertOk();

        $invite = TeamInvite::query()->firstOrFail();

        $this->assertSame('awaiting_partner_acceptance', Registration::query()->firstOrFail()->fresh('status')->status?->code);
        $this->assertNull($invite->invited_user_id);
        $this->assertSame('missing-user@test.dev', $invite->invited_email);
        Queue::assertPushed(SendTeamInviteEmailJob::class, 1);
    }

    public function test_payment_endpoint_is_idempotent_for_same_registration(): void
    {
        Queue::fake();
        $this->mockStripeCheckoutCreation('cs_idempotent');

        $captain = $this->makePlayer('captain-idempotent@test.dev');
        $partner = $this->makePlayer('partner-idempotent@test.dev');
        $category = $this->makeTournamentCategory('open');

        Sanctum::actingAs($captain);

        $registrationId = $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->json('id');

        $first = $this->postJson("/api/player/registrations/{$registrationId}/pay")->assertOk();
        $second = $this->postJson("/api/player/registrations/{$registrationId}/pay")->assertOk();

        $this->assertSame('cs_idempotent', $first->json('session_id'));
        $this->assertSame('cs_idempotent', $second->json('session_id'));
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(0, TeamInvite::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_partner_can_accept_invite_when_team_payment_is_already_covered(): void
    {
        Queue::fake();
        $this->mockStripeCheckoutCreation('cs_accept');

        $captain = $this->makePlayer('captain-accept@test.dev');
        $partner = $this->makePlayer('partner-accept@test.dev');
        $category = $this->makeTournamentCategory('open');

        Sanctum::actingAs($captain);
        $registrationId = $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->json('id');
        $this->postJson("/api/player/registrations/{$registrationId}/pay")->assertOk();
        $this->mockStripeWebhookEvent($registrationId, 'cs_accept');
        $this->postJson('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'sig_test',
        ])->assertOk();

        $invite = TeamInvite::query()->firstOrFail();

        Sanctum::actingAs($partner);
        $this->postJson("/api/player/team-invites/{$invite->id}/accept")
            ->assertOk()
            ->assertJsonPath('status.code', TeamInvite::STATUS_ACCEPTED);

        $registration = Registration::query()->firstOrFail();
        $team = Team::query()->with(['status', 'members'])->findOrFail($registration->team_id);

        $this->assertSame('paid', $registration->fresh('status')->status?->code);
        $this->assertSame(Team::STATUS_CONFIRMED, $team->status?->code);
        $this->assertTrue(
            $team->members->contains(fn (TeamMember $member) => $member->role === TeamMember::ROLE_PARTNER && (int) $member->user_id === (int) $partner->id)
        );
    }

    public function test_non_invited_authenticated_user_cannot_accept_paid_invite(): void
    {
        Queue::fake();
        $this->mockStripeCheckoutCreation('cs_outsider');

        $captain = $this->makePlayer('captain-outsider@test.dev');
        $partner = $this->makePlayer('partner-outsider@test.dev');
        $outsider = $this->makePlayer('outsider@test.dev');
        $category = $this->makeTournamentCategory('open');

        Sanctum::actingAs($captain);
        $registrationId = $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->json('id');
        $this->postJson("/api/player/registrations/{$registrationId}/pay")->assertOk();
        $this->mockStripeWebhookEvent($registrationId, 'cs_outsider');
        $this->postJson('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'sig_test',
        ])->assertOk();

        $invite = TeamInvite::query()->firstOrFail();

        Sanctum::actingAs($outsider);
        $this->postJson("/api/player/team-invites/{$invite->id}/accept")
            ->assertStatus(403);
    }

    public function test_partner_can_reject_paid_invite_and_registration_is_cancelled(): void
    {
        Queue::fake();
        $this->mockStripeCheckoutCreation('cs_reject');

        $captain = $this->makePlayer('captain-reject@test.dev');
        $partner = $this->makePlayer('partner-reject@test.dev');
        $category = $this->makeTournamentCategory('open');

        Sanctum::actingAs($captain);
        $registrationId = $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->json('id');
        $this->postJson("/api/player/registrations/{$registrationId}/pay")->assertOk();
        $this->mockStripeWebhookEvent($registrationId, 'cs_reject');
        $this->postJson('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'sig_test',
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

    public function test_captain_can_resend_pending_paid_invite(): void
    {
        Queue::fake();
        $this->mockStripeCheckoutCreation('cs_resend');

        $captain = $this->makePlayer('captain-resend@test.dev');
        $partner = $this->makePlayer('partner-resend@test.dev');
        $category = $this->makeTournamentCategory('open');

        Sanctum::actingAs($captain);
        $registrationId = $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->json('id');
        $this->postJson("/api/player/registrations/{$registrationId}/pay")->assertOk();
        $this->mockStripeWebhookEvent($registrationId, 'cs_resend');
        $this->postJson('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'sig_test',
        ])->assertOk();

        $invite = TeamInvite::query()->firstOrFail();

        $this->postJson("/api/player/team-invites/{$invite->id}/resend")
            ->assertOk()
            ->assertJsonPath('id', $invite->id);

        Queue::assertPushed(SendTeamInviteEmailJob::class, 2);
    }

    public function test_unverified_partner_cannot_accept_paid_invite(): void
    {
        Queue::fake();
        $this->mockStripeCheckoutCreation('cs_unverified');

        $captain = $this->makePlayer('captain-unverified@test.dev');
        $partner = User::factory()->unverified()->create([
            'email' => 'partner-unverified@test.dev',
            'role' => 'player',
            'is_active' => true,
        ]);
        $category = $this->makeTournamentCategory('open');

        Sanctum::actingAs($captain);
        $registrationId = $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->json('id');
        $this->postJson("/api/player/registrations/{$registrationId}/pay")->assertOk();
        $this->mockStripeWebhookEvent($registrationId, 'cs_unverified');
        $this->postJson('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'sig_test',
        ])->assertOk();

        $invite = TeamInvite::query()->firstOrFail();

        Sanctum::actingAs($partner);
        $this->postJson("/api/player/team-invites/{$invite->id}/accept")
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Please verify your email before accessing this resource.',
            ]);
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
            'level_code' => $mode === 'open' ? 'open' : 'segunda',
            'display_name' => $mode === 'open' ? 'Open' : 'Masculino 2da',
            'sort_order' => 1,
        ]);

        $tournament = Tournament::query()->create([
            'name' => 'Test Tournament',
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

    private function mockStripeCheckoutCreation(string $sessionId): void
    {
        $mock = Mockery::mock(StripeCheckoutGateway::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('createCheckoutSession')->once()->andReturn([
            'id' => $sessionId,
            'url' => "https://checkout.stripe.test/{$sessionId}",
            'status' => 'open',
            'payment_status' => 'unpaid',
            'payment_intent' => null,
        ]);

        $this->app->instance(StripeCheckoutGateway::class, $mock);
    }

    private function mockStripeWebhookEvent(int $registrationId, string $sessionId, string $type = 'checkout.session.completed'): void
    {
        $mock = Mockery::mock(StripeCheckoutGateway::class);
        $mock->shouldReceive('constructWebhookEvent')->once()->andReturn([
            'id' => 'evt_'.$sessionId,
            'type' => $type,
            'data' => [
                'id' => $sessionId,
                'payment_status' => 'paid',
                'amount_total' => 5000,
                'currency' => 'usd',
                'metadata' => [
                    'registration_id' => (string) $registrationId,
                    'captain_user_id' => '1',
                ],
            ],
        ]);

        $this->app->instance(StripeCheckoutGateway::class, $mock);
    }
}
