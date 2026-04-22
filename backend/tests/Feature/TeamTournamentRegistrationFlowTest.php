<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invitation;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Status;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Services\StripeCheckoutGateway;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
        Mail::fake();

        $captain = $this->makePlayer('captain@test.dev');
        $category = $this->makeTournamentCategory('open');

        Sanctum::actingAs($captain);

        $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => 'new-partner@test.dev',
        ])
            ->assertOk()
            ->assertJsonPath('team.status.code', Team::STATUS_CONFIRMED)
            ->assertJsonPath('status.code', 'accepted');

        $this->assertSame(1, Registration::query()->count());
        $this->assertSame(0, Payment::query()->count());
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    public function test_player_can_create_team_without_partner_invite_when_partner_has_no_account(): void
    {
        $captain = $this->makePlayer('captain-team-create@test.dev');

        Sanctum::actingAs($captain);

        $this->postJson('/api/player/teams', [
            'partner_email' => 'missing-team-partner@test.dev',
        ])->assertOk();

        $team = Team::query()->with(['status', 'members'])->firstOrFail();

        $this->assertSame(Team::STATUS_CONFIRMED, $team->status?->code);
        $this->assertCount(1, $team->members);
        $this->assertSame(TeamMember::ROLE_CAPTAIN, $team->members->first()?->role);
    }

    public function test_player_can_claim_wildcard_without_creating_partner_invite(): void
    {
        $player = $this->makePlayer('wildcard-player@test.dev');
        $category = $this->makeTournamentCategory('open');
        $category->update(['wildcard_slots' => 1]);

        $wildcard = Invitation::query()->create([
            'tournament_category_id' => $category->id,
            'purpose' => 'wildcard',
            'email' => $player->email,
            'partner_email' => 'missing-wildcard-partner@test.dev',
            'wildcard_fee_waived' => true,
            'status_id' => $this->statusId('invitation', 'pending'),
            'token' => 'wildcard-no-invite-token',
            'expires_at' => now()->addDay(),
        ]);

        Sanctum::actingAs($player);

        $this->postJson("/api/player/wildcards/{$wildcard->token}/claim")
            ->assertOk()
            ->assertJsonPath('status.code', 'paid');

        $registration = Registration::query()->with(['team.status', 'team.members', 'rankings', 'status'])->firstOrFail();
        $wildcard->refresh();

        $this->assertSame(Team::STATUS_CONFIRMED, $registration->team?->status?->code);
        $this->assertCount(1, $registration->team?->members ?? []);
        $this->assertSame('accepted', $wildcard->fresh('status')->status?->code);
        $this->assertSame('missing-wildcard-partner@test.dev', $wildcard->partner_email);
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
        Queue::assertNothingPushed();
    }

    public function test_standard_checkout_uses_tournament_category_entry_fee(): void
    {
        Queue::fake();
        $this->mockStripeCheckoutCreation('cs_category_fee', 6500, 'eur');

        $captain = $this->makePlayer('captain-category-fee@test.dev');
        $partner = $this->makePlayer('partner-category-fee@test.dev');
        $category = $this->makeTournamentCategory('amateur');
        $category->tournament->update([
            'entry_fee_amount' => 20,
            'entry_fee_currency' => 'EUR',
        ]);
        $category->update([
            'entry_fee_amount' => 65,
            'currency' => 'EUR',
        ]);

        Sanctum::actingAs($captain);

        $registrationId = $this->postJson('/api/player/registrations', [
            'tournament_category_id' => $category->id,
            'partner_email' => $partner->email,
        ])->json('id');

        $this->postJson("/api/player/registrations/{$registrationId}/pay")
            ->assertOk()
            ->assertJsonPath('session_id', 'cs_category_fee');

        $payment = Payment::query()->firstOrFail();

        $this->assertSame(6500, $payment->amount_cents);
        $this->assertSame('EUR', $payment->currency);
    }

    public function test_stripe_webhook_completion_links_existing_partner_account_without_creating_invite(): void
    {
        Queue::fake();
        Mail::fake();
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

        $registration = Registration::query()->with(['status', 'rankings'])->firstOrFail();
        $team = Team::query()->with(['status', 'members'])->findOrFail($registration->team_id);
        $partnerRanking = $registration->rankings->firstWhere('slot', 2);

        $this->assertSame('paid', $registration->status?->code);
        $this->assertSame(Team::STATUS_CONFIRMED, $team->status?->code);
        $this->assertSame($partner->id, (int) $partnerRanking?->user_id);
        $this->assertTrue(
            $team->members->contains(
                fn (TeamMember $member) => $member->role === TeamMember::ROLE_PARTNER && (int) $member->user_id === (int) $partner->id
            )
        );
        $this->assertSame('succeeded', Payment::query()->firstOrFail()->fresh('status')->status?->code);
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    public function test_stripe_webhook_completion_preserves_partner_email_without_creating_invite(): void
    {
        Queue::fake();
        Mail::fake();
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

        $registration = Registration::query()->with(['status', 'rankings'])->firstOrFail();
        $team = Team::query()->with(['status', 'members'])->findOrFail($registration->team_id);
        $partnerRanking = $registration->rankings->firstWhere('slot', 2);

        $this->assertSame('paid', $registration->status?->code);
        $this->assertSame(Team::STATUS_CONFIRMED, $team->status?->code);
        $this->assertSame('missing-user@test.dev', $partnerRanking?->invited_email);
        $this->assertNull($partnerRanking?->user_id);
        $this->assertFalse(
            $team->members->contains(fn (TeamMember $member) => $member->role === TeamMember::ROLE_PARTNER)
        );
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
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
        Queue::assertNothingPushed();
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

    private function mockStripeCheckoutCreation(string $sessionId, ?int $expectedAmountCents = null, ?string $expectedCurrency = null): void
    {
        $mock = Mockery::mock(StripeCheckoutGateway::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);

        $expectation = $mock->shouldReceive('createCheckoutSession')->once();
        if ($expectedAmountCents !== null || $expectedCurrency !== null) {
            $expectation->with(Mockery::on(function (array $payload) use ($expectedAmountCents, $expectedCurrency): bool {
                $priceData = $payload['line_items'][0]['price_data'] ?? [];

                return ($expectedAmountCents === null || (int) ($priceData['unit_amount'] ?? 0) === $expectedAmountCents)
                    && ($expectedCurrency === null || (string) ($priceData['currency'] ?? '') === $expectedCurrency);
            }));
        }

        $expectation->andReturn([
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
