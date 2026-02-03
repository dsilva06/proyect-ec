<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TeamMember extends Pivot
{
    /** @use HasFactory<\Database\Factories\TeamMemberFactory> */
    use HasFactory;

    protected $table = 'team_members';
    public $incrementing = false;

    protected $fillable = [
        'team_id',
        'user_id',
        'slot',
    ];

    protected $casts = [
        'slot' => 'integer',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
