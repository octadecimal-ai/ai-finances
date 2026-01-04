<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model reprezentujący rozliczenie ZUS (interest) z wFirma
 * 
 * Dokumentacja: https://doc.wfirma.pl/#moduly
 * Moduł: interests
 * 
 * Uwaga: wFirma API nie posiada dedykowanego modułu dla ZUS.
 * Rozliczenia ZUS mogą być dostępne w module 'interests' lub
 * wymagać dodatkowych uprawnień lub integracji z e-ZUS/PUE.
 * 
 * @extends Model<WFirmaInterest>
 */
class WFirmaInterest extends Model
{
    use HasFactory;

    protected $table = 'wfirma_interests';

    protected $fillable = [
        'wfirma_id', // ID z wFirma API
        'user_id',
        'type', // Typ rozliczenia ZUS
        'period', // Okres rozliczeniowy (np. 2025-12)
        'date', // Data rozliczenia
        'due_date', // Termin płatności
        'amount', // Kwota rozliczenia
        'currency', // Waluta
        'status', // Status rozliczenia
        'description', // Opis rozliczenia
        'zus_type', // Typ ZUS: sp, zp, fp, fgsp, fgzp, fgfp (składki pracownicze/pracodawcy)
        'employee_id', // ID pracownika (jeśli dotyczy)
        'declaration_number', // Numer deklaracji
        'payment_date', // Data płatności
        'paid', // Czy zapłacono (0, 1)
        'metadata', // Dodatkowe dane w formacie JSON
        'synced_at', // Data synchronizacji z wFirma
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'paid' => 'boolean',
        'metadata' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, WFirmaInterest>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param Builder<WFirmaInterest> $query
     * @return Builder<WFirmaInterest>
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('paid', true);
    }

    /**
     * @param Builder<WFirmaInterest> $query
     * @return Builder<WFirmaInterest>
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('paid', false);
    }

    /**
     * @param Builder<WFirmaInterest> $query
     * @return Builder<WFirmaInterest>
     */
    public function scopeByPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    /**
     * @param Builder<WFirmaInterest> $query
     * @return Builder<WFirmaInterest>
     */
    public function scopeByZusType(Builder $query, string $zusType): Builder
    {
        return $query->where('zus_type', $zusType);
    }
}

