<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Google\GoogleDriveService;
use App\Services\Google\ExcelService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Exception;

class GoogleDriveController extends Controller
{
    private GoogleDriveService $googleDriveService;
    private ExcelService $excelService;

    public function __construct(GoogleDriveService $googleDriveService, ExcelService $excelService)
    {
        $this->googleDriveService = $googleDriveService;
        $this->excelService = $excelService;
    }

    /**
     * Testuje połączenie z Google Drive
     */
    public function testConnection(): JsonResponse
    {
        try {
            $connected = $this->googleDriveService->testConnection();
            
            return response()->json([
                'success' => $connected,
                'message' => $connected 
                    ? 'Połączenie z Google Drive działa poprawnie.' 
                    : 'Połączenie z Google Drive nie powiodło się.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd połączenia: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pobiera URL autoryzacji Google Drive
     */
    public function getAuthUrl(): JsonResponse
    {
        try {
            $authUrl = $this->googleDriveService->getAuthorizationUrl();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'auth_url' => $authUrl,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd generowania URL autoryzacji: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Wymienia kod autoryzacyjny na token
     */
    public function exchangeCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $success = $this->googleDriveService->exchangeCodeForToken($request->code);
            
            return response()->json([
                'success' => $success,
                'message' => $success 
                    ? 'Autoryzacja Google Drive zakończona pomyślnie.' 
                    : 'Autoryzacja Google Drive nie powiodła się.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd autoryzacji: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pobiera listę plików z Google Drive
     */
    public function listFiles(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['mimeType', 'name', 'parents', 'trashed', 'modifiedTime']);
            $files = $this->googleDriveService->listFiles($filters);
            
            return response()->json([
                'success' => true,
                'data' => $files,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd pobierania plików: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pobiera szczegóły pliku
     */
    public function getFile(string $fileId): JsonResponse
    {
        try {
            $file = $this->googleDriveService->getFile($fileId);
            
            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plik nie został znaleziony.',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $file,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd pobierania pliku: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pobiera pliki Excel z Google Drive
     */
    public function getExcelFiles(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['name', 'parents', 'trashed', 'modifiedTime']);
            $files = $this->googleDriveService->getExcelFiles($filters);
            
            return response()->json([
                'success' => true,
                'data' => $files,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd pobierania plików Excel: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pobiera dane z arkusza Excel
     */
    public function getExcelData(Request $request, string $fileId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sheet_name' => 'nullable|string',
            'range' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $sheetName = $request->get('sheet_name');
            $range = $request->get('range');
            
            $data = $this->excelService->getExcelData($fileId, $sheetName, $range ? ['range' => $range] : []);
            
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nie udało się pobrać danych z arkusza.',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd pobierania danych Excel: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tworzy arkusz Excel z danymi
     */
    public function createExcelFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'file_name' => 'required|string',
            'sheet_name' => 'nullable|string',
            'parent_folder_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $fileId = $this->excelService->createExcelFile(
                $request->data,
                $request->file_name,
                $request->get('sheet_name', 'Sheet1'),
                $request->get('parent_folder_id')
            );
            
            if (!$fileId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nie udało się utworzyć pliku Excel.',
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'file_id' => $fileId,
                ],
                'message' => 'Plik Excel został utworzony pomyślnie.',
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd tworzenia pliku Excel: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aktualizuje arkusz Excel
     */
    public function updateExcelFile(Request $request, string $fileId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'sheet_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $success = $this->excelService->updateExcelFile(
                $fileId,
                $request->data,
                $request->get('sheet_name', 'Sheet1')
            );
            
            return response()->json([
                'success' => $success,
                'message' => $success 
                    ? 'Arkusz Excel został zaktualizowany pomyślnie.' 
                    : 'Nie udało się zaktualizować arkusza Excel.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd aktualizacji arkusza Excel: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pobiera dane z określonego zakresu
     */
    public function getRangeData(Request $request, string $fileId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'range' => 'required|string',
            'sheet_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $this->excelService->getRangeData(
                $fileId,
                $request->range,
                $request->get('sheet_name')
            );
            
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nie udało się pobrać danych z określonego zakresu.',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd pobierania danych z zakresu: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pobiera dane z określonej kolumny
     */
    public function getColumnData(Request $request, string $fileId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'column' => 'required|string',
            'sheet_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $this->excelService->getColumnData(
                $fileId,
                $request->column,
                $request->get('sheet_name')
            );
            
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nie udało się pobrać danych z kolumny.',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd pobierania danych z kolumny: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pobiera metadane arkusza Excel
     */
    public function getExcelMetadata(string $fileId): JsonResponse
    {
        try {
            $metadata = $this->excelService->getExcelMetadata($fileId);
            
            if (!$metadata) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nie udało się pobrać metadanych arkusza.',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $metadata,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd pobierania metadanych: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pobiera statystyki użycia Google Drive
     */
    public function getUsageStats(): JsonResponse
    {
        try {
            $stats = $this->googleDriveService->getUsageStats();
            
            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd pobierania statystyk: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pobiera informacje o użytkowniku Google Drive
     */
    public function getUserInfo(): JsonResponse
    {
        try {
            $userInfo = $this->googleDriveService->getUserInfo();
            
            if (!$userInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nie udało się pobrać informacji o użytkowniku.',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $userInfo,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd pobierania informacji o użytkowniku: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Wyczyść cache tokenów
     */
    public function clearTokenCache(): JsonResponse
    {
        try {
            $this->googleDriveService->clearTokenCache();
            
            return response()->json([
                'success' => true,
                'message' => 'Cache tokenów został wyczyszczony.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd czyszczenia cache: ' . $e->getMessage(),
            ], 500);
        }
    }
} 