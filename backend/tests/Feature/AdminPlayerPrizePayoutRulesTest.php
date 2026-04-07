<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Status;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPlayerPrizePayoutRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_semifinalist_prize_is_rejected_for_open_and_allowed_for_segunda(): void
    {
        $this->seed(StatusSeeder::class);

        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@payout.dev',
            'password_hash' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $player = User::query()->create([
            'name' => 'Player',
            'email' => 'player@payout.dev',
            'password_hash' => bcrypt('secret'),
            'role' => 'player',
            'is_active' => true,
        ]);

        $openCategory = Category::query()->create([
            'name' => 'Masculino Open',
            'display_name' => 'Masculino Open',
            'group_code' => 'masculino',
            'level_code' => 'open',
            'sort_order' => 1,
        ]);

        $secondCategory = Category::query()->create([
            'name' => 'Masculino 2da',
            'display_name' => 'Masculino 2da',
            'group_code' => 'masculino',
            'level_code' => 'segunda',
            'sort_order' => 3,
        ]);

        $tournamentStatusId = (int) Status::query()->where('module', 'tournament')->where('code', 'registration_open')->value('id');

        $tournament = Tournament::query()->create([
            'name' => 'Payout Test',
            'mode' => 'amateur',
            'status_id' => $tournamentStatusId,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'created_by' => $admin->id,
        ]);

        $openTournamentCategory = TournamentCategory::query()->create([
            'tournament_id' => $tournament->id,
            'category_id' => $openCategory->id,
            'max_teams' => 32,
            'wildcard_slots' => 0,
            'entry_fee_amount' => 20,
            'currency' => 'EUR',
            'acceptance_type' => 'waitlist',
            'seeding_rule' => 'ranking_desc',
            'status_id' => null,
        ]);

        $secondTournamentCategory = TournamentCategory::query()->create([
            'tournament_id' => $tournament->id,
            'category_id' => $secondCategory->id,
            'max_teams' => 32,
            'wildcard_slots' => 0,
            'entry_fee_amount' => 20,
            'currency' => 'EUR',
            'acceptance_type' => 'waitlist',
            'seeding_rule' => 'ranking_desc',
            'status_id' => null,
        ]);

        Sanctum::actingAs($admin);

        $blocked = $this->postJson("/api/admin/players/{$player->id}/prize-payouts", [
            'tournament_id' => $tournament->id,
            'tournament_category_id' => $openTournamentCategory->id,
            'position' => 'semifinalist',
            'amount_eur_cents' => 10000,
        ]);

        $blocked->assertStatus(422);

        $allowed = $this->postJson("/api/admin/players/{$player->id}/prize-payouts", [
            'tournament_id' => $tournament->id,
            'tournament_category_id' => $secondTournamentCategory->id,
            'position' => 'semifinalist',
            'amount_eur_cents' => 12000,
        ]);

        $allowed->assertStatus(201);

        $this->assertDatabaseHas('player_prize_payouts', [
            'user_id' => $player->id,
            'tournament_category_id' => $secondTournamentCategory->id,
            'position' => 'semifinalist',
            'amount_eur_cents' => 12000,
        ]);
    }
}
