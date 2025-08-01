<?php

namespace App\Services\Google;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ExcelService
{
    private GoogleDriveService $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }

    /**
     * Pobiera dane z arkusza Excel z Google Drive
     */
    public function getExcelData(string $fileId, string $sheetName = null, array $range = []): ?array
    {
        try {
            // Pobierz plik z Google Drive
            $tempPath = storage_path('app/temp/' . uniqid() . '.xlsx');
            Storage::makeDirectory('temp');
            
            $success = $this->googleDriveService->downloadFile($fileId, $tempPath);
            
            if (!$success) {
                throw new Exception('Nie udało się pobrać pliku z Google Drive');
            }

            // Wczytaj arkusz
            $spreadsheet = IOFactory::load($tempPath);
            
            // Wybierz arkusz
            if ($sheetName) {
                $worksheet = $spreadsheet->getSheetByName($sheetName);
                if (!$worksheet) {
                    throw new Exception("Arkusz '$sheetName' nie został znaleziony");
                }
            } else {
                $worksheet = $spreadsheet->getActiveSheet();
            }

            // Pobierz dane
            $data = $this->extractDataFromWorksheet($worksheet, $range);

            // Usuń tymczasowy plik
            unlink($tempPath);

            return $data;
        } catch (Exception $e) {
            Log::error('Excel data extraction failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'sheet_name' => $sheetName,
            ]);
            return null;
        }
    }

    /**
     * Tworzy arkusz Excel z danymi i uploaduje do Google Drive
     */
    public function createExcelFile(array $data, string $fileName, string $sheetName = 'Sheet1', string $parentFolderId = null): ?string
    {
        try {
            $spreadsheet = new Spreadsheet();
            $worksheet = $spreadsheet->getActiveSheet();
            $worksheet->setTitle($sheetName);

            // Dodaj dane do arkusza
            $this->addDataToWorksheet($worksheet, $data);

            // Zapisz plik tymczasowo
            $tempPath = storage_path('app/temp/' . uniqid() . '.xlsx');
            Storage::makeDirectory('temp');
            
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempPath);

            // Upload do Google Drive
            $uploadResult = $this->googleDriveService->uploadFile($tempPath, $fileName, $parentFolderId);

            // Usuń tymczasowy plik
            unlink($tempPath);

            if ($uploadResult === null) {
                return null;
            }

            return $uploadResult['id'];
        } catch (Exception $e) {
            Log::error('Excel file creation failed', [
                'error' => $e->getMessage(),
                'file_name' => $fileName,
            ]);
            return null;
        }
    }

    /**
     * Aktualizuje arkusz Excel w Google Drive
     */
    public function updateExcelFile(string $fileId, array $data, string $sheetName = 'Sheet1'): bool
    {
        try {
            // Pobierz aktualny plik
            $tempPath = storage_path('app/temp/' . uniqid() . '.xlsx');
            Storage::makeDirectory('temp');
            
            $success = $this->googleDriveService->downloadFile($fileId, $tempPath);
            
            if (!$success) {
                throw new Exception('Nie udało się pobrać pliku z Google Drive');
            }

            // Wczytaj arkusz
            $spreadsheet = IOFactory::load($tempPath);
            
            // Wybierz arkusz
            $worksheet = $spreadsheet->getSheetByName($sheetName);
            if (!$worksheet) {
                $worksheet = $spreadsheet->createSheet();
                $worksheet->setTitle($sheetName);
            }

            // Wyczyść arkusz i dodaj nowe dane
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $worksheet->removeRow(1, $highestRow);
            $this->addDataToWorksheet($worksheet, $data);

            // Zapisz plik
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempPath);

            // Aktualizuj w Google Drive
            $success = $this->googleDriveService->updateFile($fileId, $tempPath);

            // Usuń tymczasowy plik
            unlink($tempPath);

            return $success;
        } catch (Exception $e) {
            Log::error('Excel file update failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);
            return false;
        }
    }

    /**
     * Pobiera dane z arkusza Excel jako CSV
     */
    public function getExcelAsCsv(string $fileId, string $sheetName = null): ?string
    {
        try {
            $data = $this->getExcelData($fileId, $sheetName);
            
            if (!$data) {
                return null;
            }

            // Konwertuj dane na CSV
            $csvContent = '';
            foreach ($data as $row) {
                $csvContent .= implode(',', array_map(function($cell) {
                    return '"' . str_replace('"', '""', $cell) . '"';
                }, $row)) . "\n";
            }

            return $csvContent;
        } catch (Exception $e) {
            Log::error('Excel to CSV conversion failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);
            return null;
        }
    }

    /**
     * Pobiera listę arkuszy z pliku Excel
     */
    public function getSheetNames(string $fileId): array
    {
        try {
            $tempPath = storage_path('app/temp/' . uniqid() . '.xlsx');
            Storage::makeDirectory('temp');
            
            $success = $this->googleDriveService->downloadFile($fileId, $tempPath);
            
            if (!$success) {
                return [];
            }

            $spreadsheet = IOFactory::load($tempPath);
            $sheetNames = $spreadsheet->getSheetNames();

            unlink($tempPath);

            return $sheetNames;
        } catch (Exception $e) {
            Log::error('Get sheet names failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);
            return [];
        }
    }

    /**
     * Pobiera metadane arkusza Excel
     */
    public function getExcelMetadata(string $fileId): ?array
    {
        try {
            $fileInfo = $this->googleDriveService->getFileMetadata($fileId);
            
            if (!$fileInfo) {
                return null;
            }

            $sheetNames = $this->getSheetNames($fileId);
            
            return [
                'id' => $fileInfo['id'],
                'name' => $fileInfo['name'],
                'size' => $fileInfo['size'],
                'created_time' => $fileInfo['created_time'],
                'modified_time' => $fileInfo['modified_time'],
                'sheet_names' => $sheetNames,
                'sheet_count' => count($sheetNames),
            ];
        } catch (Exception $e) {
            Log::error('Get Excel metadata failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);
            return null;
        }
    }

    /**
     * Wyciąga dane z arkusza
     */
    private function extractDataFromWorksheet($worksheet, array $range = []): array
    {
        $data = [];
        
        if (!empty($range)) {
            $startRow = $range['start_row'] ?? 1;
            $endRow = $range['end_row'] ?? $worksheet->getHighestRow();
            $startCol = $range['start_col'] ?? 'A';
            $endCol = $range['end_col'] ?? $worksheet->getHighestColumn();
        } else {
            $startRow = 1;
            $endRow = $worksheet->getHighestRow();
            $startCol = 'A';
            $endCol = $worksheet->getHighestColumn();
        }

        for ($row = $startRow; $row <= $endRow; $row++) {
            $rowData = [];
            for ($col = $startCol; $col <= $endCol; $col++) {
                $cellValue = $worksheet->getCell($col . $row)->getValue();
                $rowData[] = $cellValue ?? '';
            }
            $data[] = $rowData;
        }

        return $data;
    }

    /**
     * Dodaje dane do arkusza
     */
    private function addDataToWorksheet($worksheet, array $data): void
    {
        $row = 1;
        foreach ($data as $rowData) {
            $col = 'A';
            foreach ($rowData as $cellValue) {
                $worksheet->setCellValue($col . $row, $cellValue);
                $col++;
            }
            $row++;
        }
    }

    /**
     * Pobiera dane z określonego zakresu
     */
    public function getRangeData(string $fileId, string $range, string $sheetName = null): ?array
    {
        try {
            // Parsuj zakres (np. "A1:C10")
            if (preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $range, $matches)) {
                $startCol = $matches[1];
                $startRow = (int)$matches[2];
                $endCol = $matches[3];
                $endRow = (int)$matches[4];
                
                $rangeArray = [
                    'start_col' => $startCol,
                    'start_row' => $startRow,
                    'end_col' => $endCol,
                    'end_row' => $endRow,
                ];
                
                return $this->getExcelData($fileId, $sheetName, $rangeArray);
            }
            
            throw new Exception('Nieprawidłowy format zakresu');
        } catch (Exception $e) {
            Log::error('Get range data failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'range' => $range,
            ]);
            return null;
        }
    }

    /**
     * Pobiera dane z określonej kolumny
     */
    public function getColumnData(string $fileId, string $column, string $sheetName = null): ?array
    {
        try {
            $data = $this->getExcelData($fileId, $sheetName);
            
            if (!$data) {
                return null;
            }

            $columnIndex = $this->columnToIndex($column);
            $columnData = [];
            
            foreach ($data as $row) {
                if (isset($row[$columnIndex])) {
                    $columnData[] = $row[$columnIndex];
                }
            }

            return $columnData;
        } catch (Exception $e) {
            Log::error('Get column data failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'column' => $column,
            ]);
            return null;
        }
    }

    /**
     * Konwertuje literę kolumny na indeks
     */
    private function columnToIndex(string $column): int
    {
        $index = 0;
        $length = strlen($column);
        
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
        }
        
        return $index - 1;
    }

    /**
     * Importuje transakcje z arkusza Excel z Google Drive
     */
    public function importTransactionsFromSheet(string $fileId, \App\Models\User $user, string $sheetName = null): array
    {
        try {
            // Pobierz dane z arkusza
            $data = $this->getExcelData($fileId, $sheetName);
            
            if (!$data) {
                throw new Exception('Nie udało się pobrać danych z arkusza');
            }

            // Znajdź nagłówki
            $headers = array_map('strtolower', $data[0]);
            $dateIndex = array_search('data', $headers);
            $descriptionIndex = array_search('opis', $headers);
            $amountIndex = array_search('kwota', $headers);
            $categoryIndex = array_search('kategoria', $headers);

            if ($dateIndex === false || $descriptionIndex === false || $amountIndex === false) {
                throw new Exception('Brak wymaganych kolumn w arkuszu (Data, Opis, Kwota)');
            }

            $importedCount = 0;
            $errors = [];

            // Rozpocznij transakcję
            \Illuminate\Support\Facades\DB::beginTransaction();

            // Iteruj po wierszach (pomijając nagłówki)
            for ($i = 1; $i < count($data); $i++) {
                try {
                    $row = $data[$i];
                    
                    // Sprawdź czy wiersz nie jest pusty
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    // Waliduj i formatuj datę
                    $date = $this->parseDate($row[$dateIndex]);
                    if (!$date) {
                        throw new Exception("Nieprawidłowy format daty: {$row[$dateIndex]}");
                    }

                    // Waliduj i formatuj kwotę
                    $amount = $this->parseAmount($row[$amountIndex]);
                    if ($amount === null) {
                        throw new Exception("Nieprawidłowy format kwoty: {$row[$amountIndex]}");
                    }

                    // Przygotuj dane transakcji
                    $transactionData = [
                        'user_id' => $user->id,
                        'description' => trim($row[$descriptionIndex]),
                        'amount' => $amount,
                        'currency' => 'PLN', // Domyślna waluta
                        'transaction_date' => $date,
                        'type' => $amount >= 0 ? 'credit' : 'debit',
                        'status' => 'completed',
                        'is_imported' => true
                    ];

                    // Jeśli jest kolumna z kategorią, znajdź lub utwórz kategorię
                    if ($categoryIndex !== false && !empty($row[$categoryIndex])) {
                        $categoryName = trim($row[$categoryIndex]);
                        $category = \App\Models\Category::firstOrCreate(
                            ['name' => $categoryName, 'user_id' => $user->id],
                            [
                                'name' => $categoryName,
                                'user_id' => $user->id,
                                'is_active' => true,
                            ]
                        );
                        $transactionData['category_id'] = $category->id;
                    }

                    // Sprawdź czy transakcja już istnieje
                    $exists = \App\Models\Transaction::where('user_id', $user->id)
                        ->where('description', $transactionData['description'])
                        ->where('amount', $transactionData['amount'])
                        ->where('transaction_date', $transactionData['transaction_date'])
                        ->exists();

                    if (!$exists) {
                        \App\Models\Transaction::create($transactionData);
                        $importedCount++;
                    }

                } catch (Exception $e) {
                    $errors[] = "Błąd w wierszu " . ($i + 1) . ": " . $e->getMessage();
                }
            }

            // Zatwierdź transakcję jeśli nie było błędów lub były tylko pojedyncze błędy
            if (empty($errors) || $importedCount > 0) {
                \Illuminate\Support\Facades\DB::commit();
                return [
                    'success' => true,
                    'imported_count' => $importedCount,
                    'errors' => $errors
                ];
            } else {
                \Illuminate\Support\Facades\DB::rollBack();
                return [
                    'success' => false,
                    'imported_count' => 0,
                    'errors' => $errors
                ];
            }

        } catch (Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            
            Log::error('Import transakcji z arkusza nie powiódł się', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);
            
            return [
                'success' => false,
                'imported_count' => 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Parsuje datę z różnych formatów
     */
    private function parseDate($dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }

        $dateString = trim($dateString);
        $formats = [
            'Y-m-d',
            'd.m.Y',
            'd/m/Y',
            'Y/m/d',
            'd-m-Y',
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Parsuje kwotę z różnych formatów
     */
    private function parseAmount($amountString): ?float
    {
        if (empty($amountString)) {
            return null;
        }

        // Usuń spacje i zamień przecinki na kropki
        $amountString = str_replace([' ', ','], ['', '.'], trim($amountString));
        
        // Usuń wszystkie znaki oprócz cyfr, kropki i minusa
        $amountString = preg_replace('/[^0-9.-]/', '', $amountString);
        
        if (is_numeric($amountString)) {
            return (float)$amountString;
        }

        return null;
    }

    /**
     * Pobiera dane z określonego wiersza
     */
    public function getRowData(string $fileId, int $rowNumber, string $sheetName = null): ?array
    {
        try {
            $data = $this->getExcelData($fileId, $sheetName);
            
            if (!$data || !isset($data[$rowNumber - 1])) {
                return null;
            }

            return $data[$rowNumber - 1];
        } catch (Exception $e) {
            Log::error('Get row data failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'row_number' => $rowNumber,
            ]);
            return null;
        }
    }
} 