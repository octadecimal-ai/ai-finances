@extends('components.layout')

@section('title', 'Faktury')

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    const bulkActionsForm = document.getElementById('bulk-actions-form');
    const bulkActionSelect = document.getElementById('bulk-action-select');
    const bulkActionButton = document.getElementById('bulk-action-button');
    const selectedCount = document.getElementById('selected-count');
    
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
    }
    
    if (bulkActionSelect) {
        bulkActionSelect.addEventListener('change', function() {
            if (bulkActionButton) {
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
            
            if (bulkActionSelect.value === 'update_status') {
                const status = prompt('Wybierz nowy status:\n1. pending\n2. paid\n3. overdue\n4. cancelled');
                if (!status || !['pending', 'paid', 'overdue', 'cancelled'].includes(status)) {
                    e.preventDefault();
                    return false;
                }
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'payment_status';
                statusInput.value = status;
                this.appendChild(statusInput);
            }
            
            if (!confirm(`Czy na pewno chcesz wykonać akcję "${bulkActionSelect.options[bulkActionSelect.selectedIndex].text}" na ${ids.length} fakturach?`)) {
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
        <h1 class="text-3xl font-bold">Faktury</h1>
        <div class="flex gap-2">
            <button id="column-toggle" class="px-4 py-2 border border-[#19140035] dark:border-[#3E3E3A] hover:border-[#1915014a] dark:hover:border-[#62605b] rounded-sm transition-colors text-[#1b1b18] dark:text-[#EDEDEC]">
                Wybór kolumn
            </button>
        </div>
    </div>

    <!-- Statystyki -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-[#161615] p-4 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm">
            <div class="text-sm text-[#706f6c] dark:text-[#A1A09A]">Wszystkie</div>
            <div class="text-2xl font-semibold mt-1">{{ $stats['total'] }}</div>
        </div>
        <div class="bg-white dark:bg-[#161615] p-4 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm">
            <div class="text-sm text-[#706f6c] dark:text-[#A1A09A]">Opłacone</div>
            <div class="text-2xl font-semibold mt-1 text-green-600 dark:text-green-400">{{ $stats['paid'] }}</div>
        </div>
        <div class="bg-white dark:bg-[#161615] p-4 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm">
            <div class="text-sm text-[#706f6c] dark:text-[#A1A09A]">Oczekujące</div>
            <div class="text-2xl font-semibold mt-1 text-yellow-600 dark:text-yellow-400">{{ $stats['pending'] }}</div>
        </div>
        <div class="bg-white dark:bg-[#161615] p-4 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm">
            <div class="text-sm text-[#706f6c] dark:text-[#A1A09A]">Przeterminowane</div>
            <div class="text-2xl font-semibold mt-1 text-red-600 dark:text-red-400">{{ $stats['overdue'] }}</div>
        </div>
    </div>

    <!-- Filtry -->
    <div class="bg-white dark:bg-[#161615] p-4 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm">
        <form method="GET" action="{{ route('invoices.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm bg-white dark:bg-[#161615]">
                    <option value="">Wszystkie</option>
                    <option value="paid" {{ ($filters['status'] ?? '') === 'paid' ? 'selected' : '' }}>Opłacone</option>
                    <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Oczekujące</option>
                    <option value="overdue" {{ ($filters['status'] ?? '') === 'overdue' ? 'selected' : '' }}>Przeterminowane</option>
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
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numer, sprzedawca..." 
                       class="w-full px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm bg-white dark:bg-[#161615]">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] rounded-sm hover:bg-black dark:hover:bg-white transition-colors">
                    Filtruj
                </button>
                @if(!empty(array_filter($filters)))
                    <a href="{{ route('invoices.index', ['clear' => 1]) }}" class="px-4 py-2 border border-[#19140035] dark:border-[#3E3E3A] hover:border-[#1915014a] dark:hover:border-[#62605b] rounded-sm transition-colors text-[#1b1b18] dark:text-[#EDEDEC]">
                        Wyczyść
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Filtrowanie po sprzedawcach -->
    @if(count($availableSellers) > 0)
        <div class="bg-white dark:bg-[#161615] p-4 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm">
            <form method="GET" action="{{ route('invoices.index') }}" id="sellers-filter-form">
                @foreach($filters as $key => $value)
                    @if($key !== 'seller_names' && $value)
                        @if(is_array($value))
                            @foreach($value as $v)
                                <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endif
                @endforeach
                <label class="block text-sm font-medium mb-3">Sprzedawcy</label>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @foreach($availableSellers as $seller)
                        <label class="flex items-center justify-between cursor-pointer py-2 px-3 hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a] rounded">
                            <span class="text-sm text-[#1b1b18] dark:text-[#EDEDEC]">{{ $seller }}</span>
                            <div class="relative inline-block w-11 h-6">
                                <input type="checkbox" name="seller_names[]" value="{{ $seller }}" 
                                       {{ in_array($seller, $filters['seller_names'] ?? []) ? 'checked' : '' }}
                                       class="sr-only peer"
                                       onchange="document.getElementById('sellers-filter-form').submit()">
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
        <div class="flex items-center justify-between">
            <div class="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                Wybrano: <span id="selected-count" class="font-semibold">0</span> faktur
            </div>
            <form method="POST" action="{{ route('invoices.bulkAction') }}" class="flex gap-2">
                @csrf
                <select id="bulk-action-select" name="action" class="px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm bg-white dark:bg-[#161615]">
                    <option value="">Wybierz akcję...</option>
                    <option value="delete">Usuń</option>
                    <option value="update_status">Zmień status</option>
                </select>
                <button id="bulk-action-button" type="submit" disabled class="px-4 py-2 bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] rounded-sm hover:bg-black dark:hover:bg-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    Wykonaj
                </button>
            </form>
        </div>
    </div>

    <!-- Lista faktur -->
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
                                    <a href="{{ route('invoices.index', array_merge(request()->query(), ['sort' => $column, 'direction' => ($sortColumn === $column && $sortDirection === 'asc') ? 'desc' : 'asc'])) }}" class="flex items-center justify-between">
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
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a] transition-colors">
                            <td class="px-6 py-4">
                                <input type="checkbox" class="item-checkbox rounded border-[#e3e3e0] dark:border-[#3E3E3A]" value="{{ $invoice->id }}">
                            </td>
                            @foreach($selectedColumns as $column)
                                @if(isset($availableColumns[$column]))
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($column === 'id')
                                            <div class="font-medium">{{ $invoice->id }}</div>
                                        @elseif($column === 'invoice_number')
                                            <div class="font-medium">{{ $invoice->invoice_number ?? 'Brak numeru' }}</div>
                                        @elseif($column === 'invoice_date')
                                            <div class="text-sm">{{ $invoice->invoice_date ? $invoice->invoice_date->format('Y-m-d') : ($invoice->issue_date ? $invoice->issue_date->format('Y-m-d') : '-') }}</div>
                                        @elseif($column === 'issue_date')
                                            <div class="text-sm">{{ $invoice->issue_date ? $invoice->issue_date->format('Y-m-d') : '-' }}</div>
                                        @elseif($column === 'due_date')
                                            <div class="text-sm">{{ $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '-' }}</div>
                                        @elseif($column === 'seller_name')
                                            <div class="text-sm">{{ $invoice->seller_name ?? '-' }}</div>
                                        @elseif($column === 'seller_tax_id')
                                            <div class="text-sm">{{ $invoice->seller_tax_id ?? '-' }}</div>
                                        @elseif($column === 'buyer_name')
                                            <div class="text-sm">{{ $invoice->buyer_name ?? '-' }}</div>
                                        @elseif($column === 'buyer_tax_id')
                                            <div class="text-sm">{{ $invoice->buyer_tax_id ?? '-' }}</div>
                                        @elseif($column === 'subtotal')
                                            <div class="font-medium">{{ number_format($invoice->subtotal, 2, ',', ' ') }} {{ $invoice->currency }}</div>
                                        @elseif($column === 'tax_amount')
                                            <div class="font-medium">{{ number_format($invoice->tax_amount, 2, ',', ' ') }} {{ $invoice->currency }}</div>
                                        @elseif($column === 'total_amount')
                                            <div class="font-medium">{{ number_format($invoice->total_amount, 2, ',', ' ') }} {{ $invoice->currency }}</div>
                                        @elseif($column === 'netto_pln')
                                            <div class="font-medium text-blue-600 dark:text-blue-400">
                                                @if($invoice->netto_pln !== null)
                                                    {{ number_format($invoice->netto_pln, 2, ',', ' ') }} PLN
                                                @else
                                                    <span class="text-[#706f6c] dark:text-[#A1A09A]">-</span>
                                                @endif
                                            </div>
                                        @elseif($column === 'vat_pln')
                                            <div class="font-medium text-blue-600 dark:text-blue-400">
                                                @if($invoice->vat_pln !== null)
                                                    {{ number_format($invoice->vat_pln, 2, ',', ' ') }} PLN
                                                @else
                                                    <span class="text-[#706f6c] dark:text-[#A1A09A]">-</span>
                                                @endif
                                            </div>
                                        @elseif($column === 'brutto_pln')
                                            <div class="font-medium text-blue-600 dark:text-blue-400">
                                                @if($invoice->brutto_pln !== null)
                                                    {{ number_format($invoice->brutto_pln, 2, ',', ' ') }} PLN
                                                @else
                                                    <span class="text-[#706f6c] dark:text-[#A1A09A]">-</span>
                                                @endif
                                            </div>
                                        @elseif($column === 'currency')
                                            <div class="text-sm">{{ $invoice->currency }}</div>
                                        @elseif($column === 'payment_method')
                                            <div class="text-sm">{{ $invoice->payment_method ?? '-' }}</div>
                                        @elseif($column === 'payment_status')
                                            @php
                                                $statusColors = [
                                                    'paid' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                    'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                    'overdue' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                    'cancelled' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
                                                ];
                                                $statusLabels = [
                                                    'paid' => 'Opłacona',
                                                    'pending' => 'Oczekująca',
                                                    'overdue' => 'Przeterminowana',
                                                    'cancelled' => 'Anulowana',
                                                ];
                                            @endphp
                                            <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statusColors[$invoice->payment_status] ?? $statusColors['pending'] }}">
                                                {{ $statusLabels[$invoice->payment_status] ?? $invoice->payment_status }}
                                            </span>
                                        @elseif($column === 'paid_at')
                                            <div class="text-sm">{{ $invoice->paid_at ? $invoice->paid_at->format('Y-m-d H:i') : '-' }}</div>
                                        @elseif($column === 'transaction_id')
                                            <div class="text-sm">
                                                @if($invoice->transaction_id)
                                                    <a href="{{ route('transactions.show', $invoice->transaction_id) }}" 
                                                       class="text-blue-600 dark:text-blue-400 hover:underline">
                                                        #{{ $invoice->transaction_id }}
                                                    </a>
                                                @else
                                                    <span class="text-[#706f6c] dark:text-[#A1A09A]">-</span>
                                                @endif
                                            </div>
                                        @elseif($column === 'match_score')
                                            <div class="text-sm">
                                                @if($invoice->match_score !== null)
                                                    @php
                                                        $score = (float)$invoice->match_score;
                                                        $colorClass = $score >= 70 ? 'text-green-600 dark:text-green-400' : 
                                                                     ($score >= 50 ? 'text-yellow-600 dark:text-yellow-400' : 
                                                                     'text-orange-600 dark:text-orange-400');
                                                    @endphp
                                                    <span class="font-medium {{ $colorClass }}">
                                                        {{ number_format($score, 1) }}%
                                                    </span>
                                                @else
                                                    <span class="text-[#706f6c] dark:text-[#A1A09A]">-</span>
                                                @endif
                                            </div>
                                        @elseif($column === 'matched_at')
                                            <div class="text-sm">{{ $invoice->matched_at ? $invoice->matched_at->format('Y-m-d H:i') : '-' }}</div>
                                        @elseif($column === 'matched_amount')
                                            <div class="font-medium">
                                                @if($invoice->matched_amount !== null)
                                                    {{ number_format($invoice->matched_amount, 2, ',', ' ') }} {{ $invoice->matched_amount_currency ?? '-' }}
                                                @else
                                                    <span class="text-[#706f6c] dark:text-[#A1A09A]">-</span>
                                                @endif
                                            </div>
                                        @elseif($column === 'matched_date')
                                            <div class="text-sm">
                                                @if($invoice->matched_date)
                                                    {{ $invoice->matched_date->format('Y-m-d H:i') }}
                                                @else
                                                    <span class="text-[#706f6c] dark:text-[#A1A09A]">-</span>
                                                @endif
                                            </div>
                                        @elseif($column === 'file_name')
                                            <div class="text-sm">{{ $invoice->file_name ?? '-' }}</div>
                                        @elseif($column === 'source_type')
                                            <div class="text-sm">{{ $invoice->source_type ?? '-' }}</div>
                                        @elseif($column === 'created_at')
                                            <div class="text-sm">{{ $invoice->created_at->format('Y-m-d H:i') }}</div>
                                        @elseif($column === 'updated_at')
                                            <div class="text-sm">{{ $invoice->updated_at->format('Y-m-d H:i') }}</div>
                                        @endif
                                    </td>
                                @endif
                            @endforeach
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="{{ route('invoices.show', $invoice->id) }}" 
                                   class="text-[#f53003] dark:text-[#FF4433] hover:underline">
                                    Szczegóły
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($selectedColumns) + 2 }}" class="px-6 py-8 text-center text-[#706f6c] dark:text-[#A1A09A]">
                                Brak faktur
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Paginacja -->
        @if($invoices->hasPages())
            <div class="px-6 py-4 border-t border-[#e3e3e0] dark:border-[#3E3E3A]">
                {{ $invoices->links() }}
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
        <form id="column-form" method="GET" action="{{ route('invoices.index') }}">
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
