<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';
    
    protected static ?string $title = 'Dashboard';
    
    public function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('invoices')
                ->label('Faktury')
                ->url(route('invoices.index'))
                ->icon('heroicon-o-document-text')
                ->color('primary'),
            \Filament\Actions\Action::make('transactions')
                ->label('Historia płatności')
                ->url(route('transactions.index'))
                ->icon('heroicon-o-credit-card')
                ->color('primary'),
        ];
    }
}

