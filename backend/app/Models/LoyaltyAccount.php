<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoyaltyAccount extends Model
{
    /** @use HasFactory<\Database\Factories\LoyaltyAccountFactory> */
    use HasFactory;

    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'points_balance',
        'tier',
    ];

    protected $casts = [
        'points_balance' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
