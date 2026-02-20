<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    /** @use HasFactory<\Database\Factories\RegistrationFactory> */
    use HasFactory;

    protected $fillable = [
        'tournament_category_id',
        'team_id',
        'status_id',
        'queue_position',
        'seed_number',
        'team_ranking_score',
        'is_wildcard',
        'wildcard_fee_waived',
        'wildcard_invitation_id',
        'accepted_at',
        'payment_due_at',
        'cancelled_at',
        'notes_admin',
    ];

    protected $casts = [
        'queue_position' => 'integer',
        'seed_number' => 'integer',
        'team_ranking_score' => 'integer',
        'is_wildcard' => 'boolean',
        'wildcard_fee_waived' => 'boolean',
        'accepted_at' => 'datetime',
        'payment_due_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function tournamentCategory()
    {
        return $this->belongsTo(TournamentCategory::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function rankings()
    {
        return $this->hasMany(RegistrationRanking::class);
    }

    public function wildcardInvitation()
    {
        return $this->belongsTo(Invitation::class, 'wildcard_invitation_id');
    }
}
