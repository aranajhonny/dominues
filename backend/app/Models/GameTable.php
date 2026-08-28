<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameTable extends Model
{
    protected $fillable = [
        'game_id',
        'name',
        'status',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}