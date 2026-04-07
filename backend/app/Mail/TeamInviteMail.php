<?php

namespace App\Mail;

use App\Models\TeamInvite;
use Illuminate\Mail\Mailable;

class TeamInviteMail extends Mailable
{
    public function __construct(public TeamInvite $invite)
    {
    }

    public function build()
    {
        $invite = $this->invite->loadMissing([
            'invitedUser',
            'team.creator',
            'team.registration.tournamentCategory.tournament',
            'team.registration.tournamentCategory.category',
        ]);

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $apiUrl = rtrim((string) config('app.url'), '/');
        $hasExistingAccount = $invite->invitedUser !== null;
        $tournamentName = (string) ($invite->team?->registration?->tournamentCategory?->tournament?->name ?? 'ESTARS PADEL TOUR');
        $captainName = (string) ($invite->team?->creator?->name ?? 'Un jugador');

        return $this
            ->subject($hasExistingAccount
                ? "{$captainName} te invito a {$tournamentName}"
                : "Completa tu registro para reclamar tu invitacion en {$tournamentName}")
            ->view('emails.team-invite')
            ->with([
                'invite' => $invite,
                'logoUrl' => $apiUrl.'/emails/estars-logo.png',
                'hasExistingAccount' => $hasExistingAccount,
                'captainName' => $captainName,
                'tournamentName' => $tournamentName,
                'categoryName' => (string) ($invite->team?->registration?->tournamentCategory?->category?->display_name
                    ?: $invite->team?->registration?->tournamentCategory?->category?->name
                    ?: 'Categoria por confirmar'),
                'actionUrl' => $hasExistingAccount
                    ? $frontendUrl.'/login'
                    : "{$frontendUrl}/invite/{$invite->token}",
                'actionLabel' => $hasExistingAccount
                    ? 'Revisar mis invitaciones'
                    : 'Registrar mi cuenta',
            ]);
    }
}
