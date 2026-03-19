<?php

namespace App\Services;

use App\Jobs\SendTeamInviteEmailJob;
use App\Models\Registration;
use App\Models\RegistrationRanking;
use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\TeamMember;
use App\Models\TournamentCategory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamService
{
    public function __construct(
        protected StatusService $statusService,
        protected AcceptanceService $acceptanceService
    ) {}

    public function createPendingTeamForTournament(
        User $captain,
        int $tournamentCategoryId,
        string $partnerEmail,
        array $registrationData = []
    ): Registration {
        $normalizedEmail = Str::lower(trim($partnerEmail));
        $partner = User::query()
            ->where('email', $normalizedEmail)
            ->first();

        if (! $partner) {
            throw ValidationException::withMessages([
                'partner_email' => 'This player does not have an account yet. Your partner must register first.',
            ]);
        }

        if ($partner->id === $captain->id) {
            throw ValidationException::withMessages([
                'partner_email' => 'No puedes invitar tu propio correo como partner.',
            ]);
        }

        $category = TournamentCategory::query()
            ->with('tournament')
            ->findOrFail($tournamentCategoryId);

        $existing = $this->findPendingRegistrationByCaptainTournamentAndPartner(
            $captain->id,
            $tournamentCategoryId,
            $normalizedEmail
        );
        if ($existing) {
            return $existing;
        }

        $tournamentId = (int) $category->tournament_id;

        $this->assertUserCanParticipateInTournament($captain, $tournamentId);
        $this->assertUserCanParticipateInTournament($partner, $tournamentId);

        $registration = DB::transaction(function () use (
            $captain,
            $partner,
            $tournamentCategoryId,
            $normalizedEmail,
            $registrationData
        ): Registration {
            $pendingAgain = $this->findPendingRegistrationByCaptainTournamentAndPartner(
                $captain->id,
                $tournamentCategoryId,
                $normalizedEmail,
                true
            );
            if ($pendingAgain) {
                return $pendingAgain;
            }

            $team = Team::query()->create([
                'display_name' => $captain->name ?: 'Equipo',
                'created_by' => $captain->id,
                'status_id' => $this->statusService->resolveStatusId('team', Team::STATUS_PENDING_PARTNER_ACCEPTANCE),
            ]);

            TeamMember::query()->create([
                'team_id' => $team->id,
                'user_id' => $captain->id,
                'slot' => 1,
                'role' => TeamMember::ROLE_CAPTAIN,
            ]);

            $registration = Registration::query()->create([
                'tournament_category_id' => $tournamentCategoryId,
                'team_id' => $team->id,
                'status_id' => $this->statusService->resolveStatusId('registration', 'pending'),
            ]);

            $selfRanking = array_key_exists('self_ranking_value', $registrationData) && $registrationData['self_ranking_value'] !== null
                ? (int) $registrationData['self_ranking_value']
                : null;
            $partnerRanking = array_key_exists('partner_ranking_value', $registrationData) && $registrationData['partner_ranking_value'] !== null
                ? (int) $registrationData['partner_ranking_value']
                : null;
            $selfSource = isset($registrationData['self_ranking_source'])
                ? strtoupper((string) $registrationData['self_ranking_source'])
                : null;
            $partnerSource = isset($registrationData['partner_ranking_source'])
                ? strtoupper((string) $registrationData['partner_ranking_source'])
                : null;

            RegistrationRanking::query()->create([
                'registration_id' => $registration->id,
                'tournament_category_id' => $tournamentCategoryId,
                'slot' => 1,
                'user_id' => $captain->id,
                'ranking_value' => $selfRanking,
                'ranking_source' => $selfRanking ? $selfSource : null,
            ]);

            RegistrationRanking::query()->create([
                'registration_id' => $registration->id,
                'tournament_category_id' => $tournamentCategoryId,
                'slot' => 2,
                'user_id' => $partner->id,
                'invited_email' => $normalizedEmail,
                'ranking_value' => $partnerRanking,
                'ranking_source' => $partnerRanking ? $partnerSource : null,
            ]);

            $invite = TeamInvite::query()->create([
                'team_id' => $team->id,
                'invited_email' => $normalizedEmail,
                'invited_user_id' => $partner->id,
                'token' => (string) Str::uuid(),
                'status_id' => $this->statusService->resolveStatusId('team_invite', TeamInvite::STATUS_PENDING),
                'expires_at' => now()->addDays(7),
            ]);

            SendTeamInviteEmailJob::dispatch($invite->id)->afterCommit();

            return $registration->fresh([
                'status',
                'team.status',
                'team.users',
                'team.invites.status',
                'tournamentCategory.tournament',
                'tournamentCategory.category',
                'rankings',
            ]);
        });

        return $registration;
    }

    public function createTeamWithInvite(User $user, array $data): Team
    {
        $partnerEmail = Str::lower(trim((string) ($data['partner_email'] ?? '')));

        return DB::transaction(function () use ($user, $partnerEmail): Team {
            $team = Team::query()->create([
                'display_name' => $user->name ?: 'Equipo',
                'created_by' => $user->id,
                'status_id' => $this->statusService->resolveStatusId(
                    'team',
                    $partnerEmail === '' ? Team::STATUS_CONFIRMED : Team::STATUS_PENDING_PARTNER_ACCEPTANCE
                ),
            ]);

            TeamMember::query()->create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'slot' => 1,
                'role' => TeamMember::ROLE_CAPTAIN,
            ]);

            if ($partnerEmail !== '') {
                $invitedUser = User::query()
                    ->where('email', $partnerEmail)
                    ->first();

                $invite = TeamInvite::query()->create([
                    'team_id' => $team->id,
                    'invited_email' => $partnerEmail,
                    'invited_user_id' => $invitedUser?->id,
                    'token' => (string) Str::uuid(),
                    'status_id' => $this->statusService->resolveStatusId('team_invite', TeamInvite::STATUS_PENDING),
                    'expires_at' => now()->addDays(7),
                ]);

                SendTeamInviteEmailJob::dispatch($invite->id)->afterCommit();
            }

            return $team->fresh(['users', 'invites']);
        });
    }

    public function claimInvite(User $user, string $token): TeamInvite
    {
        $invite = TeamInvite::query()
            ->where('token', $token)
            ->with(['team', 'status'])
            ->firstOrFail();

        $this->ensureInviteIsClaimable($invite, $user);

        if ($invite->invited_user_id && $invite->invited_user_id !== $user->id) {
            throw ValidationException::withMessages([
                'token' => 'Esta invitación ya está asociada a otro usuario.',
            ]);
        }

        $invite->invited_user_id = $user->id;
        $invite->save();

        return $invite->fresh(['team.users', 'status']);
    }

    /**
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function acceptInvite(User $user, TeamInvite|int $teamInvite): TeamInvite
    {
        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => 'Please verify your email before accepting this invite.',
            ]);
        }

        $inviteId = $teamInvite instanceof TeamInvite ? (int) $teamInvite->id : (int) $teamInvite;

        return DB::transaction(function () use ($user, $inviteId): TeamInvite {
            $invite = TeamInvite::query()
                ->whereKey($inviteId)
                ->lockForUpdate()
                ->with(['status', 'team'])
                ->firstOrFail();

            $this->ensureInviteIsClaimable($invite, $user);

            $team = Team::query()
                ->whereKey($invite->team_id)
                ->lockForUpdate()
                ->firstOrFail();

            $registration = Registration::query()
                ->where('team_id', $team->id)
                ->lockForUpdate()
                ->with(['status', 'tournamentCategory.tournament'])
                ->first();

            if (! $registration) {
                throw ValidationException::withMessages([
                    'invite' => 'La invitación no tiene una inscripción asociada.',
                ]);
            }

            $tournamentId = (int) $registration->tournamentCategory?->tournament_id;
            if ($tournamentId > 0) {
                $this->assertUserCanParticipateInTournament($user, $tournamentId, (int) $registration->id);
                if ($team->created_by) {
                    $captain = User::query()->find($team->created_by);
                    if ($captain) {
                        $this->assertUserCanParticipateInTournament($captain, $tournamentId, (int) $registration->id);
                    }
                }
            }

            $existingPartner = TeamMember::query()
                ->where('team_id', $team->id)
                ->where('role', TeamMember::ROLE_PARTNER)
                ->first();

            if ($existingPartner && (int) $existingPartner->user_id !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'invite' => 'Este equipo ya tiene un partner distinto.',
                ]);
            }

            if (! $existingPartner) {
                TeamMember::query()->create([
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                    'slot' => 2,
                    'role' => TeamMember::ROLE_PARTNER,
                ]);
            }

            $invite->invited_user_id = $user->id;
            $invite->save();

            $this->statusService->transition(
                $invite,
                'team_invite',
                $this->statusService->resolveStatusId('team_invite', TeamInvite::STATUS_ACCEPTED),
                $user->id,
                'invite_accepted'
            );

            $this->statusService->transition(
                $team,
                'team',
                $this->statusService->resolveStatusId('team', Team::STATUS_CONFIRMED),
                $user->id,
                'partner_accepted_invite'
            );

            $this->updateTeamDisplayName($team->fresh('users'));
            $this->linkRegistrationRanking($team, $user);
            $this->acceptanceService->recalculateForTournamentCategory((int) $registration->tournament_category_id);

            return $invite->fresh(['team.users', 'status']);
        });
    }

    /**
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function rejectInvite(User $user, TeamInvite|int $teamInvite): TeamInvite
    {
        $inviteId = $teamInvite instanceof TeamInvite ? (int) $teamInvite->id : (int) $teamInvite;

        return DB::transaction(function () use ($user, $inviteId): TeamInvite {
            $invite = TeamInvite::query()
                ->whereKey($inviteId)
                ->lockForUpdate()
                ->with(['status', 'team'])
                ->firstOrFail();

            $this->ensureInviteIsClaimable($invite, $user);

            $team = Team::query()
                ->whereKey($invite->team_id)
                ->lockForUpdate()
                ->firstOrFail();

            $registration = Registration::query()
                ->where('team_id', $team->id)
                ->lockForUpdate()
                ->first();

            $this->statusService->transition(
                $invite,
                'team_invite',
                $this->statusService->resolveStatusId('team_invite', TeamInvite::STATUS_REJECTED),
                $user->id,
                'invite_rejected'
            );

            $this->statusService->transition(
                $team,
                'team',
                $this->statusService->resolveStatusId('team', Team::STATUS_CANCELLED),
                $user->id,
                'partner_rejected_invite'
            );

            if ($registration && $registration->status?->code !== 'cancelled') {
                $this->statusService->transition(
                    $registration,
                    'registration',
                    $this->statusService->resolveStatusId('registration', 'cancelled'),
                    $user->id,
                    'partner_rejected_invite'
                );
                $registration->cancelled_at = now();
                $registration->save();
            }

            return $invite->fresh(['team.users', 'status']);
        });
    }

    /**
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function resendInvite(User $actor, TeamInvite|int $teamInvite): TeamInvite
    {
        $inviteId = $teamInvite instanceof TeamInvite ? (int) $teamInvite->id : (int) $teamInvite;

        return DB::transaction(function () use ($actor, $inviteId): TeamInvite {
            $invite = TeamInvite::query()
                ->whereKey($inviteId)
                ->lockForUpdate()
                ->with(['status', 'team'])
                ->firstOrFail();

            if ((int) ($invite->team?->created_by ?? 0) !== (int) $actor->id) {
                throw new AuthorizationException('No autorizado para reenviar esta invitación.');
            }

            if ($invite->status?->code !== TeamInvite::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'invite' => 'Solo puedes reenviar invitaciones pendientes.',
                ]);
            }

            if ($invite->expires_at && $invite->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'invite' => 'La invitación ya expiró.',
                ]);
            }

            SendTeamInviteEmailJob::dispatch($invite->id)->afterCommit();

            return $invite->fresh(['team.users', 'status']);
        });
    }

    private function ensureInviteIsClaimable(TeamInvite $invite, User $user): void
    {
        $invite->loadMissing('status');
        $status = $invite->status;

        if (! $status || $status->code !== TeamInvite::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'invite' => 'Esta invitación ya no está disponible.',
            ]);
        }

        if ($invite->expires_at && $invite->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'invite' => 'Esta invitación ya expiró.',
            ]);
        }

        if (
            strcasecmp((string) ($invite->invited_email ?? ''), (string) $user->email) !== 0 &&
            (! $invite->invited_user_id || (int) $invite->invited_user_id !== (int) $user->id)
        ) {
            throw new AuthorizationException('Esta invitación corresponde a otro correo.');
        }
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

    private function findPendingRegistrationByCaptainTournamentAndPartner(
        int $captainId,
        int $tournamentCategoryId,
        string $partnerEmail,
        bool $lock = false
    ): ?Registration {
        $query = Registration::query()
            ->where('tournament_category_id', $tournamentCategoryId)
            ->whereHas('team', function ($teamQuery) use ($captainId) {
                $teamQuery
                    ->where('created_by', $captainId)
                    ->whereHas('status', fn ($statusQuery) => $statusQuery->where('code', Team::STATUS_PENDING_PARTNER_ACCEPTANCE));
            })
            ->whereHas('team.invites', function ($inviteQuery) use ($partnerEmail) {
                $inviteQuery
                    ->where('invited_email', $partnerEmail)
                    ->whereHas('status', fn ($statusQuery) => $statusQuery->where('code', TeamInvite::STATUS_PENDING));
            })
            ->with([
                'status',
                'team.status',
                'team.users',
                'team.invites.status',
                'tournamentCategory.tournament',
                'tournamentCategory.category',
                'rankings',
            ]);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function assertUserCanParticipateInTournament(
        User $user,
        int $tournamentId,
        ?int $ignoreRegistrationId = null
    ): void {
        $query = Registration::query()
            ->whereHas('tournamentCategory', fn ($categoryQuery) => $categoryQuery->where('tournament_id', $tournamentId))
            ->whereHas('team.users', fn ($teamUsersQuery) => $teamUsersQuery->where('users.id', $user->id))
            ->whereHas('status', fn ($statusQuery) => $statusQuery->whereNotIn('code', ['cancelled', 'rejected', 'expired']));

        if ($ignoreRegistrationId) {
            $query->where('id', '!=', $ignoreRegistrationId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'partner_email' => 'A player cannot participate in more than one team in the same tournament.',
            ]);
        }
    }

    private function linkRegistrationRanking(Team $team, User $user): void
    {
        $team->loadMissing('registration.rankings');
        $registration = $team->registration;
        if (! $registration) {
            return;
        }

        $ranking = $registration->rankings
            ->firstWhere('slot', 2);

        if ($ranking) {
            $ranking->user_id = $user->id;
            $ranking->invited_email = $ranking->invited_email ?: $user->email;
            $ranking->save();
        }
    }
}
