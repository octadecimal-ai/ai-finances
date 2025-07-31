<?php

namespace App\Console\Commands;

use App\Services\Google\GoogleDriveService;
use App\Services\Google\ExcelService;
use App\Services\Google\GoogleSheetsService;
use Illuminate\Console\Command;

class FindWydatkiFile extends Command
{
    protected $signature = 'google:find-wydatki';
    protected $description = 'Znajdź plik "Kopia Wydatki" na Google Drive i sprawdź jego strukturę';

    private GoogleDriveService $googleDriveService;
    private ExcelService $excelService;
    private GoogleSheetsService $googleSheetsService;

    public function __construct(GoogleDriveService $googleDriveService, ExcelService $excelService, GoogleSheetsService $googleSheetsService)
    {
        parent::__construct();
        $this->googleDriveService = $googleDriveService;
        $this->excelService = $excelService;
        $this->googleSheetsService = $googleSheetsService;
    }

    public function handle()
    {
        $this->info('🔍 Wyszukiwanie pliku "Kopia Wydatki" na Google Drive...');

        // Wyszukaj plik
        $files = $this->googleDriveService->searchFiles('Kopia Wydatki');
        
        if (empty($files)) {
            $this->error('❌ Nie znaleziono pliku "Kopia Wydatki" na Google Drive');
            return 1;
        }

        $this->info('✅ Znaleziono pliki:');
        foreach ($files as $file) {
            $this->line("📄 {$file['name']} (ID: {$file['id']})");
            $this->line("   Rozmiar: {$file['size']} bajtów");
            $this->line("   Typ: {$file['mime_type']}");
            $this->line("   Link: {$file['web_view_link']}");
            $this->line('');
        }

        // Sprawdź strukturę pierwszego pliku Google Sheets
        $sheetsFile = null;
        foreach ($files as $file) {
            if (strpos($file['mime_type'], 'spreadsheet') !== false) {
                $sheetsFile = $file;
                break;
            }
        }

        if (!$sheetsFile) {
            $this->error('❌ Nie znaleziono pliku Google Sheets w wynikach wyszukiwania');
            return 1;
        }

        $this->info("📊 Analizowanie struktury pliku: {$sheetsFile['name']}");

        // Wyciągnij ID arkusza z linku
        $spreadsheetId = $this->extractSpreadsheetId($sheetsFile['web_view_link']);
        if (!$spreadsheetId) {
            $this->error('❌ Nie można wyciągnąć ID arkusza z linku');
            return 1;
        }

        $this->info("📋 ID arkusza: {$spreadsheetId}");

        // Pobierz metadane Google Sheets
        $sheetsMetadata = $this->googleSheetsService->getSheetMetadata($spreadsheetId);
        if (!$sheetsMetadata) {
            $this->warn('⚠️ Nie można pobrać metadanych przez Google Sheets API, próbuję przez Google Drive API...');
            
            // Fallback: spróbuj eksportować jako Excel przez Google Drive
            $tempPath = storage_path('app/temp/' . uniqid() . '.xlsx');
            $success = $this->googleDriveService->exportSheetAsExcel($sheetsFile['id'], $tempPath);
            
            if (!$success) {
                $this->error('❌ Nie można pobrać pliku przez Google Drive API');
                return 1;
            }
            
            $this->info('✅ Pobrano plik przez Google Drive API');
            
            // Wczytaj jako Excel
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $sheetTitle = $worksheet->getTitle();
            
            $this->info("📋 Arkusz: {$sheetTitle}");
            
            // Sprawdź wszystkie arkusze
            $this->info("📋 Wszystkie arkusze:");
            foreach ($spreadsheet->getSheetNames() as $sheetName) {
                $this->line("   - {$sheetName}");
            }
            $this->line('');
            
            // Pobierz dane
            $data = [];
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            
            for ($row = 1; $row <= min(20, $highestRow); $row++) {
                $rowData = [];
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $cellValue = $worksheet->getCell($col . $row)->getValue();
                    $rowData[] = $cellValue ?? '';
                }
                $data[] = $rowData;
            }
            
            // Usuń plik tymczasowy
            unlink($tempPath);
            
        } else {
            $this->info("📋 Metadane Google Sheets:");
            $this->line("   Nazwa: {$sheetsMetadata['title']}");
            $this->line("   Liczba arkuszy: {$sheetsMetadata['sheet_count']}");
            $this->line("   Nazwy arkuszy: " . implode(', ', array_map(function($sheet) {
                return $sheet['title'];
            }, $sheetsMetadata['sheets'])));
            $this->line('');

            // Sprawdź dane z pierwszego arkusza
            $sheetTitle = $sheetsMetadata['sheets'][0]['title'] ?? null;
            if (!$sheetTitle) {
                $this->error('❌ Brak arkuszy w pliku Google Sheets');
                return 1;
            }

            $this->info("📈 Analizowanie arkusza: {$sheetTitle}");

            // Pobierz pierwsze 10 wierszy do analizy struktury
            $data = $this->googleSheetsService->getSheetDataByTitle($spreadsheetId, $sheetTitle, 'A:Z');
            if (!$data || empty($data)) {
                $this->error('❌ Nie można pobrać danych z arkusza');
                return 1;
            }

            // Ogranicz do pierwszych 10 wierszy
            $data = array_slice($data, 0, 10);
        }

        $this->info("📊 Struktura danych (pierwsze " . count($data) . " wierszy):");
        
        // Wyświetl nagłówki (pierwszy wiersz)
        if (!empty($data[0])) {
            $this->line("🏷️  Nagłówki kolumn:");
            foreach ($data[0] as $index => $header) {
                $this->line("   Kolumna " . ($index + 1) . ": '{$header}'");
            }
            $this->line('');
        }

        // Wyświetl przykładowe dane
        $this->line("📝 Przykładowe dane:");
        for ($i = 1; $i < min(6, count($data)); $i++) {
            $row = $data[$i];
            $this->line("   Wiersz " . ($i + 1) . ": " . implode(' | ', array_map(function($cell) {
                return is_null($cell) ? 'NULL' : (string)$cell;
            }, $row)));
        }

        // Sprawdź arkusz "Kredyty" jeśli istnieje
        if (isset($spreadsheet) && $spreadsheet->sheetNameExists('Kredyty')) {
            $this->info("\n📊 Analizowanie arkusza 'Kredyty':");
            $kredytySheet = $spreadsheet->getSheetByName('Kredyty');
            $highestRow = $kredytySheet->getHighestRow();
            $highestColumn = $kredytySheet->getHighestColumn();
            
            $this->line("   Liczba wierszy: {$highestRow}");
            $this->line("   Liczba kolumn: " . (ord($highestColumn) - ord('A') + 1));
            
            // Pobierz pierwsze 5 wierszy z arkusza Kredyty
            $kredytyData = [];
            for ($row = 1; $row <= min(5, $highestRow); $row++) {
                $rowData = [];
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $cellValue = $kredytySheet->getCell($col . $row)->getValue();
                    $rowData[] = $cellValue ?? '';
                }
                $kredytyData[] = $rowData;
            }
            
            if (!empty($kredytyData[0])) {
                $this->line("   Nagłówki: " . implode(', ', $kredytyData[0]));
            }
            
            for ($i = 1; $i < count($kredytyData); $i++) {
                $row = $kredytyData[$i];
                $this->line("   Wiersz " . ($i + 1) . ": " . implode(' | ', array_map(function($cell) {
                    return is_null($cell) ? 'NULL' : (string)$cell;
                }, $row)));
            }
        }

        $this->info('✅ Analiza zakończona pomyślnie!');
        return 0;
    }

    /**
     * Wyciąga ID arkusza z linku Google Sheets
     */
    private function extractSpreadsheetId(string $url): ?string
    {
        // Przykład: https://docs.google.com/spreadsheets/d/1wuehQBfb46MhgFjJAuUjnLWNbbkWMGErk4LGSNi7feM/edit?usp=drivesdk
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
} 