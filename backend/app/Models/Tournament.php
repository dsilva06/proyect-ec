<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    /** @use HasFactory<\Database\Factories\TournamentFactory> */
    use HasFactory;

    public const CLASSIFICATION_SELF_SELECTED = 'self_selected';

    public const CLASSIFICATION_REFEREE_ASSIGNED = 'referee_assigned';

    protected $fillable = [
        'circuit_id',
        'name',
        'description',
        'mode',
        'classification_method',
        'status_id',
        'venue_name',
        'venue_address',
        'city',
        'province_state',
        'country',
        'timezone',
        'start_date',
        'end_date',
        'entry_fee_amount',
        'entry_fee_currency',
        'registration_open_at',
        'registration_close_at',
        'day_start_time',
        'day_end_time',
        'match_duration_minutes',
        'courts_count',
        'prize_money',
        'prize_currency',
        'created_by',
    ];

    protected $attributes = [
        'classification_method' => self::CLASSIFICATION_SELF_SELECTED,
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'entry_fee_amount' => 'integer',
        'registration_open_at' => 'datetime',
        'registration_close_at' => 'datetime',
        'day_start_time' => 'string',
        'day_end_time' => 'string',
        'match_duration_minutes' => 'integer',
        'courts_count' => 'integer',
        'prize_money' => 'decimal:2',
    ];

    public function circuit()
    {
        return $this->belongsTo(Circuit::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categories()
    {
        return $this->hasMany(TournamentCategory::class);
    }

    public function openEntries()
    {
        return $this->hasMany(OpenEntry::class);
    }

    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class);
    }
}
