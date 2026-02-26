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
        'is_verified',
        'verified_at',
        'verified_by_user_id',
    ];

    protected $casts = [
        'slot' => 'integer',
        'ranking_value' => 'integer',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
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

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
