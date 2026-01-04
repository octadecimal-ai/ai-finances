<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class InvoicesController extends Controller
{
    /**
     * Wyświetla listę faktur użytkownika
     */
    public function index(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();
        
        // Jeśli jest parametr clear, wyczyść filtry w sesji
        if ($request->has('clear')) {
            session()->forget('invoices_filters');
            return redirect()->route('invoices.index');
        }
        
        // Pobierz filtry z request lub z sesji
        $filters = [
            'status' => $request->get('status', session('invoices_filters.status', '')),
            'date_from' => $request->get('date_from', session('invoices_filters.date_from', '')),
            'date_to' => $request->get('date_to', session('invoices_filters.date_to', '')),
            'search' => $request->get('search', session('invoices_filters.search', '')),
            'seller_names' => $request->get('seller_names', session('invoices_filters.seller_names', [])),
        ];
        
        // Zapisz filtry w sesji (tylko jeśli są w request)
        if ($request->hasAny(['status', 'date_from', 'date_to', 'search', 'seller_names'])) {
            session(['invoices_filters' => $filters]);
        }
        
        // Pobierz wybrane kolumny z sesji lub użyj domyślnych
        $availableColumns = $this->getAvailableColumns();
        $selectedColumns = $request->get('columns', session('invoices_columns', array_keys($availableColumns)));
        
        // Zapisz wybrane kolumny w sesji
        if ($request->has('columns')) {
            session(['invoices_columns' => $selectedColumns]);
        }
        
        $query = Invoice::where('user_id', $user->id);

        // Zastosuj filtry
        if (!empty($filters['status'])) {
            $query->where('payment_status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('issue_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('issue_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('seller_name', 'like', "%{$search}%")
                  ->orWhere('buyer_name', 'like', "%{$search}%");
            });
        }
        
        // Filtrowanie po sprzedawcach
        if (!empty($filters['seller_names']) && is_array($filters['seller_names'])) {
            $query->whereIn('seller_name', $filters['seller_names']);
        }

        // Sortowanie
        $sortColumn = $request->get('sort', 'issue_date');
        $sortDirection = $request->get('direction', 'desc');
        
        // Walidacja kolumny sortowania
        $allowedSortColumns = array_keys($this->getAvailableColumns());
        if (!in_array($sortColumn, $allowedSortColumns)) {
            $sortColumn = 'issue_date';
        }
        
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }
        
        // Zapisz query dla statystyk (przed paginacją)
        $filteredQuery = clone $query;
        
        // Pobierz dostępnych sprzedawców w danym okresie (przed zastosowaniem filtru seller_names)
        $availableSellersQuery = Invoice::where('user_id', $user->id);
        if (!empty($filters['date_from'])) {
            $availableSellersQuery->whereDate('issue_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $availableSellersQuery->whereDate('issue_date', '<=', $filters['date_to']);
        }
        $availableSellers = $availableSellersQuery->whereNotNull('seller_name')
            ->distinct()
            ->orderBy('seller_name')
            ->pluck('seller_name')
            ->filter()
            ->values()
            ->toArray();

        $invoices = $query->orderBy($sortColumn, $sortDirection)
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        // Dodaj konwersję walut na PLN dla każdej faktury
        // Używamy invoice_date (Data faktury) zgodnie z wymaganiami użytkownika
        $invoices->getCollection()->transform(function ($invoice) {
            $invoiceDate = $invoice->invoice_date ?? $invoice->issue_date;
            $invoice->netto_pln = $this->convertToPLN($invoice->subtotal, $invoice->currency, $invoiceDate);
            $invoice->vat_pln = $this->convertToPLN($invoice->tax_amount, $invoice->currency, $invoiceDate);
            $invoice->brutto_pln = $this->convertToPLN($invoice->total_amount, $invoice->currency, $invoiceDate);
            return $invoice;
        });

        // Statystyki dla przefiltrowanych danych
        $stats = [
            'total' => $filteredQuery->count(),
            'paid' => (clone $filteredQuery)->where('payment_status', 'paid')->count(),
            'pending' => (clone $filteredQuery)->where('payment_status', 'pending')->count(),
            'overdue' => (clone $filteredQuery)->where(function ($q) {
                $q->where('payment_status', 'overdue')
                  ->orWhere(function ($q2) {
                      $q2->where('payment_status', 'pending')
                         ->where('due_date', '<', now());
                  });
            })->count(),
            'total_amount' => $filteredQuery->sum('total_amount'),
        ];

        return view('invoices.index', [
            'invoices' => $invoices,
            'stats' => $stats,
            'filters' => $filters,
            'availableColumns' => $availableColumns,
            'selectedColumns' => $selectedColumns,
            'sortColumn' => $sortColumn,
            'sortDirection' => $sortDirection,
            'availableSellers' => $availableSellers,
        ]);
    }
    
    /**
     * Zwraca listę dostępnych kolumn dla faktur
     */
    private function getAvailableColumns(): array
    {
        return [
            'id' => 'ID',
            'invoice_number' => 'Numer faktury',
            'invoice_date' => 'Data faktury',
            'issue_date' => 'Data wystawienia',
            'due_date' => 'Termin płatności',
            'seller_name' => 'Sprzedawca',
            'seller_tax_id' => 'NIP sprzedawcy',
            'buyer_name' => 'Nabywca',
            'buyer_tax_id' => 'NIP nabywcy',
            'subtotal' => 'Netto',
            'tax_amount' => 'VAT',
            'total_amount' => 'Brutto',
            'netto_pln' => 'Netto (PLN)',
            'vat_pln' => 'VAT (PLN)',
            'brutto_pln' => 'Brutto (PLN)',
            'currency' => 'Waluta',
            'payment_method' => 'Metoda płatności',
            'payment_status' => 'Status płatności',
            'paid_at' => 'Data płatności',
            'transaction_id' => 'ID transakcji',
            'match_score' => 'Poziom dopasowania',
            'matched_at' => 'Data dopasowania',
            'file_name' => 'Plik',
            'source_type' => 'Źródło',
            'created_at' => 'Data utworzenia',
            'updated_at' => 'Data aktualizacji',
        ];
    }
    
    /**
     * Operacje masowe na fakturach
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $request->validate([
            'action' => 'required|in:delete,update_status',
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:invoices,id',
            'payment_status' => 'required_if:action,update_status|in:pending,paid,overdue,cancelled',
        ]);
        
        $ids = $request->input('ids');
        $query = Invoice::where('user_id', $user->id)->whereIn('id', $ids);
        
        switch ($request->action) {
            case 'delete':
                $count = $query->count();
                $query->delete();
                return redirect()->route('invoices.index')
                    ->with('success', "Usunięto {$count} faktur.");
                    
            case 'update_status':
                $count = $query->count();
                $query->update([
                    'payment_status' => $request->payment_status,
                    'paid_at' => $request->payment_status === 'paid' ? now() : null,
                ]);
                return redirect()->route('invoices.index')
                    ->with('success', "Zaktualizowano status {$count} faktur.");
        }
        
        return redirect()->route('invoices.index');
    }

    /**
     * Wyświetla szczegóły faktury
     */
    public function show(int $id): View
    {
        $user = Auth::user();
        
        $invoice = Invoice::where('user_id', $user->id)
            ->with('items')
            ->findOrFail($id);

        // Pobierz wszystkie dostępne pola z modelu
        $allFields = $this->getAllInvoiceFields($invoice);

        return view('invoices.show', [
            'invoice' => $invoice,
            'allFields' => $allFields,
        ]);
    }
    
    /**
     * Zwraca wszystkie pola faktury do wyświetlenia
     */
    private function getAllInvoiceFields(Invoice $invoice): array
    {
        return [
            'Podstawowe informacje' => [
                'ID' => $invoice->id,
                'Numer faktury' => $invoice->invoice_number,
                'Data faktury' => $invoice->invoice_date?->format('Y-m-d H:i:s'),
                'Data wystawienia' => $invoice->issue_date?->format('Y-m-d H:i:s'),
                'Termin płatności' => $invoice->due_date?->format('Y-m-d H:i:s'),
                'Status płatności' => $invoice->payment_status,
                'Data płatności' => $invoice->paid_at?->format('Y-m-d H:i:s'),
            ],
            'Sprzedawca' => [
                'Nazwa' => $invoice->seller_name,
                'NIP' => $invoice->seller_tax_id,
                'Adres' => $invoice->seller_address,
                'Email' => $invoice->seller_email,
                'Telefon' => $invoice->seller_phone,
                'Numer konta' => $invoice->seller_account_number,
            ],
            'Nabywca' => [
                'Nazwa' => $invoice->buyer_name,
                'NIP' => $invoice->buyer_tax_id,
                'Adres' => $invoice->buyer_address,
                'Email' => $invoice->buyer_email,
                'Telefon' => $invoice->buyer_phone,
            ],
            'Kwoty' => [
                'Netto' => $invoice->subtotal ? number_format($invoice->subtotal, 2, ',', ' ') . ' ' . $invoice->currency : null,
                'VAT' => $invoice->tax_amount ? number_format($invoice->tax_amount, 2, ',', ' ') . ' ' . $invoice->currency : null,
                'Brutto' => $invoice->total_amount ? number_format($invoice->total_amount, 2, ',', ' ') . ' ' . $invoice->currency : null,
                'Waluta' => $invoice->currency,
            ],
            'Płatność' => [
                'Metoda płatności' => $invoice->payment_method,
                'Status płatności' => $invoice->payment_status,
            ],
            'Plik źródłowy' => [
                'Ścieżka pliku' => $invoice->file_path,
                'Nazwa pliku' => $invoice->file_name,
                'Typ źródła' => $invoice->source_type,
            ],
            'Dodatkowe' => [
                'Uwagi' => $invoice->notes,
                'Data parsowania' => $invoice->parsed_at?->format('Y-m-d H:i:s'),
                'Metadata' => $invoice->metadata ? json_encode($invoice->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null,
            ],
        ];
    }
    
    /**
     * Konwertuje kwotę z danej waluty na PLN używając kursu z daty faktury
     * 
     * @param float|null $amount
     * @param string|null $currency
     * @param \DateTime|null $date
     * @return float|null
     */
    private function convertToPLN(?float $amount, ?string $currency, ?\DateTime $date): ?float
    {
        // Jeśli kwota jest null lub 0, zwróć null
        if ($amount === null || $amount == 0) {
            return null;
        }
        
        // Jeśli waluta to PLN, zwróć kwotę bez konwersji
        if (strtoupper($currency ?? '') === 'PLN') {
            return $amount;
        }
        
        // Jeśli brak waluty lub daty, zwróć null
        if (empty($currency) || !$date) {
            return null;
        }
        
        // Pobierz kurs wymiany dla daty faktury
        $exchangeRate = ExchangeRate::getRateForDate(
            strtoupper($currency),
            $date->format('Y-m-d')
        );
        
        // Jeśli nie znaleziono kursu dla dokładnej daty, spróbuj znaleźć najbliższy wcześniejszy
        if (!$exchangeRate) {
            $exchangeRate = ExchangeRate::getLatestRate(
                strtoupper($currency),
                $date->format('Y-m-d')
            );
        }
        
        // Jeśli nadal nie ma kursu, zwróć null
        if (!$exchangeRate || !$exchangeRate->rate) {
            return null;
        }
        
        // Konwertuj kwotę na PLN
        return round($amount * $exchangeRate->rate, 2);
    }
}

