<?php

namespace App\Services\Import;

use App\Models\Transaction;
use App\Models\Category;
use App\Models\User;
use App\Models\BankAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class CsvImportService
{
    private array $supportedFormats = [
        'mbank' => [
            'date' => 0,
            'description' => 1,
            'amount' => 2,
            'balance' => 3,
        ],
        'ing' => [
            'date' => 0,
            'description' => 1,
            'amount' => 2,
            'balance' => 3,
        ],
        'pko' => [
            'date' => 0,
            'description' => 1,
            'amount' => 2,
            'balance' => 3,
        ],
        'revolut' => [
            'rodzaj' => 0,           // Rodzaj transakcji (Płatność kartą, Wymiana, etc.)
            'produkt' => 1,          // Produkt (Bieżące, etc.)
            'data_rozpoczecia' => 2, // Data rozpoczęcia
            'data_zrealizowania' => 3, // Data zrealizowania
            'opis' => 4,            // Opis
            'kwota' => 5,           // Kwota
            'oplata' => 6,          // Opłata
            'waluta' => 7,          // Waluta
            'state' => 8,           // State (ZAKOŃCZONO, etc.)
            'saldo' => 9,           // Saldo
        ],
    ];

    public function importCsv(User $user, UploadedFile $file, string $format, int $bankAccountId = null): array
    {
        try {
            $format = strtolower($format);
            
            if (!isset($this->supportedFormats[$format])) {
                throw new Exception("Nieobsługiwany format: {$format}");
            }

            $csvData = $this->readCsvFile($file);
            $formatConfig = $this->supportedFormats[$format];
            
            $importedCount = 0;
            $errors = [];
            $bankAccount = $bankAccountId ? BankAccount::find($bankAccountId) : null;

            DB::beginTransaction();

            foreach ($csvData as $rowIndex => $row) {
                try {
                    if ($this->isHeaderRow($row, $format)) {
                        continue;
                    }

                    $transactionData = $this->parseRow($row, $formatConfig, $format);
                    
                    if ($this->importTransaction($user, $transactionData, $bankAccount)) {
                        $importedCount++;
                    }
                } catch (Exception $e) {
                    $errors[] = [
                        'row' => $rowIndex + 1,
                        'error' => $e->getMessage(),
                        'data' => $row,
                    ];
                }
            }

            DB::commit();

            Log::info('CSV import completed', [
                'user_id' => $user->id,
                'format' => $format,
                'imported_count' => $importedCount,
                'error_count' => count($errors),
            ]);

            return [
                'success' => true,
                'imported_count' => $importedCount,
                'errors' => $errors,
            ];

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('CSV import failed', [
                'user_id' => $user->id,
                'format' => $format,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'imported_count' => 0,
                'errors' => [],
            ];
        }
    }

    private function readCsvFile(UploadedFile $file): array
    {
        $content = file_get_contents($file->getPathname());
        
        // Detect encoding
        $encodings = ['UTF-8', 'ISO-8859-2'];
        // Windows-1250 może nie być dostępne w PHP 8.5, sprawdź czy jest dostępne
        if (function_exists('mb_list_encodings')) {
            $availableEncodings = mb_list_encodings();
            if (in_array('Windows-1250', $availableEncodings)) {
                $encodings[] = 'Windows-1250';
            }
        }
        
        $encoding = mb_detect_encoding($content, $encodings, true);
        
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        } elseif (!$encoding) {
            // Jeśli nie można wykryć, załóż UTF-8
            $encoding = 'UTF-8';
        }

        $lines = explode("\n", $content);
        $csvData = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                // Revolut używa przecinka jako separatora, inne banki średnika
                $delimiter = strpos($line, ',') !== false ? ',' : ';';
                $csvData[] = str_getcsv($line, $delimiter);
            }
        }

        return $csvData;
    }

    private function isHeaderRow(array $row, string $format): bool
    {
        $firstCell = strtolower(trim($row[0] ?? ''));
        
        $headerPatterns = [
            'mbank' => ['data operacji', 'data', 'date'],
            'ing' => ['data operacji', 'data', 'date'],
            'pko' => ['data operacji', 'data', 'date'],
            'revolut' => ['rodzaj', 'produkt', 'data rozpoczęcia', 'data zrealizowania', 'opis', 'kwota', 'opłata', 'waluta', 'state', 'saldo'],
        ];

        $patterns = $headerPatterns[$format] ?? [];
        
        foreach ($patterns as $pattern) {
            if (strpos($firstCell, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseRow(array $row, array $formatConfig, string $format): array
    {
        if ($format === 'revolut') {
            return $this->parseRevolutRow($row, $formatConfig);
        }

        $date = $this->parseDate($row[$formatConfig['date']] ?? '');
        $description = trim($row[$formatConfig['description']] ?? '');
        $amount = $this->parseAmount($row[$formatConfig['amount']] ?? '', $format);
        $currency = 'PLN';

        if (empty($date) || empty($description)) {
            throw new Exception('Brak wymaganych danych: data lub opis');
        }

        return [
            'transaction_date' => $date,
            'description' => $description,
            'amount' => $amount,
            'currency' => $currency,
            'type' => $amount >= 0 ? 'credit' : 'debit',
            'status' => 'completed',
            'is_imported' => true,
        ];
    }

    private function parseRevolutRow(array $row, array $formatConfig): array
    {
        $rodzaj = trim($row[$formatConfig['rodzaj']] ?? '');
        $produkt = trim($row[$formatConfig['produkt']] ?? '');
        $dataRozpoczecia = $this->parseDate($row[$formatConfig['data_rozpoczecia']] ?? '');
        $dataZrealizowania = $this->parseDate($row[$formatConfig['data_zrealizowania']] ?? '');
        $opis = trim($row[$formatConfig['opis']] ?? '');
        $kwota = $this->parseAmount($row[$formatConfig['kwota']] ?? '', 'revolut');
        $oplata = $this->parseAmount($row[$formatConfig['oplata']] ?? '', 'revolut');
        $waluta = trim($row[$formatConfig['waluta']] ?? 'PLN');
        $state = trim($row[$formatConfig['state']] ?? '');
        $saldo = $this->parseAmount($row[$formatConfig['saldo']] ?? '', 'revolut');

        if (empty($dataRozpoczecia) || empty($opis)) {
            throw new Exception('Brak wymaganych danych: data rozpoczęcia lub opis');
        }

        // Określ typ transakcji na podstawie Rodzaj i Kwoty
        $type = $this->determineRevolutType($rodzaj, $kwota);
        
        // Określ status na podstawie State
        $status = $this->parseRevolutStatus($state);

        // Przygotuj metadata z dodatkowymi danymi
        $metadata = [
            'rodzaj' => $rodzaj,
            'produkt' => $produkt,
            'oplata' => $oplata,
            'state' => $state,
        ];

        return [
            'transaction_date' => $dataRozpoczecia,
            'booking_date' => $dataZrealizowania ?: $dataRozpoczecia,
            'value_date' => $dataZrealizowania ?: $dataRozpoczecia,
            'description' => $opis,
            'amount' => $kwota,
            'currency' => $waluta,
            'type' => $type,
            'status' => $status,
            'balance_after' => $saldo,
            'metadata' => $metadata,
            'provider' => 'revolut',
            'is_imported' => true,
        ];
    }

    private function determineRevolutType(string $rodzaj, float $amount): string
    {
        // Określ typ na podstawie Rodzaj i znaku kwoty
        $rodzajLower = strtolower($rodzaj);
        
        // Jeśli kwota jest dodatnia, to credit (wpływ)
        if ($amount > 0) {
            return 'credit';
        }
        
        // Jeśli kwota jest ujemna, to debit (wydatek)
        if ($amount < 0) {
            return 'debit';
        }
        
        // Domyślnie na podstawie rodzaju
        if (strpos($rodzajLower, 'wymiana') !== false || 
            strpos($rodzajLower, 'top up') !== false ||
            strpos($rodzajLower, 'przelew') !== false) {
            return $amount >= 0 ? 'credit' : 'debit';
        }
        
        return 'debit';
    }

    private function parseRevolutStatus(string $state): string
    {
        $stateLower = strtolower(trim($state));
        
        if ($stateLower === 'zakończono' || $stateLower === 'completed' || $stateLower === 'completed') {
            return 'completed';
        }
        
        if ($stateLower === 'pending' || $stateLower === 'oczekujące') {
            return 'pending';
        }
        
        if ($stateLower === 'failed' || $stateLower === 'nieudane') {
            return 'failed';
        }
        
        return 'completed'; // Domyślnie completed
    }

    private function parseDate(string $dateString): ?string
    {
        $dateString = trim($dateString);
        
        if (empty($dateString)) {
            return null;
        }

        // Common date formats
        $formats = [
            'Y-m-d H:i:s',  // Revolut format: 2022-04-04 15:21:56
            'Y-m-d',
            'd.m.Y',
            'd/m/Y',
            'Y/m/d',
            'd-m-Y',
            'Y/m/d H:i:s',
            'd.m.Y H:i:s',
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                // Jeśli format zawiera czas, zwróć pełną datę z czasem
                if (strpos($format, 'H:i:s') !== false || strpos($format, 'H:i') !== false) {
                    return $date->format('Y-m-d H:i:s');
                }
                // W przeciwnym razie zwróć datę z czasem 00:00:00
                return $date->format('Y-m-d H:i:s');
            }
        }

        throw new Exception("Nieprawidłowy format daty: {$dateString}");
    }

    private function parseAmount(string $amountString, string $format): float
    {
        $amountString = trim($amountString);
        
        if (empty($amountString)) {
            return 0.0;
        }

        // Remove currency symbols and spaces
        $amountString = preg_replace('/[^\d.,\-]/', '', $amountString);
        
        // Handle different decimal separators
        if (strpos($amountString, ',') !== false && strpos($amountString, '.') !== false) {
            // Format like "1.234,56"
            $amountString = str_replace('.', '', $amountString);
            $amountString = str_replace(',', '.', $amountString);
        } elseif (strpos($amountString, ',') !== false) {
            // Format like "1234,56"
            $amountString = str_replace(',', '.', $amountString);
        }

        $amount = (float) $amountString;

        // Revolut amounts are already in correct format
        if ($format !== 'revolut') {
            // For Polish banks, negative amounts are expenses
            $amount = -abs($amount);
        }

        return $amount;
    }

    private function importTransaction(User $user, array $transactionData, ?BankAccount $bankAccount): bool
    {
        // Check if transaction already exists
        $existingTransaction = Transaction::where('user_id', $user->id)
            ->where('description', $transactionData['description'])
            ->where('amount', $transactionData['amount'])
            ->where('transaction_date', $transactionData['transaction_date'])
            ->first();

        if ($existingTransaction) {
            return false; // Already imported
        }

        // Try to auto-categorize
        $categoryId = $this->autoCategorize($transactionData['description']);

        Transaction::create([
            'user_id' => $user->id,
            'bank_account_id' => $bankAccount?->id,
            'category_id' => $categoryId,
            'description' => $transactionData['description'],
            'amount' => $transactionData['amount'],
            'currency' => $transactionData['currency'],
            'transaction_date' => $transactionData['transaction_date'],
            'booking_date' => $transactionData['booking_date'] ?? null,
            'value_date' => $transactionData['value_date'] ?? null,
            'type' => $transactionData['type'],
            'status' => $transactionData['status'],
            'balance_after' => $transactionData['balance_after'] ?? null,
            'metadata' => $transactionData['metadata'] ?? null,
            'provider' => $transactionData['provider'] ?? null,
            'is_imported' => $transactionData['is_imported'],
        ]);

        return true;
    }

    private function autoCategorize(string $description): ?int
    {
        $description = strtolower($description);
        
        $categoryPatterns = [
            'Jedzenie' => [
                'biedronka', 'lidl', 'tesco', 'carrefour', 'auchan', 'kaufland',
                'restauracja', 'pizzeria', 'kebab', 'mcdonalds', 'kfc', 'burger',
                'cafe', 'kawiarnia', 'bar', 'pub', 'restaurant', 'food', 'meal',
                'piekarnia', 'cukiernia', 'delikatesy', 'warzywniak',
            ],
            'Transport' => [
                'uber', 'bolt', 'taxi', 'pkp', 'pks', 'mpk', 'ztm', 'metro',
                'autobus', 'tramwaj', 'pociąg', 'bus', 'train', 'transport',
                'orlen', 'bp', 'shell', 'paliwo', 'benzyna', 'diesel',
                'parking', 'parkomat', 'myto', 'viatoll',
            ],
            'Zakupy' => [
                'allegro', 'olx', 'amazon', 'ebay', 'zalando', 'reserved',
                'h&m', 'zara', 'uniqlo', 'decathlon', 'media markt', 'saturn',
                'empik', 'bookstore', 'księgarnia', 'sklep', 'shop', 'store',
            ],
            'Rachunki' => [
                'pge', 'tauron', 'energa', 'innogy', 'woda', 'kanalizacja',
                'gaz', 'prąd', 'elektryczność', 'internet', 'orange', 't-mobile',
                'play', 'plus', 'vodafone', 'netia', 'upc', 'vectra',
                'czynsz', 'kredyt', 'pożyczka', 'leasing', 'ubezpieczenie',
            ],
            'Zdrowie' => [
                'apteka', 'pharmacy', 'lekarz', 'dentysta', 'stomatolog',
                'szpital', 'przychodnia', 'klinika', 'medycyna', 'health',
                'leki', 'medicine', 'vitamin', 'witamina',
            ],
            'Edukacja' => [
                'uniwersytet', 'uczelnia', 'szkoła', 'kurs', 'szkolenie',
                'książka', 'podręcznik', 'materiały', 'education', 'study',
                'biblioteka', 'library',
            ],
            'Rozrywka' => [
                'kino', 'teatr', 'muzeum', 'galeria', 'koncert', 'festival',
                'netflix', 'spotify', 'youtube', 'hbo', 'disney', 'amazon prime',
                'gry', 'games', 'playstation', 'xbox', 'nintendo',
                'basen', 'siłownia', 'fitness', 'sport', 'rekreacja',
            ],
        ];

        foreach ($categoryPatterns as $categoryName => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($description, $pattern) !== false) {
                    $category = Category::where('name', $categoryName)->first();
                    return $category?->id;
                }
            }
        }

        return null;
    }

    public function getSupportedFormats(): array
    {
        return array_keys($this->supportedFormats);
    }

    public function validateCsvFormat(UploadedFile $file, string $format): bool
    {
        try {
            $csvData = $this->readCsvFile($file);
            
            if (empty($csvData)) {
                return false;
            }

            $formatConfig = $this->supportedFormats[strtolower($format)] ?? null;
            
            if (!$formatConfig) {
                return false;
            }

            // Check if first row has expected number of columns
            $firstRow = $csvData[0];
            $expectedColumns = max(array_values($formatConfig)) + 1;
            
            return count($firstRow) >= $expectedColumns;
        } catch (Exception $e) {
            return false;
        }
    }
} 