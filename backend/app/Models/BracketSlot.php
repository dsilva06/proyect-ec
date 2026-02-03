<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BracketSlot extends Model
{
    /** @use HasFactory<\Database\Factories\BracketSlotFactory> */
    use HasFactory;

    protected $fillable = [
        'bracket_id',
        'slot_number',
        'registration_id',
        'seed_number',
    ];

    protected $casts = [
        'slot_number' => 'integer',
        'seed_number' => 'integer',
    ];

    public function bracket()
    {
        return $this->belongsTo(Bracket::class);
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }
}
