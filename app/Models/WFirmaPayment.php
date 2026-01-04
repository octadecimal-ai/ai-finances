<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model reprezentujący płatność (payment) z wFirma
 * 
 * Dokumentacja: https://doc.wfirma.pl/#moduly
 * Moduł: payments
 * 
 * @extends Model<WFirmaPayment>
 */
class WFirmaPayment extends Model
{
    use HasFactory;

    protected $table = 'wfirma_payments';

    protected $fillable = [
        'wfirma_id', // ID z wFirma API
        'user_id',
        'date', // Data płatności
        'amount', // Kwota płatności
        'currency', // Waluta
        'payment_method', // Metoda płatności: cash, transfer, compensation, cod, payment_card
        'payment_cashbox_id', // ID kasy z wFirma
        'description', // Opis płatności
        'invoice_id', // ID faktury powiązanej (jeśli dotyczy)
        'expense_id', // ID wydatku powiązanego (jeśli dotyczy)
        'income_id', // ID przychodu powiązanego (jeśli dotyczy)
        'contractor_id', // ID kontrahenta z wFirma
        'bank_account_id', // ID konta bankowego z wFirma
        'status', // Status płatności
        'metadata', // Dodatkowe dane w formacie JSON
        'synced_at', // Data synchronizacji z wFirma
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, WFirmaPayment>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param Builder<WFirmaPayment> $query
     * @return Builder<WFirmaPayment>
     */
    public function scopeByDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}

