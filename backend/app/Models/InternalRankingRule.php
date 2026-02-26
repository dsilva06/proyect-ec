<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalRankingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'win_points',
        'final_played_bonus',
        'final_won_bonus',
        'updated_by',
    ];

    protected $casts = [
        'win_points' => 'integer',
        'final_played_bonus' => 'integer',
        'final_won_bonus' => 'integer',
    ];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
