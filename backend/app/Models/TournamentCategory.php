<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentCategory extends Model
{
    /** @use HasFactory<\Database\Factories\TournamentCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'category_id',
        'max_teams',
        'entry_fee_cents',
        'currency',
        'acceptance_type',
        'acceptance_window_hours',
        'seeding_rule',
        'status_id',
    ];

    protected $casts = [
        'max_teams' => 'integer',
        'entry_fee_cents' => 'integer',
        'acceptance_window_hours' => 'integer',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function brackets()
    {
        return $this->hasMany(Bracket::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }
}
