<?php

namespace App\Services;

use App\Models\OpenEntry;
use App\Models\Registration;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpenEntryService
{
    public function __construct(
        protected StatusService $statusService
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
}
