<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\OpenEntry;
use App\Models\Payment;
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

class PlayerOpenEntryReadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
    }

    public function test_player_can_list_only_own_open_entries_and_see_assignment_state(): void
    {
        $captain = $this->makePlayer('captain-list@test.dev');
        $otherCaptain = $this->makePlayer('captain-other@test.dev');
        $tournament = $this->makeOpenTournament();
        $category = $this->makeTournamentCategory($tournament);

        $entry = $this->createOpenEntry($captain, $tournament, OpenEntry::SEGMENT_MEN, 'partner-list@test.dev');
        $this->markOpenEntryPaid($entry);

        $registration = Registration::query()->create([
            'tournament_category_id' => $category->id,
            'team_id' => $entry->team_id,
            'status_id' => $this->statusId('registration', 'paid'),
            'accepted_at' => $entry->paid_at,
        ]);

        $entry->forceFill([
            'assignment_status' => OpenEntry::ASSIGNMENT_ASSIGNED,
            'assigned_tournament_category_id' => $category->id,
            'registration_id' => $registration->id,
            'assigned_by_user_id' => $otherCaptain->id,
            'assigned_at' => now(),
        ])->save();

        $otherEntry = $this->createOpenEntry($otherCaptain, $tournament, OpenEntry::SEGMENT_WOMEN, 'partner-other@test.dev');
        $this->markOpenEntryPaid($otherEntry);

        Sanctum::actingAs($captain);

        $this->getJson('/api/player/open-entries')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $entry->id)
            ->assertJsonPath('0.assignment_status', OpenEntry::ASSIGNMENT_ASSIGNED)
            ->assertJsonPath('0.registration_id', $registration->id)
            ->assertJsonPath('0.payment_is_covered', true)
            ->assertJsonPath('0.assigned_tournament_category_id', $category->id);

        $this->getJson("/api/player/open-entries/{$entry->id}")
            ->assertOk()
            ->assertJsonPath('id', $entry->id)
            ->assertJsonPath('registration_id', $registration->id)
            ->assertJsonPath('payment_is_covered', true);

        $this->getJson("/api/player/open-entries/{$otherEntry->id}")
            ->assertNotFound();
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
            'name' => 'Player Open Entry Tournament',
            'mode' => 'open',
            'classification_method' => Tournament::CLASSIFICATION_REFEREE_ASSIGNED,
            'status_id' => $this->statusId('tournament', 'registration_open'),
            'entry_fee_amount' => 50,
            'entry_fee_currency' => 'USD',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'registration_close_at' => now()->addDays(3),
        ]);
    }

    private function makeTournamentCategory(Tournament $tournament): TournamentCategory
    {
        $category = Category::query()->create([
            'name' => 'Masculino Open',
            'display_name' => 'Masculino Open',
            'group_code' => 'masculino',
            'level_code' => 'open',
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

    private function createOpenEntry(User $captain, Tournament $tournament, string $segment, string $partnerEmail): OpenEntry
    {
        $team = Team::query()->create([
            'display_name' => 'Player Open Team '.$captain->id,
            'created_by' => $captain->id,
            'status_id' => $this->statusId('team', Team::STATUS_CONFIRMED),
        ]);

        TeamMember::query()->create([
            'team_id' => $team->id,
            'user_id' => $captain->id,
            'slot' => 1,
            'role' => TeamMember::ROLE_CAPTAIN,
        ]);

        return OpenEntry::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'submitted_by_user_id' => $captain->id,
            'segment' => $segment,
            'partner_email' => $partnerEmail,
            'partner_first_name' => 'Partner',
            'partner_last_name' => 'Player',
            'partner_dni' => 'V-'.random_int(10000000, 99999999),
        ]);
    }

    private function markOpenEntryPaid(OpenEntry $entry): void
    {
        $entry->forceFill(['paid_at' => now()])->save();

        Payment::query()->create([
            'registration_id' => null,
            'open_entry_id' => $entry->id,
            'provider' => 'stripe_checkout',
            'provider_intent_id' => 'sess_'.$entry->id,
            'amount_cents' => 5000,
            'currency' => 'USD',
            'status_id' => $this->statusId('payment', 'succeeded'),
            'paid_by_user_id' => $entry->submitted_by_user_id,
            'paid_at' => $entry->paid_at,
            'raw_payload' => ['source' => 'test'],
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
