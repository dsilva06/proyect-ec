<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusTransition extends Model
{
    /** @use HasFactory<\Database\Factories\StatusTransitionFactory> */
    use HasFactory;

    protected $fillable = [
        'module',
        'from_status_id',
        'to_status_id',
        'allowed_roles',
    ];

    protected $casts = [
        'allowed_roles' => 'array',
    ];

    public function fromStatus()
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    public function toStatus()
    {
        return $this->belongsTo(Status::class, 'to_status_id');
    }
}
