<?php

namespace App\Services;

use App\Mail\TeamInviteMail;
use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamService
{
    public function __construct(
        protected StatusService $statusService,
        protected AcceptanceService $acceptanceService
    ) {
    }

    public function createTeamWithInvite(User $user, array $data): Team
    {
        $team = Team::create([
            'display_name' => $user->name ?: 'Equipo',
            'created_by' => $user->id,
        ]);

        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'slot' => 1,
        ]);

        $invitedUser = User::query()
            ->where('email', $data['partner_email'])
            ->first();

        $invite = TeamInvite::create([
            'team_id' => $team->id,
            'invited_email' => $data['partner_email'] ?? null,
            'invited_user_id' => $invitedUser?->id,
            'token' => Str::uuid()->toString(),
            'status_id' => $this->statusService->resolveStatusId('team_invite', 'sent'),
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($data['partner_email'])->send(new TeamInviteMail($invite));

        return $team->fresh(['users', 'invites']);
    }

    public function claimInvite(User $user, string $token): TeamInvite
    {
        $invite = TeamInvite::query()
            ->where('token', $token)
            ->with(['team', 'status'])
            ->firstOrFail();

        $this->ensureInviteIsClaimable($invite, $user->email);

        if ($invite->invited_user_id && $invite->invited_user_id !== $user->id) {
            throw ValidationException::withMessages([
                'token' => 'Esta invitación ya está asociada a otro usuario.',
            ]);
        }

        $invite->invited_user_id = $user->id;
        $invite->save();

        return $invite->fresh(['team.users', 'status']);
    }

    public function acceptInvite(User $user, TeamInvite $teamInvite): TeamInvite
    {
        $this->ensureInviteIsClaimable($teamInvite, $user->email);

        if ($teamInvite->invited_user_id && $teamInvite->invited_user_id !== $user->id) {
            throw ValidationException::withMessages([
                'invite' => 'No puedes aceptar esta invitación.',
            ]);
        }

        $team = $teamInvite->team()->with('users', 'members')->firstOrFail();

        if (! $team->users->contains($user->id)) {
            if ($team->members->count() >= 2) {
                throw ValidationException::withMessages([
                    'invite' => 'Este equipo ya completó el cupo de jugadores.',
                ]);
            }

            $nextSlot = ($team->members->max('slot') ?? 0) + 1;
            TeamMember::create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'slot' => $nextSlot,
            ]);
        }

        $this->statusService->transition($teamInvite, 'team_invite', $this->statusService->resolveStatusId('team_invite', 'accepted'));
        $teamInvite->invited_user_id = $user->id;
        $teamInvite->save();

        $this->linkRegistrationRanking($team, $user);
        $this->updateTeamDisplayName($team);
        $this->recalculateTeamRegistrations($team);

        return $teamInvite->fresh(['team.users', 'status']);
    }

    private function ensureInviteIsClaimable(TeamInvite $invite, string $userEmail): void
    {
        $invite->loadMissing('status');
        $status = $invite->status;

        if (! $status || $status->code !== 'sent') {
            throw ValidationException::withMessages([
                'invite' => 'Esta invitación ya no está disponible.',
            ]);
        }

        if ($invite->expires_at && $invite->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'invite' => 'Esta invitación ya expiró.',
            ]);
        }

        if (strcasecmp($invite->invited_email ?? '', $userEmail) !== 0) {
            throw ValidationException::withMessages([
                'invite' => 'Esta invitación corresponde a otro correo.',
            ]);
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

    private function recalculateTeamRegistrations(Team $team): void
    {
        $team->loadMissing('registrations');

        foreach ($team->registrations as $registration) {
            $this->acceptanceService->recalculateForTournamentCategory($registration->tournament_category_id);
        }
    }

    private function linkRegistrationRanking(Team $team, User $user): void
    {
        $team->loadMissing('registrations.rankings');

        foreach ($team->registrations as $registration) {
            $ranking = $registration->rankings()
                ->where('slot', 2)
                ->whereNull('user_id')
                ->where(function ($query) use ($user) {
                    $query->whereNull('invited_email')
                        ->orWhere('invited_email', $user->email);
                })
                ->first();

            if ($ranking) {
                $ranking->user_id = $user->id;
                $ranking->invited_email = $ranking->invited_email ?: $user->email;
                $ranking->save();
            }
        }
    }
}
