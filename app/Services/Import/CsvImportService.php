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
            'date' => 0,
            'description' => 1,
            'amount' => 2,
            'currency' => 3,
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
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-2', 'Windows-1250'], true);
        
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        $lines = explode("\n", $content);
        $csvData = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $csvData[] = str_getcsv($line, ';');
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
            'revolut' => ['date', 'data', 'completed date'],
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
        $date = $this->parseDate($row[$formatConfig['date']] ?? '');
        $description = trim($row[$formatConfig['description']] ?? '');
        $amount = $this->parseAmount($row[$formatConfig['amount']] ?? '', $format);
        $currency = $format === 'revolut' ? ($row[$formatConfig['currency']] ?? 'EUR') : 'PLN';

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

    private function parseDate(string $dateString): ?string
    {
        $dateString = trim($dateString);
        
        if (empty($dateString)) {
            return null;
        }

        // Common date formats
        $formats = [
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
                return $date->format('Y-m-d');
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
            'type' => $transactionData['type'],
            'status' => $transactionData['status'],
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