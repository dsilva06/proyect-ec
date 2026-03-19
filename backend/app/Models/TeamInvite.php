<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamInvite extends Model
{
    /** @use HasFactory<\Database\Factories\TeamInviteFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'team_id',
        'invited_email',
        'invited_phone',
        'invited_user_id',
        'invited_ranking_value',
        'invited_ranking_source',
        'token',
        'status_id',
        'expires_at',
        'email_sent_at',
        'email_last_error',
        'email_attempts',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'invited_ranking_value' => 'integer',
        'email_attempts' => 'integer',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function invitedUser()
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }
}
