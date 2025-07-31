<?php

namespace App\Services\Google;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Facades\Log;
use Exception;

class GoogleSheetsService
{
    private Client $client;
    private Sheets $service;

    public function __construct()
    {
        $this->initializeClient();
    }

    private function initializeClient(): void
    {
        try {
            $this->client = new Client();
            $this->client->setApplicationName(config('google.drive.application_name', 'Finances App'));
            $this->client->setScopes([
                'https://www.googleapis.com/auth/drive',
                'https://www.googleapis.com/auth/drive.file',
                'https://www.googleapis.com/auth/spreadsheets',
            ]);

            // Użyj credentials z konfiguracji
            $credentials = config('google.credentials');
            $credentialsType = config('google.credentials.type', 'service_account');
            
            if ($credentials && $credentialsType === 'service_account') {
                // Service Account flow
                $this->client->setAuthConfig($credentials);
            } else {
                // OAuth flow
                $this->client->setClientId(config('google.drive.client_id'));
                $this->client->setClientSecret(config('google.drive.client_secret'));
                $this->client->setRedirectUri(config('google.drive.redirect_uri'));
                $this->client->setAccessType('offline');
                $this->client->setPrompt('select_account consent');
            }

            $this->service = new Sheets($this->client);

        } catch (Exception $e) {
            Log::error('Google Sheets client initialization failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Pobiera dane z arkusza Google Sheets
     */
    public function getSheetData(string $spreadsheetId, string $range = 'A:Z'): ?array
    {
        try {
            $response = $this->service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues();

            if (!$values) {
                return [];
            }

            return $values;

        } catch (Exception $e) {
            Log::error('Google Sheets get data failed', [
                'spreadsheet_id' => $spreadsheetId,
                'range' => $range,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Pobiera metadane arkusza
     */
    public function getSheetMetadata(string $spreadsheetId): ?array
    {
        try {
            $response = $this->service->spreadsheets->get($spreadsheetId);
            
            $sheets = [];
            foreach ($response->getSheets() as $sheet) {
                $properties = $sheet->getProperties();
                $sheets[] = [
                    'id' => $properties->getSheetId(),
                    'title' => $properties->getTitle(),
                    'index' => $properties->getIndex(),
                    'grid_properties' => [
                        'row_count' => $properties->getGridProperties()->getRowCount(),
                        'column_count' => $properties->getGridProperties()->getColumnCount(),
                    ]
                ];
            }

            return [
                'spreadsheet_id' => $spreadsheetId,
                'title' => $response->getProperties()->getTitle(),
                'sheets' => $sheets,
                'sheet_count' => count($sheets),
            ];

        } catch (Exception $e) {
            Log::error('Google Sheets get metadata failed', [
                'spreadsheet_id' => $spreadsheetId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Pobiera dane z konkretnego arkusza
     */
    public function getSheetDataByTitle(string $spreadsheetId, string $sheetTitle, string $range = 'A:Z'): ?array
    {
        try {
            $fullRange = $sheetTitle . '!' . $range;
            return $this->getSheetData($spreadsheetId, $fullRange);

        } catch (Exception $e) {
            Log::error('Google Sheets get data by title failed', [
                'spreadsheet_id' => $spreadsheetId,
                'sheet_title' => $sheetTitle,
                'range' => $range,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Pobiera nagłówki (pierwszy wiersz) z arkusza
     */
    public function getHeaders(string $spreadsheetId, string $sheetTitle = null): ?array
    {
        try {
            $range = $sheetTitle ? $sheetTitle . '!A1:Z1' : 'A1:Z1';
            $data = $this->getSheetData($spreadsheetId, $range);
            
            if (!$data || empty($data)) {
                return null;
            }

            return $data[0];

        } catch (Exception $e) {
            Log::error('Google Sheets get headers failed', [
                'spreadsheet_id' => $spreadsheetId,
                'sheet_title' => $sheetTitle,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Pobiera dane z określonego zakresu
     */
    public function getRangeData(string $spreadsheetId, string $range, string $sheetTitle = null): ?array
    {
        try {
            $fullRange = $sheetTitle ? $sheetTitle . '!' . $range : $range;
            return $this->getSheetData($spreadsheetId, $fullRange);

        } catch (Exception $e) {
            Log::error('Google Sheets get range data failed', [
                'spreadsheet_id' => $spreadsheetId,
                'range' => $range,
                'sheet_title' => $sheetTitle,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Sprawdza czy arkusz istnieje
     */
    public function sheetExists(string $spreadsheetId, string $sheetTitle): bool
    {
        try {
            $metadata = $this->getSheetMetadata($spreadsheetId);
            if (!$metadata) {
                return false;
            }

            foreach ($metadata['sheets'] as $sheet) {
                if ($sheet['title'] === $sheetTitle) {
                    return true;
                }
            }

            return false;

        } catch (Exception $e) {
            Log::error('Google Sheets check sheet exists failed', [
                'spreadsheet_id' => $spreadsheetId,
                'sheet_title' => $sheetTitle,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
} 