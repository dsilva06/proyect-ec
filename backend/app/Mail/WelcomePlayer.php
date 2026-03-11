<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;

class WelcomePlayer extends Mailable
{
    public function __construct(public User $user)
    {
    }

    public function build()
    {
        return $this
            ->subject('Bienvenido a Estars Padel Tour')
            ->view('emails.welcome-player')
            ->with([
                'user' => $this->user,
            ]);
    }
}