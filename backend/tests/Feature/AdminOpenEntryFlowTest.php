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

class AdminOpenEntryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
    }

    public function test_admin_can_list_paid_unassigned_open_entries_for_assignment_pool(): void
    {
        $admin = $this->makeAdmin();
        $tournament = $this->makeOpenTournament('Open Assignment Tournament');

        $paidPending = $this->createOpenEntry($this->makePlayer('captain-paid@test.dev'), $tournament, OpenEntry::SEGMENT_MEN, 'paid-pending@test.dev');
        $this->markOpenEntryPaid($paidPending);

        $unpaidPending = $this->createOpenEntry($this->makePlayer('captain-unpaid@test.dev'), $tournament, OpenEntry::SEGMENT_MEN, 'unpaid-pending@test.dev');

        $assigned = $this->createOpenEntry($this->makePlayer('captain-assigned@test.dev'), $tournament, OpenEntry::SEGMENT_WOMEN, 'assigned@test.dev');
        $this->markOpenEntryPaid($assigned);
        $category = $this->makeTournamentCategory($tournament, 'femenino', 'open');
        $registration = $this->createAssignedRegistration($assigned, $category);
        $assigned->forceFill([
            'assignment_status' => OpenEntry::ASSIGNMENT_ASSIGNED,
            'assigned_tournament_category_id' => $category->id,
            'registration_id' => $registration->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ])->save();

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/open-entries?tournament_id={$tournament->id}&paid=1&assigned=0")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $paidPending->id)
            ->assertJsonPath('0.assignment_status', OpenEntry::ASSIGNMENT_PENDING)
            ->assertJsonPath('0.registration_id', null);

        $this->assertNull($unpaidPending->fresh()->paid_at);
    }

    public function test_admin_can_assign_paid_open_entry_to_category_and_create_paid_registration(): void
    {
        $admin = $this->makeAdmin();
        $captain = $this->makePlayer('captain-assign@test.dev');
        $partner = $this->makePlayer('partner-assign@test.dev');
        $tournament = $this->makeOpenTournament('Open Assign Paid Tournament');
        $category = $this->makeTournamentCategory($tournament, 'masculino', 'primera');
        $entry = $this->createOpenEntry($captain, $tournament, OpenEntry::SEGMENT_MEN, $partner->email);
        $this->markOpenEntryPaid($entry, $captain);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/open-entries/{$entry->id}/assign-category", [
            'tournament_category_id' => $category->id,
        ])->assertOk()
            ->assertJsonPath('assignment_status', OpenEntry::ASSIGNMENT_ASSIGNED)
            ->assertJsonPath('assigned_tournament_category_id', $category->id)
            ->assertJsonPath('registration.tournament_category_id', $category->id)
            ->assertJsonPath('registration.status.code', 'paid')
            ->assertJsonPath('registration.payment_is_covered', true);

        $registrationId = (int) $response->json('registration_id');
        $registration = Registration::query()
            ->with(['status', 'rankings', 'team.members', 'openEntry'])
            ->findOrFail($registrationId);

        $this->assertSame('paid', $registration->status?->code);
        $this->assertNotNull($registration->accepted_at);
        $this->assertSame($entry->id, $registration->openEntry?->id);
        $this->assertSame(0, Payment::query()->where('registration_id', $registration->id)->count());
        $this->assertSame(1, Payment::query()->where('open_entry_id', $entry->id)->whereHas('status', fn ($query) => $query->where('code', 'succeeded'))->count());
        $this->assertTrue(
            $registration->team->members->contains(
                fn (TeamMember $member) => $member->slot === 2 && (int) $member->user_id === (int) $partner->id
            )
        );
        $this->assertSame($partner->id, (int) $registration->rankings->firstWhere('slot', 2)?->user_id);
        $this->assertSame($partner->email, $registration->rankings->firstWhere('slot', 2)?->invited_email);
    }

    public function test_admin_cannot_assign_open_entry_twice(): void
    {
        $admin = $this->makeAdmin();
        $tournament = $this->makeOpenTournament('Open Double Assign Tournament');
        $category = $this->makeTournamentCategory($tournament, 'masculino', 'primera');
        $entry = $this->createOpenEntry($this->makePlayer('captain-double@test.dev'), $tournament, OpenEntry::SEGMENT_MEN, 'partner-double@test.dev');
        $this->markOpenEntryPaid($entry);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/open-entries/{$entry->id}/assign-category", [
            'tournament_category_id' => $category->id,
        ])->assertOk();

        $this->postJson("/api/admin/open-entries/{$entry->id}/assign-category", [
            'tournament_category_id' => $category->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['open_entry_id']);
    }

    public function test_admin_cannot_assign_open_entry_to_other_tournament_or_wrong_segment_category(): void
    {
        $admin = $this->makeAdmin();
        $tournament = $this->makeOpenTournament('Open Segment Tournament');
        $otherTournament = $this->makeOpenTournament('Other Open Tournament');
        $wrongSegmentCategory = $this->makeTournamentCategory($tournament, 'femenino', 'open');
        $otherTournamentCategory = $this->makeTournamentCategory($otherTournament, 'masculino', 'open');
        $entry = $this->createOpenEntry($this->makePlayer('captain-segment@test.dev'), $tournament, OpenEntry::SEGMENT_MEN, 'partner-segment@test.dev');
        $this->markOpenEntryPaid($entry);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/open-entries/{$entry->id}/assign-category", [
            'tournament_category_id' => $wrongSegmentCategory->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['tournament_category_id']);

        $this->postJson("/api/admin/open-entries/{$entry->id}/assign-category", [
            'tournament_category_id' => $otherTournamentCategory->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['tournament_category_id']);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'email' => 'admin-open-entry@test.dev',
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
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

    private function makeOpenTournament(string $name): Tournament
    {
        return Tournament::query()->create([
            'name' => $name,
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

    private function makeTournamentCategory(Tournament $tournament, string $groupCode, string $levelCode): TournamentCategory
    {
        $category = Category::query()->create([
            'name' => ucfirst($groupCode).' '.ucfirst($levelCode),
            'display_name' => ucfirst($groupCode).' '.ucfirst($levelCode),
            'group_code' => $groupCode,
            'level_code' => $levelCode,
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
            'display_name' => 'Open Team '.$captain->id,
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
            'partner_last_name' => 'Admin',
            'partner_dni' => 'V-'.random_int(10000000, 99999999),
        ]);
    }

    private function markOpenEntryPaid(OpenEntry $entry, ?User $actor = null): void
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
            'paid_by_user_id' => $actor?->id ?? $entry->submitted_by_user_id,
            'paid_at' => $entry->paid_at,
            'raw_payload' => ['source' => 'test'],
        ]);
    }

    private function createAssignedRegistration(OpenEntry $entry, TournamentCategory $category): Registration
    {
        return Registration::query()->create([
            'tournament_category_id' => $category->id,
            'team_id' => $entry->team_id,
            'status_id' => $this->statusId('registration', 'paid'),
            'accepted_at' => now(),
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
