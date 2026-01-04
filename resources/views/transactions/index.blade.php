@extends('components.layout')

@section('title', 'Historia płatności')

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    const bulkActionsForm = document.getElementById('bulk-actions-form');
    const bulkActionSelect = document.getElementById('bulk-action-select');
    const bulkActionButton = document.getElementById('bulk-action-button');
    const selectedCount = document.getElementById('selected-count');
    const categorySelect = document.getElementById('category-select');
    
    // Select all checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            itemCheckboxes.forEach(cb => cb.checked = this.checked);
            updateBulkActions();
        });
    }
    
    // Individual checkboxes
    itemCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            updateSelectAll();
            updateBulkActions();
        });
    });
    
    function updateSelectAll() {
        if (selectAllCheckbox) {
            const allChecked = Array.from(itemCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(itemCheckboxes).some(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
        }
    }
    
    function updateBulkActions() {
        const checked = Array.from(itemCheckboxes).filter(cb => cb.checked);
        const count = checked.length;
        
        if (selectedCount) {
            selectedCount.textContent = count;
        }
        
        if (bulkActionsForm) {
            bulkActionsForm.style.display = count > 0 ? 'block' : 'none';
        }
        
        if (bulkActionButton) {
            bulkActionButton.disabled = count === 0 || !bulkActionSelect.value;
        }
        
        // Pokaż/ukryj wybór kategorii
        if (categorySelect) {
            categorySelect.style.display = (bulkActionSelect.value === 'update_category') ? 'block' : 'none';
            if (bulkActionSelect.value === 'update_category' && !categorySelect.value) {
                bulkActionButton.disabled = true;
            }
        }
    }
    
    if (bulkActionSelect) {
        bulkActionSelect.addEventListener('change', function() {
            if (bulkActionButton) {
                bulkActionButton.disabled = !this.value || (this.value === 'update_category' && !categorySelect.value);
            }
            if (categorySelect) {
                categorySelect.style.display = (this.value === 'update_category') ? 'block' : 'none';
            }
        });
    }
    
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            if (bulkActionButton && bulkActionSelect.value === 'update_category') {
                bulkActionButton.disabled = !this.value;
            }
        });
    }
    
    if (bulkActionsForm) {
        bulkActionsForm.addEventListener('submit', function(e) {
            const checked = Array.from(itemCheckboxes).filter(cb => cb.checked);
            if (checked.length === 0) {
                e.preventDefault();
                return false;
            }
            
            const ids = checked.map(cb => cb.value);
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = bulkActionSelect.value;
            this.appendChild(actionInput);
            
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                this.appendChild(input);
            });
            
            if (bulkActionSelect.value === 'update_category') {
                if (!categorySelect.value) {
                    e.preventDefault();
                    alert('Wybierz kategorię');
                    return false;
                }
                const categoryInput = document.createElement('input');
                categoryInput.type = 'hidden';
                categoryInput.name = 'category_id';
                categoryInput.value = categorySelect.value;
                this.appendChild(categoryInput);
            }
            
            if (!confirm(`Czy na pewno chcesz wykonać akcję "${bulkActionSelect.options[bulkActionSelect.selectedIndex].text}" na ${ids.length} transakcjach?`)) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // Column selection modal
    const columnModal = document.getElementById('column-modal');
    const columnToggle = document.getElementById('column-toggle');
    const columnClose = document.getElementById('column-close');
    const columnForm = document.getElementById('column-form');
    
    if (columnToggle) {
        columnToggle.addEventListener('click', function() {
            if (columnModal) columnModal.classList.remove('hidden');
        });
    }
    
    if (columnClose) {
        columnClose.addEventListener('click', function() {
            if (columnModal) columnModal.classList.add('hidden');
        });
    }
    
    if (columnModal) {
        columnModal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    }
});
</script>
@endpush

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold">Historia płatności</h1>
        <div class="flex gap-2">
            <button id="column-toggle" class="px-4 py-2 border border-[#19140035] dark:border-[#3E3E3A] hover:border-[#1915014a] dark:hover:border-[#62605b] rounded-sm transition-colors text-[#1b1b18] dark:text-[#EDEDEC]">
                Wybór kolumn
            </button>
        </div>
    </div>

    <!-- Statystyki -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-[#161615] p-4 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm">
            <div class="text-sm text-[#706f6c] dark:text-[#A1A09A]">Wszystkie transakcje</div>
            <div class="text-2xl font-semibold mt-1">{{ $stats['total'] }}</div>
        </div>
        <div class="bg-white dark:bg-[#161615] p-4 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm">
            <div class="text-sm text-[#706f6c] dark:text-[#A1A09A]">Przychody</div>
            <div class="text-2xl font-semibold mt-1 text-green-600 dark:text-green-400">
                {{ number_format($stats['income'], 2, ',', ' ') }} PLN
            </div>
        </div>
        <div class="bg-white dark:bg-[#161615] p-4 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm">
            <div class="text-sm text-[#706f6c] dark:text-[#A1A09A]">Wydatki</div>
            <div class="text-2xl font-semibold mt-1 text-red-600 dark:text-red-400">
                -{{ number_format($stats['expenses'], 2, ',', ' ') }} PLN
            </div>
        </div>
        <div class="bg-white dark:bg-[#161615] p-4 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm">
            <div class="text-sm text-[#706f6c] dark:text-[#A1A09A]">Bilans</div>
            <div class="text-2xl font-semibold mt-1 {{ $stats['balance'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                {{ $stats['balance'] >= 0 ? '+' : '' }}{{ number_format($stats['balance'], 2, ',', ' ') }} PLN
            </div>
        </div>
    </div>

    <!-- Filtry -->
    <div class="bg-white dark:bg-[#161615] p-4 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm">
        <form method="GET" action="{{ route('transactions.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Typ</label>
                <select name="type" class="w-full px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm bg-white dark:bg-[#161615]">
                    <option value="">Wszystkie</option>
                    <option value="credit" {{ ($filters['type'] ?? '') === 'credit' ? 'selected' : '' }}>Przychody</option>
                    <option value="debit" {{ ($filters['type'] ?? '') === 'debit' ? 'selected' : '' }}>Wydatki</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Data od</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" 
                       class="w-full px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm bg-white dark:bg-[#161615]">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Data do</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" 
                       class="w-full px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm bg-white dark:bg-[#161615]">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Szukaj</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Opis, odbiorca..." 
                       class="w-full px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm bg-white dark:bg-[#161615]">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] rounded-sm hover:bg-black dark:hover:bg-white transition-colors">
                    Filtruj
                </button>
                @if(!empty(array_filter($filters)))
                    <a href="{{ route('transactions.index', ['clear' => 1]) }}" class="px-4 py-2 border border-[#19140035] dark:border-[#3E3E3A] hover:border-[#1915014a] dark:hover:border-[#62605b] rounded-sm transition-colors text-[#1b1b18] dark:text-[#EDEDEC]">
                        Wyczyść
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Filtrowanie po dostawcach -->
    @if(count($availableMerchants) > 0)
        <div class="bg-white dark:bg-[#161615] p-4 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm">
            <form method="GET" action="{{ route('transactions.index') }}" id="merchants-filter-form">
                @foreach($filters as $key => $value)
                    @if($key !== 'merchant_names' && $value)
                        @if(is_array($value))
                            @foreach($value as $v)
                                <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endif
                @endforeach
                <label class="block text-sm font-medium mb-3">Dostawcy</label>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @foreach($availableMerchants as $merchant)
                        <label class="flex items-center justify-between cursor-pointer py-2 px-3 hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a] rounded">
                            <span class="text-sm text-[#1b1b18] dark:text-[#EDEDEC]">{{ $merchant }}</span>
                            <div class="relative inline-block w-11 h-6">
                                <input type="checkbox" name="merchant_names[]" value="{{ $merchant }}" 
                                       {{ in_array($merchant, $filters['merchant_names'] ?? []) ? 'checked' : '' }}
                                       class="sr-only peer"
                                       onchange="document.getElementById('merchants-filter-form').submit()">
                                <div class="w-11 h-6 bg-gray-300 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#1b1b18] dark:peer-focus:ring-[#EDEDEC] rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#1b1b18] dark:peer-checked:bg-[#EDEDEC]"></div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </form>
        </div>
    @endif

    <!-- Operacje masowe -->
    <div id="bulk-actions-form" class="bg-white dark:bg-[#161615] p-4 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm hidden">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                Wybrano: <span id="selected-count" class="font-semibold">0</span> transakcji
            </div>
            <form method="POST" action="{{ route('transactions.bulkAction') }}" class="flex gap-2 flex-wrap">
                @csrf
                <select id="bulk-action-select" name="action" class="px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm bg-white dark:bg-[#161615]">
                    <option value="">Wybierz akcję...</option>
                    <option value="delete">Usuń</option>
                    <option value="update_category">Zmień kategorię</option>
                    <option value="export">Eksportuj</option>
                </select>
                <select id="category-select" name="category_id" class="px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm bg-white dark:bg-[#161615] hidden">
                    <option value="">Wybierz kategorię...</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <button id="bulk-action-button" type="submit" disabled class="px-4 py-2 bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] rounded-sm hover:bg-black dark:hover:bg-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    Wykonaj
                </button>
            </form>
        </div>
    </div>

    <!-- Lista transakcji -->
    <div class="bg-white dark:bg-[#161615] rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-[#FDFDFC] dark:bg-[#0a0a0a] border-b border-[#e3e3e0] dark:border-[#3E3E3A]">
                    <tr>
                        <th class="px-6 py-3 text-left">
                            <input type="checkbox" id="select-all" class="rounded border-[#e3e3e0] dark:border-[#3E3E3A]">
                        </th>
                        @foreach($selectedColumns as $column)
                            @if(isset($availableColumns[$column]))
                                <th class="px-6 py-3 text-left text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider cursor-pointer hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a] select-none">
                                    <a href="{{ route('transactions.index', array_merge(request()->query(), ['sort' => $column, 'direction' => ($sortColumn === $column && $sortDirection === 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center justify-between">
                                        <span>{{ $availableColumns[$column] }}</span>
                                        <span class="ml-2">
                                            @if($sortColumn === $column)
                                                @if($sortDirection === 'asc')
                                                    ↑
                                                @else
                                                    ↓
                                                @endif
                                            @else
                                                <span class="text-[#e3e3e0] dark:text-[#3E3E3A]">⇅</span>
                                            @endif
                                        </span>
                                    </a>
                                </th>
                            @endif
                        @endforeach
                        <th class="px-6 py-3 text-left text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider">Akcje</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e3e3e0] dark:divide-[#3E3E3A]">
                    @forelse($transactions as $transaction)
                        <tr class="hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a] transition-colors">
                            <td class="px-6 py-4">
                                <input type="checkbox" class="item-checkbox rounded border-[#e3e3e0] dark:border-[#3E3E3A]" value="{{ $transaction->id }}">
                            </td>
                            @foreach($selectedColumns as $column)
                                @if(isset($availableColumns[$column]))
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($column === 'id')
                                            <div class="font-medium">{{ $transaction->id }}</div>
                                        @elseif($column === 'transaction_date')
                                            <div class="text-sm">{{ $transaction->transaction_date->format('Y-m-d H:i:s') }}</div>
                                        @elseif($column === 'booking_date')
                                            <div class="text-sm">{{ $transaction->booking_date ? $transaction->booking_date->format('Y-m-d H:i:s') : '-' }}</div>
                                        @elseif($column === 'value_date')
                                            <div class="text-sm">{{ $transaction->value_date ? $transaction->value_date->format('Y-m-d H:i:s') : '-' }}</div>
                                        @elseif($column === 'type')
                                            <span class="px-2 py-1 text-xs font-medium rounded-full {{ $transaction->type === 'credit' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                                {{ $transaction->type === 'credit' ? 'Przychód' : 'Wydatek' }}
                                            </span>
                                        @elseif($column === 'amount')
                                            <div class="font-medium {{ $transaction->type === 'credit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                {{ $transaction->type === 'credit' ? '+' : '-' }}{{ number_format(abs($transaction->amount), 2, ',', ' ') }} {{ $transaction->currency }}
                                            </div>
                                        @elseif($column === 'currency')
                                            <div class="text-sm">{{ $transaction->currency }}</div>
                                        @elseif($column === 'description')
                                            <div class="font-medium">{{ $transaction->description }}</div>
                                        @elseif($column === 'merchant_name')
                                            <div class="text-sm">{{ $transaction->merchant_name ?? '-' }}</div>
                                        @elseif($column === 'merchant_id')
                                            <div class="text-sm font-mono">{{ $transaction->merchant_id ?? '-' }}</div>
                                        @elseif($column === 'reference')
                                            <div class="text-sm font-mono">{{ $transaction->reference ?? '-' }}</div>
                                        @elseif($column === 'status')
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                {{ $transaction->status ?? 'Zrealizowana' }}
                                            </span>
                                        @elseif($column === 'balance_after')
                                            <div class="font-medium">{{ $transaction->balance_after ? number_format($transaction->balance_after, 2, ',', ' ') . ' ' . $transaction->currency : '-' }}</div>
                                        @elseif($column === 'category')
                                            @if($transaction->category)
                                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                    {{ $transaction->category->name }}
                                                </span>
                                            @else
                                                <span class="text-sm text-[#706f6c] dark:text-[#A1A09A]">-</span>
                                            @endif
                                        @elseif($column === 'bank_account')
                                            <div class="text-sm">{{ $transaction->bankAccount->name ?? '-' }}</div>
                                        @elseif($column === 'provider')
                                            <div class="text-sm">{{ $transaction->provider ?? '-' }}</div>
                                        @elseif($column === 'external_id')
                                            <div class="text-sm font-mono">{{ $transaction->external_id ?? '-' }}</div>
                                        @elseif($column === 'is_imported')
                                            <span class="text-sm">{{ $transaction->is_imported ? 'Tak' : 'Nie' }}</span>
                                        @elseif($column === 'ai_analyzed')
                                            <span class="text-sm">{{ $transaction->ai_analyzed ? 'Tak' : 'Nie' }}</span>
                                        @elseif($column === 'created_at')
                                            <div class="text-sm">{{ $transaction->created_at->format('Y-m-d H:i') }}</div>
                                        @elseif($column === 'updated_at')
                                            <div class="text-sm">{{ $transaction->updated_at->format('Y-m-d H:i') }}</div>
                                        @endif
                                    </td>
                                @endif
                            @endforeach
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="{{ route('transactions.show', $transaction->id) }}" 
                                   class="text-[#f53003] dark:text-[#FF4433] hover:underline">
                                    Szczegóły
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($selectedColumns) + 2 }}" class="px-6 py-8 text-center text-[#706f6c] dark:text-[#A1A09A]">
                                Brak transakcji
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Paginacja -->
        @if($transactions->hasPages())
            <div class="px-6 py-4 border-t border-[#e3e3e0] dark:border-[#3E3E3A]">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal wyboru kolumn -->
<div id="column-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white dark:bg-[#161615] rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] p-6 max-w-2xl w-full mx-4 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Wybór kolumn</h2>
            <button id="column-close" class="text-[#706f6c] dark:text-[#A1A09A] hover:text-[#1b1b18] dark:hover:text-[#EDEDEC]">
                ✕
            </button>
        </div>
        <form id="column-form" method="GET" action="{{ route('transactions.index') }}">
            @foreach($filters as $key => $value)
                @if($value)
                    @if(is_array($value))
                        @foreach($value as $v)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endif
            @endforeach
            <div class="space-y-2">
                @foreach($availableColumns as $key => $label)
                    <label class="flex items-center justify-between cursor-pointer py-2 px-3 hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a] rounded">
                        <span class="text-sm text-[#1b1b18] dark:text-[#EDEDEC]">{{ $label }}</span>
                        <div class="relative inline-block w-11 h-6">
                            <input type="checkbox" name="columns[]" value="{{ $key }}" 
                                   {{ in_array($key, $selectedColumns) ? 'checked' : '' }}
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-300 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#1b1b18] dark:peer-focus:ring-[#EDEDEC] rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#1b1b18] dark:peer-checked:bg-[#EDEDEC]"></div>
                        </div>
                    </label>
                @endforeach
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" id="column-close" class="px-4 py-2 border border-[#19140035] dark:border-[#3E3E3A] hover:border-[#1915014a] dark:hover:border-[#62605b] rounded-sm transition-colors text-[#1b1b18] dark:text-[#EDEDEC]">
                    Anuluj
                </button>
                <button type="submit" class="px-4 py-2 bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] rounded-sm hover:bg-black dark:hover:bg-white transition-colors">
                    Zastosuj
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
