<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusHistory extends Model
{
    /** @use HasFactory<\Database\Factories\StatusHistoryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'module',
        'entity_type',
        'entity_id',
        'from_status_id',
        'to_status_id',
        'changed_by',
        'reason',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'from_status_id' => 'integer',
        'to_status_id' => 'integer',
        'changed_by' => 'integer',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function fromStatus()
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    public function toStatus()
    {
        return $this->belongsTo(Status::class, 'to_status_id');
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
