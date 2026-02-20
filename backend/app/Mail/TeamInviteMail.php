<?php

namespace App\Mail;

use App\Models\TeamInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TeamInvite $invite)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitación para unirte a un equipo',
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return new Content(
            view: 'emails.team-invite',
            with: [
                'invite' => $this->invite,
                'acceptUrl' => "{$frontendUrl}/invite/{$this->invite->token}",
            ],
        );
    }
}
