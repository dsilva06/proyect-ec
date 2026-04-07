<?php

namespace App\Services;

use App\Jobs\SendTeamInviteEmailJob;
use App\Models\Payment;
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
        protected AcceptanceService $acceptanceService,
        protected RegistrationService $registrationService
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

        if ($partner && $partner->id === $captain->id) {
            throw ValidationException::withMessages([
                'partner_email' => 'No puedes invitar tu propio correo como partner.',
            ]);
        }

        $category = TournamentCategory::query()
            ->with('tournament')
            ->findOrFail($tournamentCategoryId);
        $requiresRanking = strtolower((string) ($category->tournament?->mode ?? '')) !== 'open';

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
        if ($partner) {
            $this->assertUserCanParticipateInTournament($partner, $tournamentId);
        }

        $registration = DB::transaction(function () use (
            $captain,
            $partner,
            $tournamentCategoryId,
            $normalizedEmail,
            $registrationData,
            $requiresRanking
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

            $registration = $this->registrationService->create(
                $captain,
                (int) $team->id,
                $tournamentCategoryId,
                [
                    ...$registrationData,
                    'partner_email' => $normalizedEmail,
                    'partner_ranking_value' => $requiresRanking
                        ? ($registrationData['partner_ranking_value'] ?? null)
                        : null,
                    'partner_ranking_source' => $requiresRanking
                        ? ($registrationData['partner_ranking_source'] ?? null)
                        : null,
                ]
            );

            return $registration->fresh([
                'status',
                'team.status',
                'team.creator',
                'team.users',
                'team.invites.status',
                'payments.status',
                'tournamentCategory.tournament',
                'tournamentCategory.category',
                'rankings',
            ]);
        });

        return $registration;
    }

    public function payRegistration(User $actor, Registration|int $registration): Registration
    {
        $registrationId = $registration instanceof Registration ? (int) $registration->id : (int) $registration;

        return DB::transaction(function () use ($actor, $registrationId): Registration {
            $registration = Registration::query()
                ->whereKey($registrationId)
                ->lockForUpdate()
                ->with([
                    'status',
                    'payments.status',
                    'rankings',
                    'team.status',
                    'team.creator',
                    'team.invites.status',
                    'tournamentCategory.tournament',
                    'tournamentCategory.category',
                ])
                ->firstOrFail();

            if ((int) ($registration->team?->created_by ?? 0) !== (int) $actor->id) {
                throw new AuthorizationException('No autorizado para pagar esta inscripción.');
            }

            $statusCode = (string) ($registration->status?->code ?? '');
            if (in_array($statusCode, ['cancelled', 'rejected', 'expired'], true)) {
                throw ValidationException::withMessages([
                    'registration' => 'Esta inscripción ya no está disponible para pago.',
                ]);
            }

            if (in_array($statusCode, ['waitlisted', 'pending'], true)) {
                throw ValidationException::withMessages([
                    'registration' => 'Esta inscripción todavía no está lista para pago.',
                ]);
            }

            $successfulPayment = $registration->payments
                ->first(fn (Payment $payment) => $payment->status?->code === 'succeeded');

            if (! $successfulPayment) {
                $successfulPayment = $this->createSuccessfulPayment(
                    $registration,
                    $registration->tournamentCategory,
                    $actor
                );
            }

            return $this->ensureInviteAfterSuccessfulPayment($registration, $actor);
        });
    }

    public function ensureInviteAfterSuccessfulPayment(Registration|int $registration, ?User $actor = null): Registration
    {
        $registrationId = $registration instanceof Registration ? (int) $registration->id : (int) $registration;

        return DB::transaction(function () use ($registrationId, $actor): Registration {
            $registration = Registration::query()
                ->whereKey($registrationId)
                ->lockForUpdate()
                ->with([
                    'status',
                    'payments.status',
                    'payments.paidBy',
                    'rankings',
                    'team.status',
                    'team.creator',
                    'team.users.playerProfile',
                    'team.invites.status',
                    'tournamentCategory.tournament.status',
                    'tournamentCategory.category',
                    'rankings.user.playerProfile',
                    'rankings.verifier',
                ])
                ->firstOrFail();

            $invite = $registration->team?->invites
                ?->first(fn (TeamInvite $teamInvite) => strcasecmp((string) $teamInvite->invited_email, (string) $this->resolvePartnerEmail($registration)) === 0);

            $didCreateInvite = false;

            if (! $invite) {
                $partnerEmail = $this->resolvePartnerEmail($registration);
                $partnerUser = $partnerEmail
                    ? User::query()->where('email', $partnerEmail)->first()
                    : null;

                $invite = TeamInvite::query()->create([
                    'team_id' => (int) $registration->team_id,
                    'invited_email' => $partnerEmail,
                    'invited_user_id' => $partnerUser?->id,
                    'token' => (string) Str::uuid(),
                    'status_id' => $this->statusService->resolveStatusId('team_invite', TeamInvite::STATUS_PENDING),
                    'expires_at' => $this->resolveInviteExpiry($registration->tournamentCategory),
                ]);
                $didCreateInvite = true;
            }

            $awaitingPartnerStatusId = $this->statusService->resolveStatusId('registration', 'awaiting_partner_acceptance');
            if (
                (int) $registration->status_id !== (int) $awaitingPartnerStatusId
                && $registration->team?->status?->code === Team::STATUS_PENDING_PARTNER_ACCEPTANCE
            ) {
                $this->statusService->transition(
                    $registration,
                    'registration',
                    $awaitingPartnerStatusId,
                    $actor?->id,
                    'payment_succeeded_waiting_partner'
                );
            }

            $registration->accepted_at = $registration->accepted_at ?? now();
            $registration->payment_due_at = null;
            $registration->save();

            if ($didCreateInvite) {
                SendTeamInviteEmailJob::dispatch($invite->id)->afterCommit();
            }

            $this->acceptanceService->recalculateForTournamentCategory((int) $registration->tournament_category_id);

            return $registration->fresh([
                'status',
                'payments.status',
                'payments.paidBy',
                'team.status',
                'team.creator',
                'team.users.playerProfile',
                'team.invites.status',
                'tournamentCategory.tournament.status',
                'tournamentCategory.category',
                'rankings.user.playerProfile',
                'rankings.verifier',
            ]);
        });
    }

    public function refreshInviteState(TeamInvite $invite): TeamInvite
    {
        $invite->loadMissing(['status', 'team.status', 'team.registration.status']);

        if (
            $invite->status?->code === TeamInvite::STATUS_PENDING &&
            $invite->expires_at &&
            $invite->expires_at->isPast()
        ) {
            return DB::transaction(function () use ($invite): TeamInvite {
                $lockedInvite = TeamInvite::query()
                    ->whereKey($invite->id)
                    ->lockForUpdate()
                    ->with(['status', 'team.status', 'team.registration.status'])
                    ->firstOrFail();

                if ($lockedInvite->status?->code !== TeamInvite::STATUS_PENDING) {
                    return $lockedInvite;
                }

                if (! $lockedInvite->expires_at || ! $lockedInvite->expires_at->isPast()) {
                    return $lockedInvite;
                }

                $this->statusService->transition(
                    $lockedInvite,
                    'team_invite',
                    $this->statusService->resolveStatusId('team_invite', TeamInvite::STATUS_EXPIRED),
                    null,
                    'invite_expired'
                );

                $team = $lockedInvite->team;
                if ($team && ! in_array((string) $team->status?->code, [Team::STATUS_CONFIRMED, Team::STATUS_CANCELLED, Team::STATUS_EXPIRED], true)) {
                    $this->statusService->transition(
                        $team,
                        'team',
                        $this->statusService->resolveStatusId('team', Team::STATUS_EXPIRED),
                        null,
                        'invite_expired'
                    );
                }

                $registration = $team?->registration;
                if ($registration && ! in_array((string) $registration->status?->code, ['paid', 'cancelled', 'expired', 'rejected'], true)) {
                    $this->statusService->transition(
                        $registration,
                        'registration',
                        $this->statusService->resolveStatusId('registration', 'cancelled'),
                        null,
                        'invite_expired_after_payment'
                    );
                    $registration->cancelled_at = now();
                    $registration->save();
                }

                return $lockedInvite->fresh(['status', 'team.status', 'team.registration.status']);
            });
        }

        return $invite;
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

            $invite = $this->refreshInviteState($invite);

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
            $successfulPayment = $registration->payments()
                ->whereHas('status', fn ($statusQuery) => $statusQuery->where('code', 'succeeded'))
                ->exists();

            if ($successfulPayment || $registration->status?->code === 'awaiting_partner_acceptance') {
                $this->statusService->transition(
                    $registration,
                    'registration',
                    $this->statusService->resolveStatusId('registration', 'paid'),
                    $user->id,
                    'partner_accepted_after_payment'
                );
                $registration->accepted_at = $registration->accepted_at ?? now();
                $registration->payment_due_at = null;
                $registration->save();
            } else {
                $this->acceptanceService->recalculateForTournamentCategory((int) $registration->tournament_category_id);
            }

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

            $invite = $this->refreshInviteState($invite);

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

            $invite = $this->refreshInviteState($invite);

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
        $invite = $this->refreshInviteState($invite);
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
            ->whereHas('rankings', function ($rankingQuery) use ($partnerEmail) {
                $rankingQuery
                    ->where('slot', 2)
                    ->where('invited_email', $partnerEmail);
            })
            ->whereHas('status', function ($statusQuery) {
                $statusQuery->whereNotIn('code', ['cancelled', 'expired', 'rejected']);
            })
            ->with([
                'status',
                'team.status',
                'team.creator',
                'team.users',
                'team.invites.status',
                'payments.status',
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

    private function resolveInviteExpiry(?TournamentCategory $category): \Illuminate\Support\Carbon
    {
        $tournament = $category?->tournament;
        if ($tournament?->registration_close_at) {
            return $tournament->registration_close_at->copy();
        }

        if ($tournament?->end_date) {
            return $tournament->end_date->copy()->endOfDay();
        }

        return now()->addDays(7);
    }

    private function createSuccessfulPayment(
        Registration $registration,
        ?TournamentCategory $category,
        User $actor
    ): Payment {
        return Payment::query()->create([
            'registration_id' => $registration->id,
            'provider' => 'manual_checkout',
            'provider_intent_id' => (string) Str::uuid(),
            'amount_cents' => max(0, (int) ($category?->tournament?->entry_fee_amount ?? $category?->entry_fee_amount ?? 0)) * 100,
            'currency' => strtoupper((string) ($category?->tournament?->entry_fee_currency ?: $category?->currency ?: 'USD')),
            'status_id' => $this->statusService->resolveStatusId('payment', 'succeeded'),
            'paid_by_user_id' => $actor->id,
            'paid_at' => now(),
            'raw_payload' => [
                'source' => 'player_registration_checkout',
            ],
        ]);
    }

    private function resolvePartnerEmail(Registration $registration): ?string
    {
        $registration->loadMissing('rankings');

        $partnerRanking = $registration->rankings->firstWhere('slot', 2);

        return $partnerRanking?->invited_email
            ? Str::lower((string) $partnerRanking->invited_email)
            : null;
    }
}
