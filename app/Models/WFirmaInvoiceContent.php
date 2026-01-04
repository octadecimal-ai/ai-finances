<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model reprezentujący zawartość faktury (invoice_content) z wFirma
 * 
 * Dokumentacja: https://doc.wfirma.pl/#moduly
 * Moduł: invoices > invoicecontents
 * 
 * @extends Model<WFirmaInvoiceContent>
 */
class WFirmaInvoiceContent extends Model
{
    use HasFactory;

    protected $table = 'wfirma_invoice_contents';

    protected $fillable = [
        'wfirma_id', // ID z wFirma API
        'invoice_id', // ID faktury (relacja do WFirmaInvoice)
        'name', // Nazwa pozycji
        'classification', // Kod PKWiU
        'unit', // Jednostka słownie, np. "szt."
        'unit_id', // ID jednostki
        'count', // Ilość
        'unit_count', // Ilość jednostkowa
        'price', // Kwota produktu - w zależności od price_type będzie to cena netto lub brutto
        'price_modified', // Czy cena została zmodyfikowana (0, 1)
        'vat', // Stawka VAT
        'vat_code_id', // ID stawki VAT
        'discount', // Rabat
        'discount_percent', // Rabat w procentach
        'netto', // Wartość netto
        'brutto', // Wartość brutto
        'lumpcode', // Kod ryczałtu
        'good_id', // ID produktu z wFirma
        'tangiblefixedasset_id', // ID środka trwałego
        'equipment_id', // ID wyposażenia
        'vehicle_id', // ID pojazdu
        'metadata', // Dodatkowe dane w formacie JSON
    ];

    protected $casts = [
        'count' => 'decimal:4',
        'unit_count' => 'decimal:4',
        'price' => 'decimal:2',
        'price_modified' => 'boolean',
        'vat' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'netto' => 'decimal:2',
        'brutto' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<WFirmaInvoice, WFirmaInvoiceContent>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(WFirmaInvoice::class, 'invoice_id');
    }
}

