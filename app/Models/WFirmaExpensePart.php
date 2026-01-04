<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model reprezentujący część wydatku (expense_part) z wFirma
 * 
 * Dokumentacja: https://doc.wfirma.pl/#moduly
 * Moduł: expenses > expense_parts
 * 
 * @extends Model<WFirmaExpensePart>
 */
class WFirmaExpensePart extends Model
{
    use HasFactory;

    protected $table = 'wfirma_expense_parts';

    protected $fillable = [
        'wfirma_id', // ID z wFirma API
        'expense_id', // ID wydatku (relacja do WFirmaExpense)
        'expense_part_type', // rates, positions - pole specjalnie przygotowane pod API
        'schema', // Typ dokumentu: cost, purchase_trade_goods, vehicle_fuel, vehicle_expense
        'good_action', // new - wysyłamy, gdy chcemy utworzyć nowy produkt
        'good_id', // ID produktu
        'unit', // Jednostka słownie, np. "szt."
        'unit_id', // ID jednostki - możemy wysłać zamiast parametru "unit"
        'count', // Ilość - niewysłanie tego parametru wstawi produkt o ilości 1
        'price', // Kwota produktu - w zależności od tax_evaluation_method będzie to cena netto lub brutto
        'vat_code_id', // ID stawki VAT zawarte w gałęzi ID
        'name', // Nazwa pozycji
        'classification', // Kod PKWiU
        'netto', // Wartość netto
        'brutto', // Wartość brutto
        'vat', // Stawka VAT
        'discount', // Rabat
        'discount_percent', // Rabat w procentach
        'metadata', // Dodatkowe dane w formacie JSON
    ];

    protected $casts = [
        'count' => 'decimal:4',
        'price' => 'decimal:2',
        'netto' => 'decimal:2',
        'brutto' => 'decimal:2',
        'vat' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<WFirmaExpense, WFirmaExpensePart>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(WFirmaExpense::class, 'expense_id');
    }
}

