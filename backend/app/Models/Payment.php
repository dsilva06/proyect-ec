<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'provider',
        'provider_intent_id',
        'amount_cents',
        'currency',
        'status_id',
        'paid_by_user_id',
        'paid_at',
        'failure_code',
        'failure_message',
        'raw_payload',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'paid_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }
}
