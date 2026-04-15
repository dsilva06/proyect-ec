<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerProfile extends Model
{
    /** @use HasFactory<\Database\Factories\PlayerProfileFactory> */
    use HasFactory;

    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'document_type',
        'document_number',
        'dni',
        'province_state',
        'country',
        'ranking_source',
        'ranking_value',
        'ranking_updated_at',
    ];

    protected $casts = [
        'ranking_value' => 'integer',
        'ranking_updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
