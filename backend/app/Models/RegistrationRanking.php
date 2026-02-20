<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistrationRanking extends Model
{
    /** @use HasFactory<\Database\Factories\RegistrationRankingFactory> */
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'tournament_category_id',
        'slot',
        'user_id',
        'invited_email',
        'ranking_value',
        'ranking_source',
    ];

    protected $casts = [
        'slot' => 'integer',
        'ranking_value' => 'integer',
    ];

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function tournamentCategory()
    {
        return $this->belongsTo(TournamentCategory::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
