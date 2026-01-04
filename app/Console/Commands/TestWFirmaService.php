<?php

namespace App\Console\Commands;

use App\Services\Banking\WFirmaService;
use Illuminate\Console\Command;

class TestWFirmaService extends Command
{
    protected $signature = 'test:wfirma {--month= : Miesiąc w formacie YYYY-MM (np. 2025-12)}';
    protected $description = 'Test wFirma API service - pobiera faktury za określony miesiąc';

    public function __construct(
        private WFirmaService $wfirmaService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Testowanie wFirma API Service...');
        $this->newLine();

        try {
            // Test konfiguracji
            $this->info('1. Sprawdzanie konfiguracji...');
            $config = [
                'Base URL' => config('banking.wfirma.base_url'),
                'Access Key' => config('banking.wfirma.access_key') ? 'Ustawiony' : 'Brak',
                'Secret Key' => config('banking.wfirma.secret_key') ? 'Ustawiony' : 'Brak',
                'App Key' => config('banking.wfirma.app_key') ? 'Ustawiony' : 'Brak',
                'Company ID' => config('banking.wfirma.company_id') ?: 'Brak',
            ];
            
            $this->table(['Ustawienie', 'Wartość'], collect($config)->map(fn($value, $key) => [$key, $value])->toArray());
            $this->newLine();

            // Sprawdź Company ID
            if (empty(config('banking.wfirma.company_id'))) {
                $this->error('✗ Company ID nie jest ustawione!');
                $this->warn('   Dodaj WFIRMA_COMPANY_ID do pliku .env');
                $this->warn('   Company ID znajdziesz w wFirma: Ustawienia > Moja firma (ID)');
                return 1;
            }
            
            // Test połączenia
            $this->info('2. Testowanie połączenia...');
            $connected = $this->wfirmaService->testConnection();
            if ($connected) {
                $this->info('✓ Połączenie udane');
            } else {
                $this->error('✗ Połączenie nieudane');
                $this->warn('   Sprawdź logi: tail -f storage/logs/laravel.log | grep wFirma');
                return 1;
            }
            $this->newLine();

            // Pobieranie faktur
            $month = $this->option('month');
            
            if ($month) {
                $this->info("3. Pobieranie faktur za {$month}...");
                
                // Parsuj miesiąc
                $date = \Carbon\Carbon::createFromFormat('Y-m', $month);
                $dateFrom = $date->startOfMonth()->format('Y-m-d');
                $dateTo = $date->endOfMonth()->format('Y-m-d');
                
                $this->info("   Okres: {$dateFrom} - {$dateTo}");
                
                $filters = [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ];
            } else {
                $this->info("3. Pobieranie faktur (bez filtrowania daty)...");
                $filters = ['limit' => 10]; // Pobierz ostatnie 10 faktur
            }
            
            $invoices = $this->wfirmaService->getInvoices($filters);
            
            if (!empty($invoices)) {
                $this->info('✓ Znaleziono ' . count($invoices) . ' faktur');
                $this->newLine();
                
                // Wyświetl pierwsze 10 faktur
                $displayInvoices = array_slice($invoices, 0, 10);
                $tableData = [];
                
                foreach ($displayInvoices as $invoice) {
                    $invoiceData = $invoice['invoice'] ?? $invoice;
                    $tableData[] = [
                        $invoiceData['id'] ?? 'N/A',
                        $invoiceData['number'] ?? 'N/A',
                        $invoiceData['date'] ?? 'N/A',
                        $invoiceData['contractor']['name'] ?? ($invoiceData['contractor_name'] ?? 'N/A'),
                        number_format($invoiceData['price_gross'] ?? ($invoiceData['total'] ?? 0), 2, ',', ' ') . ' PLN',
                        $invoiceData['status'] ?? 'N/A',
                    ];
                }
                
                $this->table([
                    'ID',
                    'Numer',
                    'Data',
                    'Kontrahent',
                    'Kwota',
                    'Status'
                ], $tableData);
                
                if (count($invoices) > 10) {
                    $this->info('... i ' . (count($invoices) - 10) . ' więcej faktur');
                }
            } else {
                $this->warn('⚠ Nie znaleziono faktur za podany okres');
            }
            
            $this->newLine();
            $this->info('✓ Test zakończony pomyślnie');
            
            return 0;
        } catch (\Exception $e) {
            $this->error('✗ Błąd podczas testowania: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
}

