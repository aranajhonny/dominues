<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchGame extends Model
{
    protected $table = 'matches';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'game_id',
        'status',
        'players',
        'winner_id',
        'pot',
        'fee_amount',
        'prize',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'players' => 'array',
        'pot' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'prize' => 'decimal:2',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'match_id', 'id');
    }
}