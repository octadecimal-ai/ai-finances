<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_account_id',
        'category_id',
        'external_id',
        'description',
        'amount',
        'currency',
        'transaction_date',
        'value_date',
        'type', // credit, debit
        'status', // pending, completed, failed
        'merchant_name',
        'merchant_id',
        'reference',
        'notes',
        'is_imported', // from CSV import
        'ai_analyzed', // if Claude analyzed this transaction
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'value_date' => 'date',
        'is_imported' => 'boolean',
        'ai_analyzed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('type', 'credit');
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('type', 'debit');
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

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