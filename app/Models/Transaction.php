<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Model<Transaction>
 */
class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_account_id',
        'category_id',
        'external_id',
        'provider',
        'type', // credit, debit
        'amount',
        'currency',
        'description',
        'merchant_name',
        'merchant_id',
        'transaction_date',
        'booking_date', // Dodano booking_date do fillable
        'value_date',
        'status',
        'reference',
        'balance_after',
        'metadata',
        'is_imported',
        'ai_analyzed',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_date' => 'datetime',
        'booking_date' => 'datetime',
        'value_date' => 'datetime',
        'metadata' => 'array',
        'is_imported' => 'boolean',
        'ai_analyzed' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, Transaction>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<BankAccount, Transaction>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<Category, Transaction>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param Builder<Transaction> $query
     * @return Builder<Transaction>
     */
    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('type', 'credit');
    }

    /**
     * @param Builder<Transaction> $query
     * @return Builder<Transaction>
     */
    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('type', 'debit');
    }

    /**
     * @param Builder<Transaction> $query
     * @param string $fromDate
     * @param string $toDate
     * @return Builder<Transaction>
     */
    public function scopeByDateRange(Builder $query, string $fromDate, string $toDate): Builder
    {
        return $query->whereBetween('transaction_date', [$fromDate, $toDate]);
    }

    /**
     * @param Builder<Transaction> $query
     * @param int $categoryId
     * @return Builder<Transaction>
     */
    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function getFormattedAmountAttribute(): string
    {
        $sign = $this->type === 'credit' ? '+' : '-';
        return $sign . number_format(abs($this->amount), 2) . ' ' . $this->currency;
    }

    public function isIncome(): bool
    {
        return $this->type === 'credit';
    }

    public function isExpense(): bool
    {
        return $this->type === 'debit';
    }

    public function getAbsoluteAmountAttribute(): float
    {
        return abs($this->amount);
    }
} 