<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model reprezentujący fakturę sprzedażową (invoice) z wFirma
 * 
 * Dokumentacja: https://doc.wfirma.pl/#moduly
 * Moduł: invoices
 * 
 * Rodzaje dokumentów:
 * - normal: Faktura VAT
 * - margin: Faktura VAT marża
 * - proforma: Pro forma
 * - offer: Oferta
 * - receipt_normal: Dowód sprzedaży / Paragon niefiskalny
 * - receipt_fiscal_normal: Paragon fiskalny
 * - income_normal: Inny przychód - sprzedaż
 * - bill: Faktura (bez VAT)
 * - proforma_bill: Pro forma (bez VAT)
 * - offer_bill: Oferta (bez VAT)
 * - receipt_bill: Dowód sprzedaży / Paragon niefiskalny (bez VAT)
 * - receipt_fiscal_bill: Paragon fiskalny (bez VAT)
 * - income_bill: Inny przychód - sprzedaż (bez VAT)
 * 
 * @extends Model<WFirmaInvoice>
 */
class WFirmaInvoice extends Model
{
    use HasFactory;

    protected $table = 'wfirma_invoices';

    protected $fillable = [
        'wfirma_id', // ID z wFirma API
        'user_id',
        'type', // Typ dokumentu: normal, proforma, offer, receipt_normal, receipt_fiscal_normal, income_normal, bill, proforma_bill, offer_bill, receipt_bill, receipt_fiscal_bill, income_bill
        'date', // Data wystawienia faktury - format RRRR-MM-DD
        'disposaldate', // Data sprzedaży - format RRRR-MM-DD
        'disposaldate_empty', // 0, 1 - czy data sprzedaży jest pusta (sprzedaż wysyłkowa)
        'disposaldate_format', // Format daty sprzedaży: month, day
        'paymentdate', // Termin płatności - format RRRR-MM-DD
        'paymentmethod', // Metoda płatności: cash, transfer, compensation, cod, payment_card
        'paymentstate', // Stan płatności: paid, unpaid, undefined
        'alreadypaid_initial', // Kwota zapłacono określona przy dodawaniu faktury
        'alreadypaid', // Kwota zapłacono uwzględniająca wszystkie płatności (tylko odczyt)
        'currency', // Waluta np. PLN
        'currency_exchange', // Kurs księgowy faktury (tylko odczyt)
        'currency_label', // Numer tabeli NBP kursu księgowego (tylko odczyt)
        'currency_date', // Data opublikowania kursu (tylko odczyt)
        'price_currency_exchange', // Kurs stosowany przy przeliczaniu cen w panelu wfirmy (tylko odczyt)
        'good_price_group_currency_exchange', // Kurs grupy cenowej (tylko odczyt)
        'number', // Numer wstawiany w miejsce znacznika [numer] we wzorcu serii numeracji
        'day', // Dzień wstawiany w miejsce znacznika [dzień] we wzorcu serii numeracji
        'month', // Miesiąc wstawiany w miejsce znacznika [miesiąc] we wzorcu serii numeracji
        'year', // Rok wstawiany w miejsce znacznika [rok] we wzorcu serii numeracji
        'fullnumber', // Numer wygenerowany na podstawie wzorca serii numeracji
        'semitemplatenumber', // Częściowo wygenerowany numer (tylko odczyt)
        'correction_type', // Pole wykorzystywane wewnętrznie przy fakturach korygujących (tylko odczyt)
        'corrections', // Liczba korekt (tylko odczyt)
        'schema', // Schemat księgowy: normal, vat_invoice_date, vat_buyer_construction_service, assessor, split_payment
        'schema_bill', // Opcja faktura do paragonu
        'schema_cancelled', // Opcja faktura anulowana
        'schema_receipt_book', // Czy paragon ma być księgowany (dla paragonów)
        'register_description', // Domyślny opis księgowania do ewidencji
        'template', // Identyfikator szablonu wydruku dokumentu sprzedaży
        'auto_send', // Automatyczna wysyłka faktury na adres e-mail kontrahenta (0, 1)
        'description', // Uwagi
        'header', // Dodatkowe informacje w nagłówku faktury (tylko odczyt)
        'footer', // Dodatkowe informacje w stopce faktury (tylko odczyt)
        'user_name', // Imię i nazwisko osoby upoważnionej do wystawienia faktury (tylko odczyt)
        'netto', // Wartość netto ogółem (tylko odczyt)
        'tax', // Wartość podatku ogółem (tylko odczyt)
        'total', // Kwota razem dokumentu sprzedaży bez uwzględnienia ewentualnych korekt (tylko odczyt)
        'total_composed', // Kwota razem faktury z uwzględnieniem korekt (tylko odczyt)
        'signed', // Oznaczenie faktur podpisanych elektronicznie (tylko odczyt)
        'hash', // Hash zabezpieczający odsyłacz do faktury w panelu klienta (tylko odczyt)
        'id_external', // Pole do zapisywania własnych wartości
        'warehouse_type', // Pole określa czy był włączony moduł magazynowy (extended) czy katalog produktów (simple) (tylko odczyt)
        'notes', // Liczba notatek powiązanych z dokumentem sprzedaży (tylko odczyt)
        'documents', // Liczba dokumentów powiązanych z dokumentem sprzedaży (tylko odczyt)
        'tags', // Znaczniki powiązane z fakturą w formacie (ID ZNACZNIKA X),(ID ZNACZNIKA Y)...
        'price_type', // Rodzaj ceny - netto lub brutto
        'series_id', // Id serii numeracji
        'contractor_id', // ID kontrahenta z wFirma
        'receipt_fiscal_printed', // Czy paragon został wydrukowany (tylko dla paragonów fiskalnych, tylko odczyt)
        'income_lumpcode', // Stawka ryczałtu w przypadku prowadzenia Ewidencji przychodów (dla Inny przychód)
        'income_correction', // Stawka ryczałtu w przypadku prowadzenia Ewidencji przychodów (dla Inny przychód)
        'period', // Okres w którym dokument jest widoczny na liście (tylko odczyt)
        'metadata', // Dodatkowe dane w formacie JSON
        'synced_at', // Data synchronizacji z wFirma
    ];

    protected $casts = [
        'date' => 'date',
        'disposaldate' => 'date',
        'disposaldate_empty' => 'boolean',
        'paymentdate' => 'date',
        'currency_date' => 'date',
        'alreadypaid_initial' => 'decimal:2',
        'alreadypaid' => 'decimal:2',
        'currency_exchange' => 'decimal:4',
        'price_currency_exchange' => 'decimal:4',
        'good_price_group_currency_exchange' => 'decimal:4',
        'auto_send' => 'boolean',
        'schema_bill' => 'boolean',
        'schema_cancelled' => 'boolean',
        'schema_receipt_book' => 'boolean',
        'receipt_fiscal_printed' => 'boolean',
        'signed' => 'boolean',
        'netto' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'total_composed' => 'decimal:2',
        'metadata' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, WFirmaInvoice>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WFirmaInvoiceContent, WFirmaInvoice>
     */
    public function invoiceContents(): HasMany
    {
        return $this->hasMany(WFirmaInvoiceContent::class, 'invoice_id');
    }

    /**
     * @param Builder<WFirmaInvoice> $query
     * @return Builder<WFirmaInvoice>
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('paymentstate', 'paid');
    }

    /**
     * @param Builder<WFirmaInvoice> $query
     * @return Builder<WFirmaInvoice>
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('paymentstate', 'unpaid');
    }

    /**
     * @param Builder<WFirmaInvoice> $query
     * @return Builder<WFirmaInvoice>
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * @param Builder<WFirmaInvoice> $query
     * @return Builder<WFirmaInvoice>
     */
    public function scopeByDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}

