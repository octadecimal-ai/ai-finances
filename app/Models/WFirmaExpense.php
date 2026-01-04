<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model reprezentujący wydatek (expense) z wFirma
 * 
 * Dokumentacja: https://doc.wfirma.pl/#moduly
 * Moduł: expenses
 * 
 * @extends Model<WFirmaExpense>
 */
class WFirmaExpense extends Model
{
    use HasFactory;

    protected $table = 'wfirma_expenses';

    protected $fillable = [
        'wfirma_id', // ID z wFirma API
        'user_id',
        'type', // Typ dokumentu: invoice, bill, vat_exempt
        'date', // Data wystawienia wydatku - format RRRR-MM-DD
        'taxregister_date', // Data księgowania do kpir - format RRRR-MM-DD
        'payment_date', // Termin płatności - format RRRR-MM-DD
        'payment_method', // Metoda płatności: cash, transfer, compensation, cod, payment_card
        'paid', // 0, 1 - czy zapłacono całość
        'alreadypaid_initial', // Kwota do podania, jeśli "paid" wynosi 1, należy wprowadzić 0
        'currency', // Waluta np. PLN
        'accounting_effect', // Skutek księgowy: kpir_and_vat, kpir, vat, nothing
        'warehouse_type', // simple - informacje o ilości w prostym katalogu produktów, extended - informacje o ilości przy włączonym module magazynowym
        'schema_vat_cashbox', // 0, 1 - metoda kasowa
        'wnt', // 0, 1 - WNT
        'service_import', // 0, 1 - Import usług
        'service_import2', // 0, 1 - Import usług art.28b
        'cargo_import', // 0, 1 - Import towarów art. 33a
        'split_payment', // 0, 1 - Podzielona płatność
        'draft', // 0, 1 - draft wydatku
        'tax_evaluation_method', // netto, brutto - sposób przeliczania ceny z "price" dla produktów
        'contractor_id', // ID kontrahenta z wFirma
        'fullnumber', // Pełny numer dokumentu
        'number', // Numer dokumentu
        'description', // Opis
        'netto', // Wartość netto
        'brutto', // Wartość brutto
        'vat_content_netto', // Suma netto z VAT
        'vat_content_tax', // Suma podatku VAT
        'vat_content_brutto', // Suma brutto z VAT
        'total', // Suma całkowita
        'remaining', // Pozostało do zapłaty
        'metadata', // Dodatkowe dane w formacie JSON
        'synced_at', // Data synchronizacji z wFirma
    ];

    protected $casts = [
        'date' => 'date',
        'taxregister_date' => 'date',
        'payment_date' => 'date',
        'paid' => 'boolean',
        'alreadypaid_initial' => 'decimal:2',
        'schema_vat_cashbox' => 'boolean',
        'wnt' => 'boolean',
        'service_import' => 'boolean',
        'service_import2' => 'boolean',
        'cargo_import' => 'boolean',
        'split_payment' => 'boolean',
        'draft' => 'boolean',
        'netto' => 'decimal:2',
        'brutto' => 'decimal:2',
        'vat_content_netto' => 'decimal:2',
        'vat_content_tax' => 'decimal:2',
        'vat_content_brutto' => 'decimal:2',
        'total' => 'decimal:2',
        'remaining' => 'decimal:2',
        'metadata' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, WFirmaExpense>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WFirmaExpensePart, WFirmaExpense>
     */
    public function expenseParts(): HasMany
    {
        return $this->hasMany(WFirmaExpensePart::class, 'expense_id');
    }

    /**
     * @param Builder<WFirmaExpense> $query
     * @return Builder<WFirmaExpense>
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('paid', true);
    }

    /**
     * @param Builder<WFirmaExpense> $query
     * @return Builder<WFirmaExpense>
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('paid', false);
    }

    /**
     * @param Builder<WFirmaExpense> $query
     * @return Builder<WFirmaExpense>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('draft', true);
    }
}

