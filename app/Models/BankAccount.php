<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Model<BankAccount>
 */
class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_account_id',
        'name',
        'account_number',
        'currency',
        'balance',
        'status',
        'sync_enabled',
        'last_sync_at',
        'sync_frequency',
        'settings',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'sync_enabled' => 'boolean',
        'last_sync_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * @return BelongsTo<User, BankAccount>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Transaction>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @param Builder<BankAccount> $query
     * @return Builder<BankAccount>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param Builder<BankAccount> $query
     * @return Builder<BankAccount>
     */
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