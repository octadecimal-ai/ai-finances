<?php

namespace App\Console\Commands;

use App\Models\Finance;
use App\Services\Google\GoogleDriveService;
use App\Services\Google\GoogleSheetsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportWydatkiFromSheets extends Command
{
    protected $signature = 'import:wydatki-from-sheets {--spreadsheet-id=} {--sheet-title=} {--dry-run}';
    protected $description = 'Importuj dane z Google Sheets do tabeli finances';

    private GoogleDriveService $googleDriveService;
    private GoogleSheetsService $googleSheetsService;

    public function __construct(GoogleDriveService $googleDriveService, GoogleSheetsService $googleSheetsService)
    {
        parent::__construct();
        $this->googleDriveService = $googleDriveService;
        $this->googleSheetsService = $googleSheetsService;
    }

    public function handle()
    {
        $spreadsheetId = $this->option('spreadsheet-id');
        $sheetTitle = $this->option('sheet-title');
        $dryRun = $this->option('dry-run');

        if (!$spreadsheetId) {
            // Wyszukaj plik "Kopia Wydatki"
            $this->info('🔍 Wyszukiwanie pliku "Kopia Wydatki"...');
            $files = $this->googleDriveService->searchFiles('Kopia Wydatki');
            
            if (empty($files)) {
                $this->error('❌ Nie znaleziono pliku "Kopia Wydatki"');
                return 1;
            }

            $sheetsFile = $files[0];
            $spreadsheetId = $this->extractSpreadsheetId($sheetsFile['web_view_link']);
            
            if (!$spreadsheetId) {
                $this->error('❌ Nie można wyciągnąć ID arkusza');
                return 1;
            }

            $this->info("📋 Użyto ID arkusza: {$spreadsheetId}");
        }

        if (!$sheetTitle) {
            // Pobierz metadane aby znaleźć pierwszy arkusz
            $metadata = $this->googleSheetsService->getSheetMetadata($spreadsheetId);
            if (!$metadata || empty($metadata['sheets'])) {
                $this->error('❌ Nie można pobrać metadanych arkusza');
                return 1;
            }

            $sheetTitle = $metadata['sheets'][0]['title'];
            $this->info("📋 Użyto arkusza: {$sheetTitle}");
        }

        $this->info("📊 Importowanie danych z arkusza: {$sheetTitle}");

        // Pobierz dane z arkusza
        $data = $this->googleSheetsService->getSheetDataByTitle($spreadsheetId, $sheetTitle);
        if (!$data || empty($data)) {
            $this->error('❌ Nie można pobrać danych z arkusza');
            return 1;
        }

        $this->info("📈 Znaleziono " . count($data) . " wierszy danych");

        // Pobierz nagłówki (pierwszy wiersz)
        $headers = array_shift($data);
        $this->info("🏷️  Nagłówki: " . implode(', ', $headers));

        // Mapuj kolumny
        $columnMap = $this->mapColumns($headers);
        $this->info("🗺️  Mapowanie kolumn:");
        foreach ($columnMap as $sheetColumn => $dbColumn) {
            $this->line("   {$sheetColumn} → {$dbColumn}");
        }

        // Przetwórz dane
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($data as $index => $row) {
            $rowNumber = $index + 2; // +2 bo usunęliśmy nagłówki i index zaczyna się od 0

            try {
                $financeData = $this->processRow($row, $columnMap, $spreadsheetId, $sheetTitle, $rowNumber);
                
                if (!$financeData) {
                    $skipped++;
                    continue;
                }

                if (!$dryRun) {
                    Finance::create($financeData);
                }
                
                $imported++;
                $this->line("✅ Wiersz {$rowNumber}: {$financeData['opis']} - {$financeData['kwota']} PLN");

            } catch (\Exception $e) {
                $errors++;
                $this->error("❌ Błąd w wierszu {$rowNumber}: " . $e->getMessage());
                Log::error('Import wydatków błąd', [
                    'row' => $rowNumber,
                    'data' => $row,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("\n📊 Podsumowanie importu:");
        $this->line("   ✅ Zaimportowano: {$imported}");
        $this->line("   ⏭️  Pominięto: {$skipped}");
        $this->line("   ❌ Błędy: {$errors}");
        
        if ($dryRun) {
            $this->warn("🔍 Tryb testowy - dane nie zostały zapisane");
        }

        return 0;
    }

    /**
     * Mapuj kolumny z arkusza na kolumny w bazie danych
     */
    private function mapColumns(array $headers): array
    {
        $map = [];
        
        foreach ($headers as $index => $header) {
            $header = strtolower(trim($header));
            
            switch ($header) {
                case 'data':
                case 'date':
                case 'dzien':
                    $map[$index] = 'data';
                    break;
                case 'opis':
                case 'description':
                case 'nazwa':
                case 'tytul':
                    $map[$index] = 'opis';
                    break;
                case 'kwota':
                case 'amount':
                case 'cena':
                case 'suma':
                    $map[$index] = 'kwota';
                    break;
                case 'kategoria':
                case 'category':
                case 'kat':
                    $map[$index] = 'kategoria';
                    break;
                case 'status':
                case 'stan':
                    $map[$index] = 'status';
                    break;
                case 'metoda_platnosci':
                case 'payment_method':
                case 'sposob_platnosci':
                    $map[$index] = 'metoda_platnosci';
                    break;
                case 'konto':
                case 'account':
                case 'bank':
                    $map[$index] = 'konto';
                    break;
                case 'notatki':
                case 'notes':
                case 'uwagi':
                    $map[$index] = 'notatki';
                    break;
            }
        }
        
        return $map;
    }

    /**
     * Przetwórz wiersz danych
     */
    private function processRow(array $row, array $columnMap, string $spreadsheetId, string $sheetTitle, int $rowNumber): ?array
    {
        $data = [
            'source_file' => $spreadsheetId,
            'source_id' => "{$sheetTitle}_row_{$rowNumber}",
        ];

        // Sprawdź czy mamy wymagane kolumny
        $hasRequired = false;
        
        foreach ($columnMap as $columnIndex => $dbColumn) {
            if (!isset($row[$columnIndex])) {
                continue;
            }
            
            $value = trim($row[$columnIndex]);
            
            switch ($dbColumn) {
                case 'data':
                    $date = $this->parseDate($value);
                    if ($date) {
                        $data['data'] = $date;
                        $hasRequired = true;
                    }
                    break;
                    
                case 'opis':
                    if (!empty($value)) {
                        $data['opis'] = $value;
                        $hasRequired = true;
                    }
                    break;
                    
                case 'kwota':
                    $amount = $this->parseAmount($value);
                    if ($amount !== null) {
                        $data['kwota'] = $amount;
                        $hasRequired = true;
                    }
                    break;
                    
                case 'kategoria':
                    if (!empty($value)) {
                        $data['kategoria'] = $value;
                    }
                    break;
                    
                case 'status':
                    if (!empty($value)) {
                        $data['status'] = $value;
                    }
                    break;
                    
                case 'metoda_platnosci':
                    if (!empty($value)) {
                        $data['metoda_platnosci'] = $value;
                    }
                    break;
                    
                case 'konto':
                    if (!empty($value)) {
                        $data['konto'] = $value;
                    }
                    break;
                    
                case 'notatki':
                    if (!empty($value)) {
                        $data['notatki'] = $value;
                    }
                    break;
            }
        }
        
        return $hasRequired ? $data : null;
    }

    /**
     * Parsuj datę
     */
    private function parseDate(string $value): ?string
    {
        // Spróbuj różne formaty daty
        $formats = ['Y-m-d', 'd.m.Y', 'd/m/Y', 'Y/m/d', 'd-m-Y', 'Y-m-d H:i:s'];
        
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        
        return null;
    }

    /**
     * Parsuj kwotę
     */
    private function parseAmount(string $value): ?float
    {
        // Usuń spacje i zamień przecinki na kropki
        $value = str_replace([' ', ','], ['', '.'], $value);
        
        // Usuń znaki walut
        $value = preg_replace('/[^\d.-]/', '', $value);
        
        if (is_numeric($value)) {
            return (float) $value;
        }
        
        return null;
    }

    /**
     * Wyciąga ID arkusza z linku Google Sheets
     */
    private function extractSpreadsheetId(string $url): ?string
    {
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
} 