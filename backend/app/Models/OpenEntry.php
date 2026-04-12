<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenEntry extends Model
{
    /** @use HasFactory<\Database\Factories\OpenEntryFactory> */
    use HasFactory;

    public const SEGMENT_MEN = 'men';

    public const SEGMENT_WOMEN = 'women';

    public const ASSIGNMENT_PENDING = 'pending';

    public const ASSIGNMENT_ASSIGNED = 'assigned';

    protected $fillable = [
        'tournament_id',
        'team_id',
        'submitted_by_user_id',
        'segment',
        'partner_email',
        'partner_first_name',
        'partner_last_name',
        'partner_dni',
        'assignment_status',
        'paid_at',
        'assigned_tournament_category_id',
        'registration_id',
        'assigned_by_user_id',
        'assigned_at',
        'notes_admin',
    ];

    protected $attributes = [
        'assignment_status' => self::ASSIGNMENT_PENDING,
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'assigned_at' => 'datetime',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function assignedTournamentCategory()
    {
        return $this->belongsTo(TournamentCategory::class, 'assigned_tournament_category_id');
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
