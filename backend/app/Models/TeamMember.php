<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TeamMember extends Pivot
{
    /** @use HasFactory<\Database\Factories\TeamMemberFactory> */
    use HasFactory;

    public const ROLE_CAPTAIN = 'captain';

    public const ROLE_PARTNER = 'partner';

    protected $table = 'team_members';

    public $incrementing = false;

    protected $fillable = [
        'team_id',
        'user_id',
        'slot',
        'role',
    ];

    protected $casts = [
        'slot' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (TeamMember $member): void {
            if (! $member->role) {
                $member->role = ((int) $member->slot === 1)
                    ? self::ROLE_CAPTAIN
                    : self::ROLE_PARTNER;
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
