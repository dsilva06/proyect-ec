<?php

namespace Tests\Feature;

use App\Models\OpenEntry;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Status;
use App\Models\Tournament;
use App\Models\User;
use App\Services\StripeCheckoutGateway;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class OpenEntryPaymentFlowTest extends TestCase
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

    public function test_captain_can_start_stripe_checkout_for_open_entry(): void
    {
        $this->mockStripeCheckoutCreation('cs_open_entry_start');

        $captain = $this->makePlayer('captain-open-pay@test.dev');
        $entry = $this->createOpenEntry($captain, 'partner-open-pay@test.dev');

        Sanctum::actingAs($captain);

        $this->postJson("/api/player/open-entries/{$entry->id}/pay")
            ->assertOk()
            ->assertJsonPath('session_id', 'cs_open_entry_start')
            ->assertJsonPath('checkout_url', 'https://checkout.stripe.test/cs_open_entry_start');

        $payment = Payment::query()->with('status')->firstOrFail();

        $this->assertNull($payment->registration_id);
        $this->assertSame($entry->id, $payment->open_entry_id);
        $this->assertSame('pending', $payment->status?->code);
        $this->assertNull($entry->fresh()->paid_at);
        $this->assertSame(0, Registration::query()->count());
    }

    public function test_stripe_webhook_completion_marks_open_entry_paid_without_creating_registration(): void
    {
        $this->mockStripeCheckoutCreation('cs_open_entry_paid');

        $captain = $this->makePlayer('captain-open-webhook@test.dev');
        $entry = $this->createOpenEntry($captain, 'partner-open-webhook@test.dev');

        Sanctum::actingAs($captain);

        $this->postJson("/api/player/open-entries/{$entry->id}/pay")->assertOk();

        $this->mockStripeWebhookEventForOpenEntry($entry->id, $captain->id, 'cs_open_entry_paid');

        $this->postJson('/api/stripe/webhook', [], [
            'Stripe-Signature' => 'sig_test',
        ])->assertOk();

        $payment = Payment::query()->with('status')->firstOrFail();
        $entry = $entry->fresh(['team.status', 'payments.status']);

        $this->assertSame('succeeded', $payment->status?->code);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($entry->paid_at);
        $this->assertSame(OpenEntry::ASSIGNMENT_PENDING, $entry->assignment_status);
        $this->assertNull($entry->registration_id);
        $this->assertSame(0, Registration::query()->count());
    }

    public function test_player_payments_index_includes_open_entry_payments(): void
    {
        $this->mockStripeCheckoutCreation('cs_open_entry_index');

        $captain = $this->makePlayer('captain-open-payments@test.dev');
        $entry = $this->createOpenEntry($captain, 'partner-open-payments@test.dev');

        Sanctum::actingAs($captain);

        $this->postJson("/api/player/open-entries/{$entry->id}/pay")->assertOk();

        $this->getJson('/api/player/payments')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.open_entry_id', $entry->id)
            ->assertJsonPath('0.registration_id', null)
            ->assertJsonPath('0.status.code', 'pending')
            ->assertJsonPath('0.open_entry.id', $entry->id);
    }

    private function createOpenEntry(User $captain, string $partnerEmail): OpenEntry
    {
        $tournament = Tournament::query()->create([
            'name' => 'Open Payment Tournament',
            'mode' => 'open',
            'classification_method' => Tournament::CLASSIFICATION_REFEREE_ASSIGNED,
            'status_id' => $this->statusId('tournament', 'registration_open'),
            'entry_fee_amount' => 50,
            'entry_fee_currency' => 'EUR',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'registration_close_at' => now()->addDays(2),
        ]);

        Sanctum::actingAs($captain);

        $response = $this->postJson('/api/player/registrations', [
            'tournament_id' => $tournament->id,
            'segment' => OpenEntry::SEGMENT_MEN,
            'partner_email' => $partnerEmail,
            'partner_first_name' => 'Partner',
            'partner_last_name' => 'Payment',
            'partner_dni' => 'V-30001001',
        ])->assertOk();

        return OpenEntry::query()->findOrFail((int) $response->json('id'));
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

    private function statusId(string $module, string $code): int
    {
        return (int) Status::query()
            ->where('module', $module)
            ->where('code', $code)
            ->value('id');
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

    private function mockStripeWebhookEventForOpenEntry(int $openEntryId, int $captainUserId, string $sessionId, string $type = 'checkout.session.completed'): void
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
                    'open_entry_id' => (string) $openEntryId,
                    'captain_user_id' => (string) $captainUserId,
                ],
            ],
        ]);

        $this->app->instance(StripeCheckoutGateway::class, $mock);
    }
}
