<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportExchangeRates extends Command
{
    protected $signature = 'import:exchange-rates 
                            {year? : Rok do importu (np. 2025) lub "all" dla wszystkich lat}
                            {--directory= : Katalog z plikami CSV (nadpisuje EXCHANGE_RATES_DIR)}';

    protected $description = 'Importuj kursy walut z plików CSV NBP';

    public function handle(): int
    {
        $year = $this->argument('year');
        $directory = $this->option('directory') ?? env('EXCHANGE_RATES_DIR');

        if (empty($directory)) {
            $this->error("❌ EXCHANGE_RATES_DIR nie jest ustawione w .env");
            return 1;
        }

        if (!is_dir($directory)) {
            $this->error("❌ Katalog nie istnieje: {$directory}");
            return 1;
        }

        // Określ rok do importu
        if (empty($year)) {
            $year = date('Y'); // Domyślnie bieżący rok
        }

        $this->info("📁 Importowanie kursów walut z: {$directory}");
        $this->info("📅 Rok: {$year}");

        $files = [];
        
        if ($year === 'all') {
            // Importuj wszystkie pliki
            $files = glob($directory . '/archiwum_tab_a_*.csv');
        } else {
            // Importuj plik dla konkretnego roku
            $file = $directory . "/archiwum_tab_a_{$year}.csv";
            if (file_exists($file)) {
                $files[] = $file;
            } else {
                $this->warn("⚠️  Nie znaleziono pliku: {$file}");
                return 0;
            }
        }

        if (empty($files)) {
            $this->warn("⚠️  Nie znaleziono plików CSV w katalogu");
            return 0;
        }

        $totalImported = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($files as $file) {
            try {
                $this->line("📄 Przetwarzanie: " . basename($file));
                
                $result = $this->importCsvFile($file);
                
                $totalImported += $result['imported'];
                $totalSkipped += $result['skipped'];
                $totalErrors += $result['errors'];
                
                $this->info("  ✅ Zaimportowano: {$result['imported']}, ⏭️  Pominięto: {$result['skipped']}, ❌ Błędy: {$result['errors']}");
                
            } catch (\Exception $e) {
                $this->error("  ❌ Błąd: " . $e->getMessage());
                Log::error('Exchange rates import failed', [
                    'file' => $file,
                    'error' => $e->getMessage(),
                ]);
                $totalErrors++;
            }
        }

        $this->newLine();
        $this->info("📊 Podsumowanie:");
        $this->info("  ✅ Zaimportowano: {$totalImported}");
        $this->info("  ⏭️  Pominięto: {$totalSkipped}");
        $this->info("  ❌ Błędy: {$totalErrors}");

        return $totalErrors > 0 ? 1 : 0;
    }

    private function importCsvFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Plik nie istnieje: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \Exception("Nie można otworzyć pliku: {$filePath}");
        }

        // Pierwsza linia to nagłówek z kodami walut (np. "1THB", "1USD", "1EUR")
        $header1 = fgetcsv($handle, 0, ';');
        
        // Druga linia to opisy walut (pomijamy)
        $header2 = fgetcsv($handle, 0, ';');
        
        if ($header1 === false) {
            fclose($handle);
            throw new \Exception("Nie można odczytać nagłówka CSV");
        }

        // Mapowanie kolumn - pierwsza kolumna to Data, ostatnie 2 to nr tabeli i pełny numer
        $currencyColumns = [];
        for ($i = 1; $i < count($header1) - 2; $i++) {
            $currencyCode = $this->extractCurrencyCode($header1[$i]);
            if ($currencyCode) {
                $currencyColumns[$i] = $currencyCode;
            }
        }

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        DB::beginTransaction();
        
        try {
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                if (count($row) < count($header2)) {
                    continue; // Pomiń niepełne wiersze
                }

                // Parsuj datę (format: YYYYMMDD)
                $dateStr = trim($row[0] ?? '');
                if (empty($dateStr)) {
                    continue; // Pomiń wiersze bez daty
                }

                try {
                    $date = \DateTime::createFromFormat('Ymd', $dateStr);
                    if ($date === false) {
                        $errors++;
                        continue;
                    }
                    $dateFormatted = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    $errors++;
                    continue;
                }

                // Pobierz nr tabeli i pełny numer (ostatnie 2 kolumny)
                $tableNumber = trim($row[count($row) - 2] ?? '');
                $fullTableNumber = trim($row[count($row) - 1] ?? '');

                // Importuj kursy dla każdej waluty
                foreach ($currencyColumns as $columnIndex => $currencyCode) {
                    $rateStr = trim($row[$columnIndex] ?? '');
                    
                    if (empty($rateStr)) {
                        continue; // Pomiń puste kursy
                    }

                    // Parsuj kurs (format: 0,1234 lub 1234,56)
                    $rate = $this->parseRate($rateStr);
                    if ($rate === null) {
                        $errors++;
                        continue;
                    }

                    // Sprawdź czy kurs już istnieje
                    $existing = ExchangeRate::where('date', $dateFormatted)
                        ->where('currency_code', $currencyCode)
                        ->first();

                    if ($existing) {
                        $skipped++;
                        continue;
                    }

                    // Zapisz kurs
                    ExchangeRate::create([
                        'date' => $dateFormatted,
                        'currency_code' => $currencyCode,
                        'rate' => $rate,
                        'table_number' => $tableNumber ?: null,
                        'full_table_number' => $fullTableNumber ?: null,
                    ]);

                    $imported++;
                }
            }

            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } finally {
            fclose($handle);
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Wyciąga kod waluty z nagłówka (np. "1THB" -> "THB", "1USD" -> "USD", "100HUF" -> "HUF")
     * 
     * Mapowanie znanych kodów walut z NBP:
     * - 1THB -> THB (bat tajlandzki)
     * - 1USD -> USD (dolar amerykański)
     * - 1EUR -> EUR (euro)
     * - 100HUF -> HUF (forint węgierski)
     * - 100JPY -> JPY (jen japoński)
     * - 10000IDR -> IDR (rupia indonezyjska)
     */
    private function extractCurrencyCode(string $header): ?string
    {
        // Usuń białe znaki
        $header = trim($header);
        
        // Pattern: liczba + kod waluty (np. "1THB", "1USD", "100HUF", "10000IDR")
        // Szukamy 1-5 cyfr na początku, potem 2-4 litery (kod waluty)
        // Ważne: kod waluty musi być na początku po liczbie, nie w środku słowa
        if (preg_match('/^(\d+)([A-Z]{2,4})/i', $header, $matches)) {
            $code = strtoupper($matches[2]);
            // Sprawdź czy to poprawny kod waluty (2-4 litery)
            if (strlen($code) >= 2 && strlen($code) <= 4) {
                // Sprawdź czy następny znak to litera (wtedy to część większego słowa)
                $nextChar = substr($header, strlen($matches[0]), 1);
                if (!ctype_alpha($nextChar)) {
                    return $code;
                }
            }
        }
        
        return null;
    }

    /**
     * Parsuje kurs z formatu polskiego (przecinek jako separator dziesiętny)
     */
    private function parseRate(string $rateStr): ?float
    {
        // Zamień przecinek na kropkę
        $rateStr = str_replace(',', '.', $rateStr);
        
        // Usuń białe znaki
        $rateStr = trim($rateStr);
        
        // Sprawdź czy to liczba
        if (!is_numeric($rateStr)) {
            return null;
        }
        
        return (float) $rateStr;
    }
}
