<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model reprezentujący termin (term) z wFirma - Terminarz
 * 
 * Dokumentacja: https://doc.wfirma.pl/#moduly
 * Moduł: terms
 * 
 * @extends Model<WFirmaTerm>
 */
class WFirmaTerm extends Model
{
    use HasFactory;

    protected $table = 'wfirma_terms';

    protected $fillable = [
        'wfirma_id', // ID z wFirma API
        'user_id',
        'term_group_id', // ID grupy terminów z wFirma
        'date', // Data terminu
        'time', // Godzina terminu (opcjonalna)
        'description', // Opis terminu
        'title', // Tytuł terminu
        'status', // Status terminu
        'reminder', // Czy przypomnienie włączone (0, 1)
        'reminder_minutes', // Liczba minut przed terminem na przypomnienie
        'contractor_id', // ID kontrahenta powiązanego (jeśli dotyczy)
        'invoice_id', // ID faktury powiązanej (jeśli dotyczy)
        'expense_id', // ID wydatku powiązanego (jeśli dotyczy)
        'income_id', // ID przychodu powiązanego (jeśli dotyczy)
        'metadata', // Dodatkowe dane w formacie JSON
        'synced_at', // Data synchronizacji z wFirma
    ];

    protected $casts = [
        'date' => 'date',
        'reminder' => 'boolean',
        'metadata' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, WFirmaTerm>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param Builder<WFirmaTerm> $query
     * @return Builder<WFirmaTerm>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('date', '>=', now()->toDateString())
            ->orderBy('date', 'asc')
            ->orderBy('time', 'asc');
    }

    /**
     * @param Builder<WFirmaTerm> $query
     * @return Builder<WFirmaTerm>
     */
    public function scopePast(Builder $query): Builder
    {
        return $query->where('date', '<', now()->toDateString())
            ->orderBy('date', 'desc');
    }

    /**
     * @param Builder<WFirmaTerm> $query
     * @return Builder<WFirmaTerm>
     */
    public function scopeByDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}

