<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerCategoryRanking extends Model
{
    /** @use HasFactory<\Database\Factories\PlayerCategoryRankingFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'ranking_source',
        'ranking_value',
        'ranking_updated_at',
        'updated_by_user_id',
    ];

    protected $casts = [
        'ranking_value' => 'integer',
        'ranking_updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}

