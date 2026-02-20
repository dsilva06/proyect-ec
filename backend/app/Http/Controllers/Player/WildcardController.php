<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegistrationResource;
use App\Http\Resources\WildcardInvitationResource;
use App\Models\Invitation;
use App\Services\RegistrationService;
use App\Services\StatusService;
use App\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WildcardController extends Controller
{
    public function show(string $token)
    {
        $invite = Invitation::query()
            ->where('token', $token)
            ->where('purpose', 'wildcard')
            ->with(['status', 'tournamentCategory.category', 'tournamentCategory.tournament.status'])
            ->firstOrFail();

        return new WildcardInvitationResource($invite);
    }

    public function claim(Request $request, string $token)
    {
        $data = $request->validate([
            'partner_email' => ['nullable', 'email', 'max:255'],
        ]);

        $invite = Invitation::query()
            ->where('token', $token)
            ->where('purpose', 'wildcard')
            ->with(['status'])
            ->firstOrFail();

        $status = $invite->status;
        if (! $status || $status->code !== 'pending') {
            throw ValidationException::withMessages([
                'token' => 'La invitación ya no está disponible.',
            ]);
        }

        if ($invite->expires_at && $invite->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => 'La invitación ya expiró.',
            ]);
        }

        $user = $request->user();
        if ($invite->email && strcasecmp($invite->email, $user->email) !== 0) {
            throw ValidationException::withMessages([
                'token' => 'Esta invitación corresponde a otro correo.',
            ]);
        }

        $partnerEmail = $data['partner_email'] ?? $invite->partner_email;
        if (! $partnerEmail) {
            throw ValidationException::withMessages([
                'partner_email' => 'Ingresa el email del partner.',
            ]);
        }

        $team = app(TeamService::class)->createTeamWithInvite($user, [
            'partner_email' => $partnerEmail,
        ]);

        $invite->user_id = $user->id;
        $invite->team_id = $team->id;
        $invite->save();

        app(StatusService::class)->transition($invite, 'invitation', app(StatusService::class)->resolveStatusId('invitation', 'accepted'));

        $registration = app(RegistrationService::class)->create(
            $user,
            $team->id,
            $invite->tournament_category_id,
            [],
            true,
            (bool) $invite->wildcard_fee_waived,
            $invite->id
        );

        $registration->load([
            'status',
            'team.users.playerProfile',
            'tournamentCategory.tournament.status',
            'tournamentCategory.category',
        ]);

        return new RegistrationResource($registration);
    }
}
