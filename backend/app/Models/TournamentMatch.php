<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentMatch extends Model
{
    /** @use HasFactory<\Database\Factories\TournamentMatchFactory> */
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'tournament_category_id',
        'bracket_id',
        'round_number',
        'match_number',
        'registration_a_id',
        'registration_b_id',
        'status_id',
        'scheduled_at',
        'not_before_at',
        'estimated_duration_minutes',
        'court',
        'score_json',
        'winner_registration_id',
        'updated_by',
        'updated_at_daily',
    ];

    protected $casts = [
        'tournament_category_id' => 'integer',
        'round_number' => 'integer',
        'match_number' => 'integer',
        'scheduled_at' => 'datetime',
        'not_before_at' => 'datetime',
        'estimated_duration_minutes' => 'integer',
        'score_json' => 'array',
        'updated_at_daily' => 'date',
    ];

    public function bracket()
    {
        return $this->belongsTo(Bracket::class);
    }

    public function tournamentCategory()
    {
        return $this->belongsTo(TournamentCategory::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function registrationA()
    {
        return $this->belongsTo(Registration::class, 'registration_a_id');
    }

    public function registrationB()
    {
        return $this->belongsTo(Registration::class, 'registration_b_id');
    }

    public function winnerRegistration()
    {
        return $this->belongsTo(Registration::class, 'winner_registration_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
