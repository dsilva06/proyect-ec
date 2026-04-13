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

class AdminCrudSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_admin_can_crud_tournaments_and_tournament_categories(): void
    {
        $this->seed(StatusSeeder::class);

        $admin = $this->createVerifiedAdmin();
        $category = Category::query()->create([
            'name' => 'Masculino 3ra',
            'display_name' => 'Masculino 3ra',
            'group_code' => 'masculino',
            'level_code' => 'tercera',
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($admin);

        $tournamentResponse = $this->postJson('/api/admin/tournaments', [
            'name' => 'CRUD Tournament',
            'description' => 'Smoke test',
            'mode' => 'amateur',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'entry_fee_amount' => 25,
            'entry_fee_currency' => 'EUR',
        ]);

        $tournamentResponse->assertCreated();
        $tournamentId = (int) Tournament::query()
            ->where('name', 'CRUD Tournament')
            ->value('id');

        $this->assertDatabaseHas('tournaments', [
            'id' => $tournamentId,
            'name' => 'CRUD Tournament',
            'entry_fee_amount' => 25,
            'entry_fee_currency' => 'EUR',
            'classification_method' => Tournament::CLASSIFICATION_SELF_SELECTED,
        ]);

        $tournamentResponse->assertJsonPath('classification_method', Tournament::CLASSIFICATION_SELF_SELECTED);

        $categoryResponse = $this->postJson("/api/admin/tournaments/{$tournamentId}/categories", [
            'category_id' => $category->id,
            'max_teams' => 32,
            'wildcard_slots' => 0,
            'acceptance_type' => 'waitlist',
        ]);

        $categoryResponse->assertCreated();
        $tournamentCategoryId = (int) TournamentCategory::query()
            ->where('tournament_id', $tournamentId)
            ->where('category_id', $category->id)
            ->value('id');

        $this->assertDatabaseHas('tournament_categories', [
            'id' => $tournamentCategoryId,
            'tournament_id' => $tournamentId,
            'category_id' => $category->id,
            'entry_fee_amount' => 25,
            'currency' => 'EUR',
        ]);

        $updateTournament = $this->putJson("/api/admin/tournaments/{$tournamentId}", [
            'name' => 'CRUD Tournament Updated',
            'entry_fee_amount' => 30,
            'classification_method' => Tournament::CLASSIFICATION_REFEREE_ASSIGNED,
        ]);

        $updateTournament->assertOk();
        $this->assertDatabaseHas('tournaments', [
            'id' => $tournamentId,
            'name' => 'CRUD Tournament Updated',
            'entry_fee_amount' => 30,
            'classification_method' => Tournament::CLASSIFICATION_REFEREE_ASSIGNED,
        ]);

        $updateTournament->assertJsonPath('classification_method', Tournament::CLASSIFICATION_REFEREE_ASSIGNED);

        $updateCategory = $this->patchJson("/api/admin/tournament-categories/{$tournamentCategoryId}", [
            'wildcard_slots' => 2,
            'acceptance_type' => 'immediate',
        ]);

        $updateCategory->assertOk();
        $this->assertDatabaseHas('tournament_categories', [
            'id' => $tournamentCategoryId,
            'wildcard_slots' => 2,
            'acceptance_type' => 'immediate',
        ]);

        $deleteCategory = $this->deleteJson("/api/admin/tournament-categories/{$tournamentCategoryId}");
        $deleteCategory->assertNoContent();
        $this->assertDatabaseMissing('tournament_categories', ['id' => $tournamentCategoryId]);

        $deleteTournament = $this->deleteJson("/api/admin/tournaments/{$tournamentId}");
        $deleteTournament->assertNoContent();
        $this->assertDatabaseMissing('tournaments', ['id' => $tournamentId]);
    }

    public function test_admin_tournament_endpoints_tolerate_tournaments_without_status(): void
    {
        $this->seed(StatusSeeder::class);

        $admin = $this->createVerifiedAdmin();

        $tournament = Tournament::query()->create([
            'name' => 'Statusless Tournament',
            'description' => 'Legacy data safety check',
            'mode' => 'amateur',
            'status_id' => null,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'created_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/tournaments')
            ->assertOk()
            ->assertJsonPath('0.id', $tournament->id)
            ->assertJsonPath('0.status', null);

        $this->getJson("/api/admin/tournaments/{$tournament->id}")
            ->assertOk()
            ->assertJsonPath('id', $tournament->id)
            ->assertJsonPath('status', null);
    }

    public function test_admin_tournament_creation_returns_validation_error_when_default_draft_status_is_missing(): void
    {
        $this->seed(StatusSeeder::class);

        $admin = $this->createVerifiedAdmin();

        Status::query()
            ->where('module', 'tournament')
            ->where('code', 'draft')
            ->delete();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/tournaments', [
            'name' => 'Missing Draft Tournament',
            'mode' => 'amateur',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status_id']);

        $this->assertDatabaseMissing('tournaments', [
            'name' => 'Missing Draft Tournament',
        ]);
    }

    public function test_admin_tournament_creation_rejects_non_eur_currencies(): void
    {
        $this->seed(StatusSeeder::class);

        $admin = $this->createVerifiedAdmin();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/tournaments', [
            'name' => 'USD Tournament',
            'mode' => 'amateur',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'entry_fee_amount' => 25,
            'entry_fee_currency' => 'USD',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['entry_fee_currency']);
    }

    public function test_verified_admin_can_crud_link_wildcards_without_registration_side_effects(): void
    {
        $this->seed(StatusSeeder::class);

        $admin = $this->createVerifiedAdmin();
        [$tournament, $tournamentCategory] = $this->createTournamentCategory($admin, 'open');

        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/admin/wildcards', [
            'tournament_category_id' => $tournamentCategory->id,
            'mode' => 'link',
            'email' => 'wildcard@test.dev',
            'partner_email' => 'partner@test.dev',
            'partner_name' => 'Partner',
            'wildcard_fee_waived' => true,
        ]);

        $create->assertOk();
        $wildcardId = (int) \App\Models\Invitation::query()
            ->where('email', 'wildcard@test.dev')
            ->where('purpose', 'wildcard')
            ->value('id');

        $this->assertDatabaseHas('invitations', [
            'id' => $wildcardId,
            'tournament_category_id' => $tournamentCategory->id,
            'email' => 'wildcard@test.dev',
            'partner_email' => 'partner@test.dev',
            'wildcard_fee_waived' => true,
            'purpose' => 'wildcard',
        ]);

        $update = $this->patchJson("/api/admin/wildcards/{$wildcardId}", [
            'tournament_category_id' => $tournamentCategory->id,
            'partner_name' => 'Partner Updated',
            'wildcard_fee_waived' => false,
            'status_id' => $this->statusId('invitation', 'pending'),
        ]);

        $update->assertOk();
        $this->assertDatabaseHas('invitations', [
            'id' => $wildcardId,
            'partner_name' => 'Partner Updated',
            'wildcard_fee_waived' => false,
        ]);

        $delete = $this->deleteJson("/api/admin/wildcards/{$wildcardId}");
        $delete->assertNoContent();
        $this->assertDatabaseMissing('invitations', ['id' => $wildcardId]);
    }

    public function test_verified_admin_can_crud_draft_brackets(): void
    {
        $this->seed(StatusSeeder::class);

        $admin = $this->createVerifiedAdmin();
        [, $tournamentCategory] = $this->createTournamentCategory($admin, 'amateur');

        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/admin/brackets', [
            'tournament_category_id' => $tournamentCategory->id,
            'status_id' => $this->statusId('bracket', 'draft'),
        ]);

        $create->assertCreated();
        $bracketId = (int) \App\Models\Bracket::query()
            ->where('tournament_category_id', $tournamentCategory->id)
            ->value('id');

        $this->assertDatabaseHas('brackets', [
            'id' => $bracketId,
            'tournament_category_id' => $tournamentCategory->id,
            'type' => 'single_elimination',
        ]);

        $publishedAt = now()->addHour()->setMicroseconds(0);
        $update = $this->patchJson("/api/admin/brackets/{$bracketId}", [
            'published_at' => $publishedAt->toIso8601String(),
        ]);

        $update->assertOk();

        $this->assertDatabaseHas('brackets', [
            'id' => $bracketId,
            'published_at' => $publishedAt->toDateTimeString(),
        ]);

        $delete = $this->deleteJson("/api/admin/brackets/{$bracketId}");
        $delete->assertNoContent();
        $this->assertDatabaseMissing('brackets', ['id' => $bracketId]);
    }

    private function createVerifiedAdmin(): User
    {
        $admin = User::query()->create([
            'name' => 'Admin CRUD',
            'email' => 'admin-crud@test.dev',
            'password_hash' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $admin->forceFill(['email_verified_at' => now()])->save();

        return $admin;
    }

    private function createTournamentCategory(User $admin, string $levelCode): array
    {
        $category = Category::query()->create([
            'name' => 'Category '.$levelCode,
            'display_name' => 'Category '.$levelCode,
            'group_code' => 'masculino',
            'level_code' => $levelCode,
            'sort_order' => 1,
        ]);

        $tournament = Tournament::query()->create([
            'name' => 'Tournament '.$levelCode,
            'mode' => 'amateur',
            'classification_method' => Tournament::CLASSIFICATION_SELF_SELECTED,
            'status_id' => $this->statusId('tournament', 'registration_open'),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'created_by' => $admin->id,
        ]);

        $tournamentCategory = TournamentCategory::query()->create([
            'tournament_id' => $tournament->id,
            'category_id' => $category->id,
            'max_teams' => 32,
            'wildcard_slots' => 0,
            'entry_fee_amount' => 20,
            'currency' => 'EUR',
            'acceptance_type' => 'waitlist',
            'seeding_rule' => 'ranking_desc',
        ]);

        return [$tournament, $tournamentCategory];
    }

    private function statusId(string $module, string $code): int
    {
        return (int) Status::query()
            ->where('module', $module)
            ->where('code', $code)
            ->value('id');
    }
}
