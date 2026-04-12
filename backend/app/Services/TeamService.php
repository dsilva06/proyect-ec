<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationRanking;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentCategory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
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
                'status_id' => $this->statusService->resolveStatusId('team', Team::STATUS_CONFIRMED),
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

            return $this->finalizeRegistrationAfterSuccessfulPayment($registration, $actor);
        });
    }

    public function finalizeRegistrationAfterSuccessfulPayment(Registration|int $registration, ?User $actor = null): Registration
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
                    'team.members',
                    'team.creator',
                    'team.users.playerProfile',
                    'tournamentCategory.tournament.status',
                    'tournamentCategory.category',
                    'rankings.user.playerProfile',
                    'rankings.verifier',
                ])
                ->firstOrFail();

            $team = $registration->team;
            $partnerEmail = $this->resolvePartnerEmail($registration);
            $partnerUser = $partnerEmail
                ? User::query()->where('email', $partnerEmail)->first()
                : null;

            if ($team && $partnerUser) {
                $this->ensurePartnerMember($team, $partnerUser);
                $this->linkRegistrationRanking($team, $partnerUser);
                $this->updateTeamDisplayName($team->fresh('users'));
            }

            if ($team && $team->status?->code !== Team::STATUS_CONFIRMED) {
                $this->statusService->transition(
                    $team,
                    'team',
                    $this->statusService->resolveStatusId('team', Team::STATUS_CONFIRMED),
                    $actor?->id,
                    'payment_succeeded'
                );
            }

            $paidStatusId = $this->statusService->resolveStatusId('registration', 'paid');
            $registration->accepted_at = $registration->accepted_at ?? now();
            $registration->payment_due_at = null;
            if ((int) $registration->status_id !== (int) $paidStatusId) {
                $this->statusService->transition(
                    $registration,
                    'registration',
                    $paidStatusId,
                    $actor?->id,
                    'payment_succeeded'
                );
            } else {
                $registration->save();
            }

            $this->acceptanceService->recalculateForTournamentCategory((int) $registration->tournament_category_id);

            return $registration->fresh([
                'status',
                'payments.status',
                'payments.paidBy',
                'team.status',
                'team.creator',
                'team.users.playerProfile',
                'tournamentCategory.tournament.status',
                'tournamentCategory.category',
                'rankings.user.playerProfile',
                'rankings.verifier',
            ]);
        });
    }

    public function createTeam(User $user, array $data): Team
    {
        $partnerEmail = Str::lower(trim((string) ($data['partner_email'] ?? '')));

        if ($partnerEmail !== '' && strcasecmp($partnerEmail, (string) $user->email) === 0) {
            throw ValidationException::withMessages([
                'partner_email' => 'No puedes invitar tu propio correo como partner.',
            ]);
        }

        return DB::transaction(function () use ($user, $partnerEmail): Team {
            $team = Team::query()->create([
                'display_name' => $user->name ?: 'Equipo',
                'created_by' => $user->id,
                'status_id' => $this->statusService->resolveStatusId('team', Team::STATUS_CONFIRMED),
            ]);

            TeamMember::query()->create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'slot' => 1,
                'role' => TeamMember::ROLE_CAPTAIN,
            ]);

            if ($partnerEmail !== '') {
                $partnerUser = User::query()
                    ->where('email', $partnerEmail)
                    ->first();

                if ($partnerUser) {
                    TeamMember::query()->create([
                        'team_id' => $team->id,
                        'user_id' => $partnerUser->id,
                        'slot' => 2,
                        'role' => TeamMember::ROLE_PARTNER,
                    ]);

                    $this->updateTeamDisplayName($team->fresh('users'));
                }
            }

            return $team->fresh(['users', 'status']);
        });
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
                $teamQuery->where('created_by', $captainId);
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

    private function ensurePartnerMember(Team $team, User $user): void
    {
        $team->loadMissing('members');

        $existingPartner = $team->members->firstWhere('role', TeamMember::ROLE_PARTNER);
        if ($existingPartner && (int) $existingPartner->user_id === (int) $user->id) {
            return;
        }

        if ($existingPartner) {
            return;
        }

        TeamMember::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'slot' => 2,
            'role' => TeamMember::ROLE_PARTNER,
        ]);
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
