<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class PendingRegistrationVerificationMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $logoUrl,
        public string $verificationEntryUrl,
        public string $loginUrl,
    ) {
    }

    public function build()
    {
        return $this
            ->subject('Verifica tu correo - ESTARS PADEL TOUR')
            ->view('emails.verify-email')
            ->with([
                'name' => $this->name,
                'logoUrl' => $this->logoUrl,
                'verificationEntryUrl' => $this->verificationEntryUrl,
                'loginUrl' => $this->loginUrl,
            ]);
    }
}
