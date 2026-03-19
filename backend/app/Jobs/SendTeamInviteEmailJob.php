<?php

namespace App\Jobs;

use App\Mail\TeamInviteMail;
use App\Models\TeamInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendTeamInviteEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $teamInviteId) {}

    public function handle(): void
    {
        $invite = TeamInvite::query()->find($this->teamInviteId);
        if (! $invite || ! $invite->invited_email) {
            return;
        }

        $invite->increment('email_attempts');

        try {
            Mail::to($invite->invited_email)->send(new TeamInviteMail($invite->fresh()));

            $invite->forceFill([
                'email_sent_at' => now(),
                'email_last_error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $invite->forceFill([
                'email_last_error' => Str::limit($exception->getMessage(), 1000, ''),
            ])->save();

            throw $exception;
        }
    }
}
