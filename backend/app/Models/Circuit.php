<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Circuit extends Model
{
    /** @use HasFactory<\Database\Factories\CircuitFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }
}
