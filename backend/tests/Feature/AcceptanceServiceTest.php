<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\OpenEntry;
use App\Models\Registration;
use App\Models\RegistrationRanking;
use App\Models\Status;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Services\AcceptanceService;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcceptanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
    }

    public function test_open_recalculation_uses_fifo_without_ranking_scores(): void
    {
        $category = $this->makeTournamentCategory('open', 'ranking_desc', 1);

        $olderRegistration = $this->makeRegistration($category, 'older-open@test.dev', now()->subMinute());
        $newerRegistration = $this->makeRegistration($category, 'newer-open@test.dev', now(), [1, 2]);

        app(AcceptanceService::class)->recalculateForTournamentCategory($category->id);

        $olderRegistration->refresh();
        $newerRegistration->refresh();

        $this->assertSame('accepted', $olderRegistration->fresh('status')->status?->code);
        $this->assertSame('waitlisted', $newerRegistration->fresh('status')->status?->code);
        $this->assertSame(1, $olderRegistration->queue_position);
        $this->assertSame(1, $newerRegistration->queue_position);
        $this->assertNull($olderRegistration->team_ranking_score);
        $this->assertNull($newerRegistration->team_ranking_score);
        $this->assertNotNull($olderRegistration->accepted_at);
        $this->assertNull($newerRegistration->accepted_at);
    }

    public function test_pro_recalculation_keeps_ranking_based_ordering(): void
    {
        $category = $this->makeTournamentCategory('pro', 'ranking_desc', 1);

        $olderWorseRegistration = $this->makeRegistration($category, 'older-pro@test.dev', now()->subMinute(), [90, 100]);
        $newerBetterRegistration = $this->makeRegistration($category, 'newer-pro@test.dev', now(), [10, 20]);

        app(AcceptanceService::class)->recalculateForTournamentCategory($category->id);

        $olderWorseRegistration->refresh();
        $newerBetterRegistration->refresh();

        $this->assertSame('waitlisted', $olderWorseRegistration->fresh('status')->status?->code);
        $this->assertSame('accepted', $newerBetterRegistration->fresh('status')->status?->code);
        $this->assertSame(95, $olderWorseRegistration->team_ranking_score);
        $this->assertSame(15, $newerBetterRegistration->team_ranking_score);
        $this->assertNull($olderWorseRegistration->accepted_at);
        $this->assertNotNull($newerBetterRegistration->accepted_at);
    }

    public function test_amateur_recalculation_uses_fifo_without_ranking_scores(): void
    {
        $category = $this->makeTournamentCategory('amateur', 'ranking_desc', 1);

        $olderRegistration = $this->makeRegistration($category, 'older-amateur@test.dev', now()->subMinute());
        $newerRegistration = $this->makeRegistration($category, 'newer-amateur@test.dev', now(), [1, 2]);

        app(AcceptanceService::class)->recalculateForTournamentCategory($category->id);

        $olderRegistration->refresh();
        $newerRegistration->refresh();

        $this->assertSame('accepted', $olderRegistration->fresh('status')->status?->code);
        $this->assertSame('waitlisted', $newerRegistration->fresh('status')->status?->code);
        $this->assertSame(1, $olderRegistration->queue_position);
        $this->assertSame(1, $newerRegistration->queue_position);
        $this->assertNull($olderRegistration->team_ranking_score);
        $this->assertNull($newerRegistration->team_ranking_score);
        $this->assertNotNull($olderRegistration->accepted_at);
        $this->assertNull($newerRegistration->accepted_at);
    }

    public function test_open_recalculation_uses_only_assigned_registrations_and_ignores_raw_open_entries(): void
    {
        $category = $this->makeTournamentCategory('open', 'ranking_desc', 1);

        $olderAssignedRegistration = $this->makeRegistration($category, 'older-assigned-open@test.dev', now()->subMinute());
        $newerAssignedRegistration = $this->makeRegistration($category, 'newer-assigned-open@test.dev', now());

        $this->makeOpenEntry($category->tournament, 'raw-open-entry@test.dev');
        $this->makeOpenEntry(
            $category->tournament,
            'older-assigned-open@test.dev',
            $olderAssignedRegistration,
            $category
        );
        $this->makeOpenEntry(
            $category->tournament,
            'newer-assigned-open@test.dev',
            $newerAssignedRegistration,
            $category
        );

        app(AcceptanceService::class)->recalculateForTournamentCategory($category->id);

        $olderAssignedRegistration->refresh();
        $newerAssignedRegistration->refresh();
        $rawOpenEntry = OpenEntry::query()
            ->where('partner_email', 'raw-open-entry@test.dev')
            ->firstOrFail();

        $this->assertSame(2, Registration::query()->where('tournament_category_id', $category->id)->count());
        $this->assertSame('accepted', $olderAssignedRegistration->fresh('status')->status?->code);
        $this->assertSame('waitlisted', $newerAssignedRegistration->fresh('status')->status?->code);
        $this->assertSame(1, $olderAssignedRegistration->queue_position);
        $this->assertSame(1, $newerAssignedRegistration->queue_position);
        $this->assertNull($olderAssignedRegistration->team_ranking_score);
        $this->assertNull($newerAssignedRegistration->team_ranking_score);
        $this->assertNull($rawOpenEntry->registration_id);
        $this->assertSame(OpenEntry::ASSIGNMENT_PENDING, $rawOpenEntry->assignment_status);
    }

    private function makeTournamentCategory(string $mode, string $seedingRule, int $maxTeams): TournamentCategory
    {
        $category = Category::query()->create([
            'name' => strtoupper($mode).' Category',
            'display_name' => strtoupper($mode).' Category',
            'group_code' => 'mixed',
            'level_code' => $mode === 'open' ? 'open' : 'segunda',
            'sort_order' => 1,
        ]);

        $tournament = Tournament::query()->create([
            'name' => strtoupper($mode).' Tournament',
            'mode' => $mode,
            'status_id' => $this->statusId('tournament', 'registration_open'),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);

        return TournamentCategory::query()->create([
            'tournament_id' => $tournament->id,
            'category_id' => $category->id,
            'max_teams' => $maxTeams,
            'entry_fee_amount' => 20,
            'currency' => 'USD',
            'acceptance_type' => 'waitlist',
            'seeding_rule' => $seedingRule,
            'status_id' => null,
        ]);
    }

    private function makeRegistration(
        TournamentCategory $category,
        string $email,
        \DateTimeInterface $createdAt,
        array $rankings = []
    ): Registration {
        $player = User::factory()->create([
            'email' => $email,
            'role' => 'player',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $team = Team::query()->create([
            'display_name' => 'Team '.$player->id,
            'created_by' => $player->id,
        ]);

        $registration = Registration::query()->create([
            'tournament_category_id' => $category->id,
            'team_id' => $team->id,
            'status_id' => $this->statusId('registration', 'pending'),
            'is_wildcard' => false,
            'wildcard_fee_waived' => false,
        ]);

        $registration->created_at = $createdAt;
        $registration->save();

        foreach (array_values($rankings) as $index => $rankingValue) {
            RegistrationRanking::query()->create([
                'registration_id' => $registration->id,
                'tournament_category_id' => $category->id,
                'slot' => $index + 1,
                'user_id' => $player->id,
                'ranking_value' => $rankingValue,
                'ranking_source' => 'FEP',
            ]);
        }

        return $registration;
    }

    private function makeOpenEntry(
        Tournament $tournament,
        string $partnerEmail,
        ?Registration $registration = null,
        ?TournamentCategory $assignedCategory = null
    ): OpenEntry {
        $captain = User::factory()->create([
            'email' => 'captain-'.md5($partnerEmail.microtime(true)).'@test.dev',
            'role' => 'player',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $team = Team::query()->create([
            'display_name' => 'Open Entry Team '.$captain->id,
            'created_by' => $captain->id,
        ]);

        return OpenEntry::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'submitted_by_user_id' => $captain->id,
            'segment' => OpenEntry::SEGMENT_MEN,
            'partner_email' => $partnerEmail,
            'partner_first_name' => 'Partner',
            'partner_last_name' => 'Acceptance',
            'partner_dni' => 'V-'.random_int(1000000, 9999999),
            'assignment_status' => $registration ? OpenEntry::ASSIGNMENT_ASSIGNED : OpenEntry::ASSIGNMENT_PENDING,
            'assigned_tournament_category_id' => $assignedCategory?->id,
            'registration_id' => $registration?->id,
            'assigned_by_user_id' => $registration ? $captain->id : null,
            'assigned_at' => $registration ? now() : null,
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
