<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    /** @use HasFactory<\Database\Factories\StatusFactory> */
    use HasFactory;

    protected $fillable = [
        'module',
        'code',
        'label',
        'description',
        'is_terminal',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_terminal' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
