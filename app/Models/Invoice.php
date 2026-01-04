<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Model<Invoice>
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'match_score',
        'matched_at',
        'invoice_number',
        'invoice_date',
        'issue_date',
        'due_date',
        'seller_name',
        'seller_tax_id',
        'seller_address',
        'seller_email',
        'seller_phone',
        'seller_account_number',
        'buyer_name',
        'buyer_tax_id',
        'buyer_address',
        'buyer_email',
        'buyer_phone',
        'subtotal',
        'tax_amount',
        'total_amount',
        'currency',
        'payment_method',
        'payment_status',
        'paid_at',
        'file_path',
        'file_name',
        'source_type',
        'notes',
        'metadata',
        'parsed_at',
    ];

    protected $casts = [
        'invoice_date' => 'datetime',
        'issue_date' => 'datetime',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'parsed_at' => 'datetime',
        'matched_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'match_score' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<User, Invoice>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<InvoiceItem, Invoice>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<Transaction, Invoice>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @param Builder<Invoice> $query
     * @return Builder<Invoice>
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * @param Builder<Invoice> $query
     * @return Builder<Invoice>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * @param Builder<Invoice> $query
     * @return Builder<Invoice>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('payment_status', 'overdue')
            ->orWhere(function ($q) {
                $q->where('payment_status', 'pending')
                  ->where('due_date', '<', now());
            });
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->payment_status === 'overdue' 
            || ($this->payment_status === 'pending' && $this->due_date && $this->due_date->isPast());
    }
}
