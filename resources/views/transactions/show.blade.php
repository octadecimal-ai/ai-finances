@extends('components.layout')

@section('title', 'Szczegóły transakcji')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <a href="{{ route('transactions.index') }}" class="text-sm text-[#706f6c] dark:text-[#A1A09A] hover:text-[#1b1b18] dark:hover:text-[#EDEDEC] mb-2 inline-block">
                ← Powrót do listy
            </a>
            <h1 class="text-3xl font-bold">Transakcja #{{ $transaction->id }}</h1>
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
                                    @if($label === 'Typ')
                                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $value === 'Przychód' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                            {{ $value }}
                                        </span>
                                    @elseif($label === 'Status')
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            {{ $value }}
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
    </div>
</div>
@endsection
