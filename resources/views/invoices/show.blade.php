@extends('components.layout')

@section('title', 'Szczegóły faktury')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <a href="{{ route('invoices.index') }}" class="text-sm text-[#706f6c] dark:text-[#A1A09A] hover:text-[#1b1b18] dark:hover:text-[#EDEDEC] mb-2 inline-block">
                ← Powrót do listy
            </a>
            <h1 class="text-3xl font-bold">Faktura {{ $invoice->invoice_number ?? '#' . $invoice->id }}</h1>
        </div>
    </div>

    <div class="space-y-6">
        @foreach($allFields as $sectionName => $fields)
            <div class="bg-white dark:bg-[#161615] p-6 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm">
                <h2 class="text-xl font-semibold mb-4">{{ $sectionName }}</h2>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($fields as $label => $value)
                        @if($value !== null && $value !== '')
                            <div>
                                <dt class="text-sm text-[#706f6c] dark:text-[#A1A09A]">{{ $label }}</dt>
                                <dd class="mt-1">
                                    @if($label === 'Status płatności')
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
                                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statusColors[$value] ?? $statusColors['pending'] }}">
                                            {{ $statusLabels[$value] ?? $value }}
                                        </span>
                                    @elseif($label === 'Metadata')
                                        <pre class="text-xs bg-[#FDFDFC] dark:bg-[#0a0a0a] p-3 rounded border border-[#e3e3e0] dark:border-[#3E3E3A] overflow-x-auto">{{ $value }}</pre>
                                    @else
                                        <span class="font-medium">{{ $value }}</span>
                                    @endif
                                </dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>
        @endforeach

        <!-- Pozycje faktury -->
        @if($invoice->items->count() > 0)
            <div class="bg-white dark:bg-[#161615] p-6 rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] shadow-sm">
                <h2 class="text-xl font-semibold mb-4">Pozycje faktury</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-[#FDFDFC] dark:bg-[#0a0a0a] border-b border-[#e3e3e0] dark:border-[#3E3E3A]">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">Nazwa</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">Ilość</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">Cena</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">VAT</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-[#706f6c] dark:text-[#A1A09A]">Wartość</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e3e3e0] dark:divide-[#3E3E3A]">
                            @foreach($invoice->items as $item)
                                <tr>
                                    <td class="px-4 py-2">
                                        <div class="font-medium">{{ $item->name }}</div>
                                        @if($item->description)
                                            <div class="text-sm text-[#706f6c] dark:text-[#A1A09A]">{{ $item->description }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">{{ $item->quantity }} {{ $item->unit ?? 'szt' }}</td>
                                    <td class="px-4 py-2">{{ number_format($item->unit_price, 2, ',', ' ') }} {{ $invoice->currency }}</td>
                                    <td class="px-4 py-2">{{ $item->tax_rate }}%</td>
                                    <td class="px-4 py-2 text-right font-medium">{{ number_format($item->gross_amount, 2, ',', ' ') }} {{ $invoice->currency }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
