<?php

namespace Tests\Feature;

use App\Models\Bracket;
use App\Models\Category;
use App\Models\OpenEntry;
use App\Models\Registration;
use App\Models\Status;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Services\BracketGenerationService;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BracketGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StatusSeeder::class);
    }

    public function test_open_generation_does_not_assign_ranking_based_seeds(): void
    {
        $category = $this->makeTournamentCategory('open');
        $bracket = Bracket::query()->create([
            'tournament_category_id' => $category->id,
            'type' => Bracket::TYPE_SINGLE_ELIMINATION,
            'status_id' => $this->statusId('bracket', 'draft'),
        ]);

        $this->makeAcceptedRegistration($category, 10, now()->subMinutes(3));
        $this->makeAcceptedRegistration($category, 20, now()->subMinutes(2));
        $this->makeAcceptedRegistration($category, 30, now()->subMinute());

        app(BracketGenerationService::class)->generate($bracket, false);

        $seededSlots = $bracket->fresh('slots')->slots->filter(fn ($slot) => $slot->seed_number !== null);
        $seededRegistrations = Registration::query()
            ->where('tournament_category_id', $category->id)
            ->whereNotNull('seed_number')
            ->get();

        $this->assertCount(0, $seededSlots);
        $this->assertCount(0, $seededRegistrations);
    }

    public function test_non_open_generation_keeps_ranking_based_seeding(): void
    {
        $category = $this->makeTournamentCategory('pro');
        $bracket = Bracket::query()->create([
            'tournament_category_id' => $category->id,
            'type' => Bracket::TYPE_SINGLE_ELIMINATION,
            'status_id' => $this->statusId('bracket', 'draft'),
        ]);

        $topSeed = $this->makeAcceptedRegistration($category, 10, now()->subMinutes(4));
        $secondSeed = $this->makeAcceptedRegistration($category, 20, now()->subMinutes(3));
        $this->makeAcceptedRegistration($category, 30, now()->subMinutes(2));
        $this->makeAcceptedRegistration($category, 40, now()->subMinute());

        app(BracketGenerationService::class)->generate($bracket, false);

        $this->assertSame(1, $topSeed->fresh()->seed_number);
        $this->assertSame(2, $secondSeed->fresh()->seed_number);
    }

    public function test_open_generation_uses_assigned_registrations_only_and_ignores_raw_open_entries(): void
    {
        $category = $this->makeTournamentCategory('open', 8);
        $bracket = Bracket::query()->create([
            'tournament_category_id' => $category->id,
            'type' => Bracket::TYPE_SINGLE_ELIMINATION,
            'status_id' => $this->statusId('bracket', 'draft'),
        ]);

        $firstAssigned = $this->makeAcceptedRegistration($category, 10, now()->subMinutes(3));
        $secondAssigned = $this->makeAcceptedRegistration($category, 20, now()->subMinutes(2));
        $thirdAssigned = $this->makeAcceptedRegistration($category, 30, now()->subMinute());

        $this->makeOpenEntry($category->tournament, 'raw-bracket-entry@test.dev');
        $this->makeOpenEntry($category->tournament, 'assigned-one@test.dev', $firstAssigned, $category);
        $this->makeOpenEntry($category->tournament, 'assigned-two@test.dev', $secondAssigned, $category);
        $this->makeOpenEntry($category->tournament, 'assigned-three@test.dev', $thirdAssigned, $category);

        app(BracketGenerationService::class)->generate($bracket, false);

        $slotRegistrationIds = $bracket->fresh('slots')
            ->slots
            ->pluck('registration_id')
            ->filter()
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing(
            [$firstAssigned->id, $secondAssigned->id, $thirdAssigned->id],
            $slotRegistrationIds
        );
        $this->assertSame(3, count($slotRegistrationIds));
        $this->assertCount(0, Registration::query()
            ->where('tournament_category_id', $category->id)
            ->whereNotNull('seed_number')
            ->get());
        $this->assertNull(
            OpenEntry::query()
                ->where('partner_email', 'raw-bracket-entry@test.dev')
                ->firstOrFail()
                ->registration_id
        );
    }

    public function test_open_generation_supports_sixteen_team_draw_after_assignment(): void
    {
        $category = $this->makeTournamentCategory('open', 16);
        $bracket = Bracket::query()->create([
            'tournament_category_id' => $category->id,
            'type' => Bracket::TYPE_SINGLE_ELIMINATION,
            'status_id' => $this->statusId('bracket', 'draft'),
        ]);

        $assignedRegistrations = [];
        for ($i = 0; $i < 9; $i++) {
            $registration = $this->makeAcceptedRegistration($category, 10 + $i, now()->subMinutes(20 - $i));
            $this->makeOpenEntry($category->tournament, "assigned-sixteen-{$i}@test.dev", $registration, $category);
            $assignedRegistrations[] = $registration->id;
        }

        $this->makeOpenEntry($category->tournament, 'raw-sixteen-entry@test.dev');

        app(BracketGenerationService::class)->generate($bracket, false);

        $bracket->refresh()->load(['slots', 'matches']);
        $slotRegistrationIds = $bracket->slots->pluck('registration_id')->filter()->values()->all();

        $this->assertCount(16, $bracket->slots);
        $this->assertCount(15, $bracket->matches);
        $this->assertEqualsCanonicalizing($assignedRegistrations, $slotRegistrationIds);
    }

    private function makeTournamentCategory(string $mode, int $maxTeams = 8): TournamentCategory
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
            'seeding_rule' => $mode === 'open' ? 'fifo' : 'ranking_desc',
            'status_id' => null,
        ]);
    }

    private function makeAcceptedRegistration(
        TournamentCategory $category,
        int $teamRankingScore,
        \DateTimeInterface $createdAt
    ): Registration {
        $team = Team::query()->create([
            'display_name' => 'Team '.$teamRankingScore,
        ]);

        $registration = Registration::query()->create([
            'tournament_category_id' => $category->id,
            'team_id' => $team->id,
            'status_id' => $this->statusId('registration', 'accepted'),
            'team_ranking_score' => $teamRankingScore,
            'is_wildcard' => false,
            'wildcard_fee_waived' => false,
        ]);

        $registration->created_at = $createdAt;
        $registration->save();

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
            'display_name' => 'Open Bracket Team '.$captain->id,
            'created_by' => $captain->id,
        ]);

        return OpenEntry::query()->create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'submitted_by_user_id' => $captain->id,
            'segment' => OpenEntry::SEGMENT_MEN,
            'partner_email' => $partnerEmail,
            'partner_first_name' => 'Partner',
            'partner_last_name' => 'Bracket',
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
