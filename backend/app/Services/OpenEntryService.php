<?php

namespace App\Services;

use App\Models\OpenEntry;
use App\Models\Registration;
use App\Models\RegistrationRanking;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpenEntryService
{
    public function __construct(
        protected StatusService $statusService,
        protected AcceptanceService $acceptanceService
    ) {
    }

    public function create(User $captain, array $data): OpenEntry
    {
        $tournamentId = (int) $data['tournament_id'];
        $tournament = Tournament::query()->findOrFail($tournamentId);
        $this->assertTournamentSupportsOpenIntake($tournament);

        $partnerEmail = Str::lower(trim((string) $data['partner_email']));
        $partner = User::query()->where('email', $partnerEmail)->first();

        if ($partner && (int) $partner->id === (int) $captain->id) {
            throw ValidationException::withMessages([
                'partner_email' => 'No puedes registrarte con tu propio correo como partner.',
            ]);
        }

        $existing = $this->findExistingOpenEntry((int) $captain->id, $tournamentId, $partnerEmail);
        if ($existing) {
            return $existing;
        }

        $this->assertCaptainCanParticipateInTournament($captain, $tournamentId);
        $this->assertPartnerCanParticipateInTournament($partnerEmail, $tournamentId, $partner?->id);

        return DB::transaction(function () use ($captain, $data, $tournamentId, $partnerEmail): OpenEntry {
            $existing = $this->findExistingOpenEntry((int) $captain->id, $tournamentId, $partnerEmail, true);
            if ($existing) {
                return $existing;
            }

            $team = Team::query()->create([
                'display_name' => $captain->name ?: 'Equipo',
                'created_by' => $captain->id,
                'status_id' => $this->statusService->resolveStatusId('team', Team::STATUS_CONFIRMED),
            ]);

            TeamMember::query()->create([
                'team_id' => $team->id,
                'user_id' => $captain->id,
                'slot' => 1,
                'role' => TeamMember::ROLE_CAPTAIN,
            ]);

            $entry = OpenEntry::query()->create([
                'tournament_id' => $tournamentId,
                'team_id' => $team->id,
                'submitted_by_user_id' => $captain->id,
                'segment' => (string) $data['segment'],
                'partner_email' => $partnerEmail,
                'partner_first_name' => trim((string) $data['partner_first_name']),
                'partner_last_name' => trim((string) $data['partner_last_name']),
                'partner_dni' => trim((string) $data['partner_dni']),
            ]);

            return $entry->fresh([
                'tournament.status',
                'team.status',
                'team.creator',
                'team.users.playerProfile',
                'payments.status',
                'payments.paidBy',
                'submittedBy.playerProfile',
            ]);
        });
    }

    public function markPaid(OpenEntry|int $openEntry): OpenEntry
    {
        $openEntryId = $openEntry instanceof OpenEntry ? (int) $openEntry->id : (int) $openEntry;

        return DB::transaction(function () use ($openEntryId): OpenEntry {
            $entry = OpenEntry::query()
                ->whereKey($openEntryId)
                ->lockForUpdate()
                ->with([
                    'tournament.status',
                    'team.status',
                    'team.creator',
                    'team.users.playerProfile',
                    'payments.status',
                    'payments.paidBy',
                    'submittedBy.playerProfile',
                ])
                ->firstOrFail();

            if (! $entry->paid_at) {
                $entry->paid_at = now();
                $entry->save();
            }

            return $entry->fresh([
                'tournament.status',
                'team.status',
                'team.creator',
                'team.users.playerProfile',
                'payments.status',
                'payments.paidBy',
                'submittedBy.playerProfile',
                'assignedTournamentCategory.category',
                'assignedTournamentCategory.tournament.status',
                'registration.status',
                'registration.openEntry',
                'registration.team.users.playerProfile',
                'registration.rankings.user.playerProfile',
                'assignedBy.playerProfile',
            ]);
        });
    }

    public function assignCategory(OpenEntry|int $openEntry, int $tournamentCategoryId, User $actor): OpenEntry
    {
        $openEntryId = $openEntry instanceof OpenEntry ? (int) $openEntry->id : (int) $openEntry;

        return DB::transaction(function () use ($openEntryId, $tournamentCategoryId, $actor): OpenEntry {
            $entry = OpenEntry::query()
                ->whereKey($openEntryId)
                ->lockForUpdate()
                ->with([
                    'tournament.status',
                    'team.status',
                    'team.creator',
                    'team.members',
                    'team.users.playerProfile',
                    'payments.status',
                    'payments.paidBy',
                    'submittedBy.playerProfile',
                    'registration.status',
                ])
                ->firstOrFail();

            if (! $entry->paid_at) {
                throw ValidationException::withMessages([
                    'open_entry_id' => 'Solo se puede asignar categoría a parejas OPEN ya pagadas.',
                ]);
            }

            if ($entry->registration_id || $entry->assignment_status === OpenEntry::ASSIGNMENT_ASSIGNED) {
                throw ValidationException::withMessages([
                    'open_entry_id' => 'Esta pareja OPEN ya fue asignada a una categoría.',
                ]);
            }

            $category = TournamentCategory::query()
                ->with(['tournament', 'category'])
                ->findOrFail($tournamentCategoryId);

            $this->assertCategoryCanReceiveOpenEntry($entry, $category);

            if (Registration::query()->where('team_id', $entry->team_id)->exists()) {
                throw ValidationException::withMessages([
                    'open_entry_id' => 'El equipo ya tiene una inscripción creada.',
                ]);
            }

            $partnerUser = User::query()
                ->where('email', $entry->partner_email)
                ->first();

            if ($entry->team && $partnerUser) {
                $this->ensurePartnerMember($entry->team, $partnerUser);
                $this->updateTeamDisplayName($entry->team->fresh('users'));
            }

            $registration = Registration::query()->create([
                'tournament_category_id' => $category->id,
                'team_id' => $entry->team_id,
                'status_id' => $this->statusService->resolveStatusId('registration', 'paid'),
                'accepted_at' => $entry->paid_at ?? now(),
                'payment_due_at' => null,
                'is_wildcard' => false,
                'wildcard_fee_waived' => false,
            ]);

            $registration->forceFill([
                'created_at' => $entry->paid_at ?? $entry->created_at ?? now(),
            ])->saveQuietly();

            $captainUserId = (int) ($entry->team?->members->firstWhere('slot', 1)?->user_id ?? $entry->submitted_by_user_id);
            $partnerUserId = $entry->team?->members->firstWhere('slot', 2)?->user_id ?: $partnerUser?->id;

            RegistrationRanking::query()->create([
                'registration_id' => $registration->id,
                'tournament_category_id' => $category->id,
                'slot' => 1,
                'user_id' => $captainUserId ?: null,
                'ranking_value' => null,
                'ranking_source' => null,
            ]);

            RegistrationRanking::query()->create([
                'registration_id' => $registration->id,
                'tournament_category_id' => $category->id,
                'slot' => 2,
                'user_id' => $partnerUserId,
                'invited_email' => $entry->partner_email,
                'ranking_value' => null,
                'ranking_source' => null,
            ]);

            $entry->forceFill([
                'assignment_status' => OpenEntry::ASSIGNMENT_ASSIGNED,
                'assigned_tournament_category_id' => $category->id,
                'registration_id' => $registration->id,
                'assigned_by_user_id' => $actor->id,
                'assigned_at' => now(),
            ])->save();

            $this->acceptanceService->recalculateForTournamentCategory($category->id);

            return $entry->fresh([
                'tournament.status',
                'team.status',
                'team.creator',
                'team.users.playerProfile',
                'payments.status',
                'payments.paidBy',
                'submittedBy.playerProfile',
                'assignedTournamentCategory.category',
                'assignedTournamentCategory.tournament.status',
                'registration.status',
                'registration.openEntry',
                'registration.team.users.playerProfile',
                'registration.rankings.user.playerProfile',
                'assignedBy.playerProfile',
            ]);
        });
    }

    private function assertTournamentSupportsOpenIntake(Tournament $tournament): void
    {
        if (strtolower((string) $tournament->mode) !== 'open') {
            throw ValidationException::withMessages([
                'tournament_id' => 'OPEN intake signup is only available for OPEN tournaments.',
            ]);
        }

        if ((string) $tournament->classification_method !== Tournament::CLASSIFICATION_REFEREE_ASSIGNED) {
            throw ValidationException::withMessages([
                'tournament_id' => 'This tournament does not use referee-assigned OPEN intake signup.',
            ]);
        }
    }

    private function findExistingOpenEntry(
        int $captainId,
        int $tournamentId,
        string $partnerEmail,
        bool $lock = false
    ): ?OpenEntry {
        $query = OpenEntry::query()
            ->where('tournament_id', $tournamentId)
            ->where('submitted_by_user_id', $captainId)
            ->where('partner_email', $partnerEmail)
            ->with([
                'tournament.status',
                'team.status',
                'team.creator',
                'team.users.playerProfile',
                'payments.status',
                'payments.paidBy',
                'submittedBy.playerProfile',
            ]);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function assertCaptainCanParticipateInTournament(User $captain, int $tournamentId): void
    {
        $registrationConflict = Registration::query()
            ->whereHas('tournamentCategory', fn ($query) => $query->where('tournament_id', $tournamentId))
            ->whereHas('team.users', fn ($query) => $query->where('users.id', $captain->id))
            ->whereHas('status', fn ($query) => $query->whereNotIn('code', ['cancelled', 'rejected', 'expired']))
            ->exists();

        $openEntryConflict = OpenEntry::query()
            ->where('tournament_id', $tournamentId)
            ->where('submitted_by_user_id', $captain->id)
            ->exists();

        if ($registrationConflict || $openEntryConflict) {
            throw ValidationException::withMessages([
                'partner_email' => 'A player cannot participate in more than one team in the same tournament.',
            ]);
        }
    }

    private function assertPartnerCanParticipateInTournament(string $partnerEmail, int $tournamentId, ?int $partnerUserId = null): void
    {
        $registrationConflict = Registration::query()
            ->whereHas('tournamentCategory', fn ($query) => $query->where('tournament_id', $tournamentId))
            ->whereHas('status', fn ($query) => $query->whereNotIn('code', ['cancelled', 'rejected', 'expired']))
            ->where(function ($query) use ($partnerEmail, $partnerUserId) {
                $query->whereHas('rankings', function ($rankingsQuery) use ($partnerEmail) {
                    $rankingsQuery
                        ->where('slot', 2)
                        ->where('invited_email', $partnerEmail);
                });

                if ($partnerUserId) {
                    $query->orWhereHas('team.users', fn ($teamUsersQuery) => $teamUsersQuery->where('users.id', $partnerUserId));
                }
            })
            ->exists();

        $openEntryConflict = OpenEntry::query()
            ->where('tournament_id', $tournamentId)
            ->where(function ($query) use ($partnerEmail, $partnerUserId) {
                $query->where('partner_email', $partnerEmail);

                if ($partnerUserId) {
                    $query->orWhere('submitted_by_user_id', $partnerUserId);
                }
            })
            ->exists();

        if ($registrationConflict || $openEntryConflict) {
            throw ValidationException::withMessages([
                'partner_email' => 'A player cannot participate in more than one team in the same tournament.',
            ]);
        }
    }

    private function assertCategoryCanReceiveOpenEntry(OpenEntry $entry, TournamentCategory $category): void
    {
        if ((int) $category->tournament_id !== (int) $entry->tournament_id) {
            throw ValidationException::withMessages([
                'tournament_category_id' => 'La categoría seleccionada no pertenece al mismo torneo OPEN.',
            ]);
        }

        if (strtolower((string) ($category->tournament?->mode ?? '')) !== 'open') {
            throw ValidationException::withMessages([
                'tournament_category_id' => 'La asignación OPEN solo puede apuntar a categorías OPEN del mismo torneo.',
            ]);
        }

        if ((string) ($category->tournament?->classification_method ?? '') !== Tournament::CLASSIFICATION_REFEREE_ASSIGNED) {
            throw ValidationException::withMessages([
                'tournament_category_id' => 'La categoría seleccionada no pertenece a un torneo OPEN con asignación por árbitro.',
            ]);
        }

        $groupCode = strtolower((string) ($category->category?->group_code ?? ''));
        $allowedGroupCodes = match ((string) $entry->segment) {
            OpenEntry::SEGMENT_MEN => ['masculino', 'mixed'],
            OpenEntry::SEGMENT_WOMEN => ['femenino', 'mixed'],
            default => [],
        };

        if ($groupCode !== '' && ! in_array($groupCode, $allowedGroupCodes, true)) {
            throw ValidationException::withMessages([
                'tournament_category_id' => 'La categoría seleccionada no coincide con el segmento del OPEN entry.',
            ]);
        }

        $activeRegistrations = Registration::query()
            ->where('tournament_category_id', $category->id)
            ->whereHas('status', fn ($query) => $query->whereNotIn('code', ['cancelled', 'rejected', 'expired']))
            ->count();

        if ($category->max_teams && $activeRegistrations >= (int) $category->max_teams) {
            throw ValidationException::withMessages([
                'tournament_category_id' => 'La categoría seleccionada ya alcanzó su capacidad máxima.',
            ]);
        }
    }

    private function ensurePartnerMember(Team $team, User $partnerUser): void
    {
        $existingPartner = $team->members->firstWhere('slot', 2);
        if ($existingPartner && (int) $existingPartner->user_id === (int) $partnerUser->id) {
            return;
        }

        if ($existingPartner) {
            $existingPartner->user_id = $partnerUser->id;
            $existingPartner->role = TeamMember::ROLE_PARTNER;
            $existingPartner->save();

            return;
        }

        TeamMember::query()->create([
            'team_id' => $team->id,
            'user_id' => $partnerUser->id,
            'slot' => 2,
            'role' => TeamMember::ROLE_PARTNER,
        ]);
    }

    private function updateTeamDisplayName(Team $team): void
    {
        $team->loadMissing('users');
        $names = $team->users
            ->sortBy('pivot.slot')
            ->pluck('name')
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return;
        }

        $team->display_name = $names->join(' / ');
        $team->save();
    }
}
