<?php

namespace App\Console\Commands;

use App\Models\Finance;
use App\Services\Google\GoogleDriveService;
use App\Services\Google\GoogleSheetsService;
use App\Services\FinancesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportWydatkiFromSheets extends Command
{
    protected $signature = 'import:wydatki-from-sheets {--spreadsheet-id=} {--sheet-title=} {--dry-run}';
    protected $description = 'Importuj dane z Google Sheets do tabeli wydatki';

    private GoogleDriveService $googleDriveService;
    private GoogleSheetsService $googleSheetsService;
    private FinancesService $financesService;

    public function __construct(GoogleDriveService $googleDriveService, GoogleSheetsService $googleSheetsService, FinancesService $financesService)
    {
        parent::__construct();
        $this->googleDriveService = $googleDriveService;
        $this->googleSheetsService = $googleSheetsService;
        $this->financesService = $financesService;
    }

    public function handle()
    {
        $spreadsheetId = $this->option('spreadsheet-id');
        $sheetTitle = $this->option('sheet-title') ?: 'Wydatki';
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

        $this->info("📊 Importowanie danych z arkusza: {$sheetTitle}");

        // Pobierz dane z arkusza przez eksport Excel
        $tempPath = storage_path('app/temp/' . uniqid() . '.xlsx');
        $success = $this->googleDriveService->exportSheetAsExcel($spreadsheetId, $tempPath);
        
        if (!$success) {
            $this->error('❌ Nie można eksportować arkusza jako Excel');
            return 1;
        }
        
        // Wczytaj jako Excel
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempPath);
        $worksheet = $spreadsheet->getSheetByName($sheetTitle);
        
        if (!$worksheet) {
            $this->error("❌ Arkusz '{$sheetTitle}' nie został znaleziony");
            unlink($tempPath);
            return 1;
        }
        
        // Pobierz dane z arkusza
        $data = [];
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        
        // Ogranicz do pierwszych 100 wierszy aby uniknąć problemów z pamięcią
        for ($row = 1; $row <= min(100, $highestRow); $row++) {
            $rowData = [];
            // Ogranicz do kolumn A-BQ (43 kolumny) - rzeczywisty zakres arkusza
            for ($col = 'A'; $col <= 'BQ'; $col++) {
                $cellValue = $worksheet->getCell($col . $row)->getValue();
                $rowData[] = $cellValue ?? '';
            }
            $data[] = $rowData;
        }
        
        // Usuń plik tymczasowy
        unlink($tempPath);
        
        if (empty($data)) {
            $this->error('❌ Nie można pobrać danych z arkusza');
            return 1;
        }

        $this->info("📈 Znaleziono " . count($data) . " wierszy danych");

        // Pobierz nagłówki (pierwszy wiersz)
        $headers = array_shift($data);
        $this->info("🏷️  Nagłówki: " . implode(', ', array_slice($headers, 0, 10)) . "...");

        if ($dryRun) {
            $this->warn("🔍 Tryb testowy - analizowanie danych...");
            
            // Pokaż pierwsze kilka wierszy
            for ($i = 0; $i < min(5, count($data)); $i++) {
                $row = $data[$i];
                $this->line("   Wiersz " . ($i + 2) . ": " . implode(' | ', array_slice($row, 0, 5)) . "...");
            }
            
            $this->info("📊 W trybie testowym nie importowano danych");
            return 0;
        }

        // Importuj dane używając FinancesService
        $result = $this->financesService->importFromExcel($data, $spreadsheetId, $sheetTitle);

        $this->info("\n📊 Podsumowanie importu:");
        $this->line("   ✅ Zaimportowano: {$result['imported']}");
        $this->line("   ⏭️  Pominięto: {$result['skipped']}");
        $this->line("   ❌ Błędy: {$result['errors']}");

        return 0;
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