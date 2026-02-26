<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerPrizePayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tournament_id',
        'tournament_category_id',
        'position',
        'amount_eur_cents',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount_eur_cents' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function tournamentCategory()
    {
        return $this->belongsTo(TournamentCategory::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
