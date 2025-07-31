<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TFactory
 * @extends Model<TFactory>
 */
class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_name',
        'account_name',
        'account_number',
        'iban',
        'currency',
        'balance',
        'last_sync_at',
        'provider', // nordigen, revolut, etc.
        'provider_account_id',
        'is_active',
        'sync_enabled',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'last_sync_at' => 'datetime',
        'is_active' => 'boolean',
        'sync_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSyncEnabled(Builder $query): Builder
    {
        return $query->where('sync_enabled', true);
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance, 2) . ' ' . $this->currency;
    }

    public function needsSync(): bool
    {
        if (!$this->sync_enabled) {
            return false;
        }

        return !$this->last_sync_at || 
               $this->last_sync_at->diffInHours(now()) >= config('banking.sync.interval', 3600);
    }
} 