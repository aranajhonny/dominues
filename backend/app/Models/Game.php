<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'name',
        'mode',
        'min_bet',
        'max_players',
        'fee_percent',
        'active',
    ];

    protected $casts = [
        'min_bet' => 'decimal:2',
        'fee_percent' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function tables(): HasMany
    {
        return $this->hasMany(GameTable::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(MatchGame::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}