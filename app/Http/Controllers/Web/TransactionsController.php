<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class TransactionsController extends Controller
{
    /**
     * Wyświetla listę transakcji (płatności) użytkownika
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        
        // Jeśli jest parametr clear, wyczyść filtry w sesji
        if ($request->has('clear')) {
            session()->forget('transactions_filters');
            return redirect()->route('transactions.index');
        }
        
        // Pobierz filtry z request lub z sesji
        $filters = [
            'type' => $request->get('type', session('transactions_filters.type', '')),
            'category_id' => $request->get('category_id', session('transactions_filters.category_id', '')),
            'date_from' => $request->get('date_from', session('transactions_filters.date_from', '')),
            'date_to' => $request->get('date_to', session('transactions_filters.date_to', '')),
            'search' => $request->get('search', session('transactions_filters.search', '')),
            'merchant_names' => $request->get('merchant_names', session('transactions_filters.merchant_names', [])),
        ];
        
        // Zapisz filtry w sesji (tylko jeśli są w request)
        if ($request->hasAny(['type', 'category_id', 'date_from', 'date_to', 'search', 'merchant_names'])) {
            session(['transactions_filters' => $filters]);
        }
        
        // Pobierz wybrane kolumny z sesji lub użyj domyślnych
        $availableColumns = $this->getAvailableColumns();
        $selectedColumns = $request->get('columns', session('transactions_columns', array_keys($availableColumns)));
        
        // Zapisz wybrane kolumny w sesji
        if ($request->has('columns')) {
            session(['transactions_columns' => $selectedColumns]);
        }
        
        $query = Transaction::where('user_id', $user->id);

        // Zastosuj filtry
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('transaction_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('transaction_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('merchant_name', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }
        
        // Filtrowanie po dostawcach
        if (!empty($filters['merchant_names']) && is_array($filters['merchant_names'])) {
            $query->whereIn('merchant_name', $filters['merchant_names']);
        }

        // Sortowanie
        $sortColumn = $request->get('sort', 'transaction_date');
        $sortDirection = $request->get('direction', 'desc');
        
        // Walidacja kolumny sortowania
        $allowedSortColumns = array_keys($this->getAvailableColumns());
        // Dodaj kolumny relacji
        $allowedSortColumns[] = 'category';
        $allowedSortColumns[] = 'bank_account';
        
        if (!in_array($sortColumn, $allowedSortColumns)) {
            $sortColumn = 'transaction_date';
        }
        
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }
        
        // Zapisz query dla statystyk (przed sortowaniem i paginacją)
        $filteredQuery = clone $query;
        
        // Pobierz dostępnych dostawców w danym okresie (przed zastosowaniem filtru merchant_names)
        $availableMerchantsQuery = Transaction::where('user_id', $user->id);
        if (!empty($filters['date_from'])) {
            $availableMerchantsQuery->whereDate('transaction_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $availableMerchantsQuery->whereDate('transaction_date', '<=', $filters['date_to']);
        }
        $availableMerchants = $availableMerchantsQuery->whereNotNull('merchant_name')
            ->distinct()
            ->orderBy('merchant_name')
            ->pluck('merchant_name')
            ->filter()
            ->values()
            ->toArray();
        
        // Obsługa sortowania po relacjach
        if ($sortColumn === 'category') {
            $query->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
                  ->orderBy('categories.name', $sortDirection)
                  ->select('transactions.*');
        } elseif ($sortColumn === 'bank_account') {
            $query->leftJoin('bank_accounts', 'transactions.bank_account_id', '=', 'bank_accounts.id')
                  ->orderBy('bank_accounts.name', $sortDirection)
                  ->select('transactions.*');
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        $transactions = $query->with(['category', 'bankAccount'])
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        // Statystyki dla przefiltrowanych danych
        // Pobierz wszystkie transakcje dla obliczeń
        $allTransactions = $filteredQuery->get();
        
        $income = $allTransactions->where('type', 'credit')->sum('amount');
        $expenses = $allTransactions->where('type', 'debit')->sum(function ($transaction) {
            // Użyj wartości bezwzględnej dla wydatków (mogą być ujemne w bazie)
            return abs($transaction->amount);
        });
        
        $stats = [
            'total' => $filteredQuery->count(),
            'income' => $income,
            'expenses' => $expenses,
            'balance' => $income - $expenses,
        ];

        // Pobierz kategorie dla operacji masowych
        $categories = Category::where('user_id', $user->id)
            ->orWhereNull('user_id')
            ->orderBy('name')
            ->get();

        return view('transactions.index', [
            'transactions' => $transactions,
            'stats' => $stats,
            'filters' => $filters,
            'availableColumns' => $availableColumns,
            'selectedColumns' => $selectedColumns,
            'categories' => $categories,
            'sortColumn' => $sortColumn,
            'sortDirection' => $sortDirection,
            'availableMerchants' => $availableMerchants,
        ]);
    }
    
    /**
     * Zwraca listę dostępnych kolumn dla transakcji
     */
    private function getAvailableColumns(): array
    {
        return [
            'id' => 'ID',
            'transaction_date' => 'Data transakcji',
            'booking_date' => 'Data księgowania',
            'value_date' => 'Data wartości',
            'type' => 'Typ',
            'amount' => 'Kwota',
            'currency' => 'Waluta',
            'description' => 'Opis',
            'merchant_name' => 'Odbiorca',
            'merchant_id' => 'ID odbiorcy',
            'reference' => 'Numer referencyjny',
            'status' => 'Status',
            'balance_after' => 'Saldo po',
            'category' => 'Kategoria',
            'bank_account' => 'Konto bankowe',
            'provider' => 'Dostawca',
            'external_id' => 'ID zewnętrzne',
            'is_imported' => 'Zaimportowana',
            'ai_analyzed' => 'Przeanalizowana przez AI',
            'created_at' => 'Data utworzenia',
            'updated_at' => 'Data aktualizacji',
        ];
    }
    
    /**
     * Operacje masowe na transakcjach
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $request->validate([
            'action' => 'required|in:delete,update_category,export',
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:transactions,id',
            'category_id' => 'required_if:action,update_category|nullable|exists:categories,id',
        ]);
        
        $ids = $request->input('ids');
        $query = Transaction::where('user_id', $user->id)->whereIn('id', $ids);
        
        switch ($request->action) {
            case 'delete':
                $count = $query->count();
                $query->delete();
                return redirect()->route('transactions.index')
                    ->with('success', "Usunięto {$count} transakcji.");
                    
            case 'update_category':
                $count = $query->count();
                $query->update(['category_id' => $request->category_id]);
                return redirect()->route('transactions.index')
                    ->with('success', "Zaktualizowano kategorię dla {$count} transakcji.");
                    
            case 'export':
                // TODO: Implementacja eksportu
                return redirect()->route('transactions.index')
                    ->with('info', 'Eksport w przygotowaniu.');
        }
        
        return redirect()->route('transactions.index');
    }

    /**
     * Wyświetla szczegóły transakcji
     */
    public function show(int $id): View
    {
        $user = Auth::user();
        
        $transaction = Transaction::where('user_id', $user->id)
            ->with(['category', 'bankAccount'])
            ->findOrFail($id);

        // Pobierz wszystkie dostępne pola z modelu
        $allFields = $this->getAllTransactionFields($transaction);

        return view('transactions.show', [
            'transaction' => $transaction,
            'allFields' => $allFields,
        ]);
    }
    
    /**
     * Zwraca wszystkie pola transakcji do wyświetlenia
     */
    private function getAllTransactionFields(Transaction $transaction): array
    {
        return [
            'Podstawowe informacje' => [
                'ID' => $transaction->id,
                'Data transakcji' => $transaction->transaction_date?->format('Y-m-d H:i:s'),
                'Data księgowania' => $transaction->booking_date?->format('Y-m-d H:i:s'),
                'Data wartości' => $transaction->value_date?->format('Y-m-d H:i:s'),
                'Typ' => $transaction->type === 'credit' ? 'Przychód' : 'Wydatek',
                'Kwota' => number_format(abs($transaction->amount), 2, ',', ' ') . ' ' . $transaction->currency,
                'Waluta' => $transaction->currency,
                'Status' => $transaction->status,
            ],
            'Opis transakcji' => [
                'Opis' => $transaction->description,
                'Odbiorca/Nadawca' => $transaction->merchant_name,
                'ID odbiorcy' => $transaction->merchant_id,
                'Numer referencyjny' => $transaction->reference,
            ],
            'Klasyfikacja' => [
                'Kategoria' => $transaction->category?->name,
                'Konto bankowe' => $transaction->bankAccount?->name,
            ],
            'Saldo' => [
                'Saldo po transakcji' => $transaction->balance_after ? number_format($transaction->balance_after, 2, ',', ' ') . ' ' . $transaction->currency : null,
            ],
            'Informacje techniczne' => [
                'Dostawca' => $transaction->provider,
                'ID zewnętrzne' => $transaction->external_id,
                'Zaimportowana' => $transaction->is_imported ? 'Tak' : 'Nie',
                'Przeanalizowana przez AI' => $transaction->ai_analyzed ? 'Tak' : 'Nie',
            ],
            'Metadata' => [
                'Metadata' => $transaction->metadata ? json_encode($transaction->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null,
            ],
            'Systemowe' => [
                'Data utworzenia' => $transaction->created_at?->format('Y-m-d H:i:s'),
                'Data aktualizacji' => $transaction->updated_at?->format('Y-m-d H:i:s'),
            ],
        ];
    }
}

