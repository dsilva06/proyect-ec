<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamInvite extends Model
{
    /** @use HasFactory<\Database\Factories\TeamInviteFactory> */
    use HasFactory;

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
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'invited_ranking_value' => 'integer',
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
