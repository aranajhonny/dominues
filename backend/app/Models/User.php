<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'active',
        'balance',
        'reserved_balance',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'balance' => 'decimal:2',
            'reserved_balance' => 'decimal:2',
        ];
    }

    protected $appends = ['kyc_status'];

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function kycDocument(): HasOne
    {
        return $this->hasOne(KycDocument::class)->latestOfMany();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getKycStatusAttribute(): string
    {
        $latest = $this->kycDocument;
        return $latest ? $latest->status : 'none';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isHost(): bool
    {
        return $this->role === 'host';
    }

    public function canAccessBackoffice(): bool
    {
        return in_array($this->role, ['admin', 'host', 'business'], true);
    }

    public function getAvailableBalanceAttribute(): float
    {
        return (float) $this->balance - (float) $this->reserved_balance;
    }
}