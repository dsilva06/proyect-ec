<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bracket extends Model
{
    /** @use HasFactory<\Database\Factories\BracketFactory> */
    use HasFactory;

    protected $fillable = [
        'tournament_category_id',
        'type',
        'status_id',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function tournamentCategory()
    {
        return $this->belongsTo(TournamentCategory::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function slots()
    {
        return $this->hasMany(BracketSlot::class);
    }

    public function matches()
    {
        return $this->hasMany(TournamentMatch::class, 'bracket_id');
    }
}
