<?php

namespace App\Services\Google;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Exception;

class GoogleDriveService
{
    private Google_Client $client;
    private Google_Service_Drive $service;
    private string $applicationName;
    private string $credentialsPath;
    private string $tokenPath;
    private array $scopes;

    public function __construct()
    {
        $this->applicationName = config('google.application_name', 'Finances Analyzer');
        $this->credentialsPath = config('google.credentials_path');
        $this->tokenPath = config('google.token_path');
        $this->scopes = config('google.scopes', [
            'https://www.googleapis.com/auth/drive',
            'https://www.googleapis.com/auth/drive.file',
            'https://www.googleapis.com/auth/spreadsheets',
        ]);

        $this->initializeClient();
    }

    /**
     * Inicjalizuje Google Client
     */
    private function initializeClient(): void
    {
        try {
            $this->client = new Google_Client();
            $this->client->setApplicationName($this->applicationName);
            $this->client->setScopes($this->scopes);
            $this->client->setAuthConfig($this->credentialsPath);
            $this->client->setAccessType('offline');
            $this->client->setPrompt('select_account consent');

            // Load previously authorized token from cache
            $tokenArray = Cache::get('google_drive_token');
            if ($tokenArray) {
                $this->client->setAccessToken($tokenArray);
            }

            // If there is no previous token or it has expired
            if ($this->client->isAccessTokenExpired()) {
                if ($this->client->getRefreshToken()) {
                    $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                    Cache::put('google_drive_token', $this->client->getAccessToken(), 3600);
                } else {
                    throw new Exception('No refresh token available. Please authenticate first.');
                }
            }

            $this->service = new Google_Service_Drive($this->client);
        } catch (Exception $e) {
            Log::error('Google Drive client initialization failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generuje URL autoryzacji
     */
    public function getAuthorizationUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Wymienia kod autoryzacyjny na token
     */
    public function exchangeCodeForToken(string $code): bool
    {
        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);
            $this->client->setAccessToken($accessToken);

            // Cache token
            Cache::put('google_drive_token', $accessToken, 3600);

            return true;
        } catch (Exception $e) {
            Log::error('Google Drive token exchange failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Pobiera listę plików z Google Drive
     */
    public function listFiles(array $filters = []): array
    {
        try {
            $query = $this->buildQuery($filters);
            
            $results = $this->service->files->listFiles([
                'pageSize' => 50,
                'fields' => 'nextPageToken, files(id, name, mimeType, size, createdTime, modifiedTime, parents)',
                'q' => $query,
            ]);

            return $results->getFiles();
        } catch (Exception $e) {
            Log::error('Google Drive list files failed', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            return [];
        }
    }

    /**
     * Pobiera plik z Google Drive
     */
    public function getFile(string $fileId): ?array
    {
        try {
            $file = $this->service->files->get($fileId, [
                'fields' => 'id, name, mimeType, size, createdTime, modifiedTime, parents, webViewLink, webContentLink'
            ]);

            return [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'mimeType' => $file->getMimeType(),
                'size' => $file->getSize(),
                'createdTime' => $file->getCreatedTime(),
                'modifiedTime' => $file->getModifiedTime(),
                'webViewLink' => $file->getWebViewLink(),
                'webContentLink' => $file->getWebContentLink(),
            ];
        } catch (Exception $e) {
            Log::error('Google Drive get file failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);
            return null;
        }
    }

    /**
     * Pobiera zawartość pliku
     */
    public function downloadFile(string $fileId): ?string
    {
        try {
            $content = $this->service->files->get($fileId, [
                'alt' => 'media'
            ])->getBody()->getContents();

            return $content;
        } catch (Exception $e) {
            Log::error('Google Drive download file failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);
            return null;
        }
    }

    /**
     * Uploaduje plik do Google Drive
     */
    public function uploadFile(string $filePath, string $fileName, string $mimeType = null, string $parentFolderId = null): ?string
    {
        try {
            $fileMetadata = new Google_Service_Drive_DriveFile([
                'name' => $fileName,
            ]);

            if ($parentFolderId) {
                $fileMetadata->setParents([$parentFolderId]);
            }

            if (!$mimeType) {
                $mimeType = mime_content_type($filePath);
            }

            $content = file_get_contents($filePath);
            $file = $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id'
            ]);

            return $file->getId();
        } catch (Exception $e) {
            Log::error('Google Drive upload file failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
                'file_name' => $fileName,
            ]);
            return null;
        }
    }

    /**
     * Tworzy folder w Google Drive
     */
    public function createFolder(string $folderName, string $parentFolderId = null): ?string
    {
        try {
            $fileMetadata = new Google_Service_Drive_DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
            ]);

            if ($parentFolderId) {
                $fileMetadata->setParents([$parentFolderId]);
            }

            $folder = $this->service->files->create($fileMetadata, [
                'fields' => 'id'
            ]);

            return $folder->getId();
        } catch (Exception $e) {
            Log::error('Google Drive create folder failed', [
                'error' => $e->getMessage(),
                'folder_name' => $folderName,
            ]);
            return null;
        }
    }

    /**
     * Usuwa plik z Google Drive
     */
    public function deleteFile(string $fileId): bool
    {
        try {
            $this->service->files->delete($fileId);
            return true;
        } catch (Exception $e) {
            Log::error('Google Drive delete file failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);
            return false;
        }
    }

    /**
     * Aktualizuje plik w Google Drive
     */
    public function updateFile(string $fileId, string $filePath, string $mimeType = null): bool
    {
        try {
            if (!$mimeType) {
                $mimeType = mime_content_type($filePath);
            }

            $content = file_get_contents($filePath);
            $this->service->files->update($fileId, null, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Google Drive update file failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'file_path' => $filePath,
            ]);
            return false;
        }
    }

    /**
     * Pobiera pliki Excel z Google Drive
     */
    public function getExcelFiles(array $filters = []): array
    {
        $excelMimeTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
            'application/vnd.ms-excel', // .xls
            'application/vnd.google-apps.spreadsheet', // Google Sheets
        ];

        $filters['mimeType'] = implode(' or ', array_map(function($type) {
            return "mimeType='$type'";
        }, $excelMimeTypes));

        return $this->listFiles($filters);
    }

    /**
     * Pobiera pliki CSV z Google Drive
     */
    public function getCsvFiles(array $filters = []): array
    {
        $filters['mimeType'] = "mimeType='text/csv'";
        return $this->listFiles($filters);
    }

    /**
     * Pobiera pliki PDF z Google Drive
     */
    public function getPdfFiles(array $filters = []): array
    {
        $filters['mimeType'] = "mimeType='application/pdf'";
        return $this->listFiles($filters);
    }

    /**
     * Wyszukuje pliki po nazwie
     */
    public function searchFiles(string $query, array $filters = []): array
    {
        $filters['name'] = "name contains '$query'";
        return $this->listFiles($filters);
    }

    /**
     * Pobiera pliki z określonego folderu
     */
    public function getFilesFromFolder(string $folderId, array $filters = []): array
    {
        $filters['parents'] = "'$folderId' in parents";
        return $this->listFiles($filters);
    }

    /**
     * Pobiera statystyki użycia
     */
    public function getUsageStats(): array
    {
        try {
            $about = $this->service->about->get([
                'fields' => 'storageQuota'
            ]);

            $quota = $about->getStorageQuota();
            
            return [
                'total' => $quota->getLimit(),
                'used' => $quota->getUsage(),
                'available' => $quota->getLimit() - $quota->getUsage(),
                'usage_percentage' => round(($quota->getUsage() / $quota->getLimit()) * 100, 2),
            ];
        } catch (Exception $e) {
            Log::error('Google Drive usage stats failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Testuje połączenie z Google Drive
     */
    public function testConnection(): bool
    {
        try {
            $this->service->about->get(['fields' => 'user']);
            return true;
        } catch (Exception $e) {
            Log::error('Google Drive connection test failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Buduje zapytanie do Google Drive API
     */
    private function buildQuery(array $filters): string
    {
        $conditions = [];

        foreach ($filters as $key => $value) {
            switch ($key) {
                case 'mimeType':
                    $conditions[] = $value;
                    break;
                case 'name':
                    $conditions[] = $value;
                    break;
                case 'parents':
                    $conditions[] = $value;
                    break;
                case 'trashed':
                    $conditions[] = "trashed=$value";
                    break;
                case 'modifiedTime':
                    $conditions[] = "modifiedTime > '$value'";
                    break;
            }
        }

        return implode(' and ', $conditions);
    }

    /**
     * Wyczyść cache tokenów
     */
    public function clearTokenCache(): void
    {
        Cache::forget('google_drive_token');
    }

    /**
     * Pobiera informacje o użytkowniku
     */
    public function getUserInfo(): ?array
    {
        try {
            $about = $this->service->about->get([
                'fields' => 'user'
            ]);

            $user = $about->getUser();
            
            return [
                'id' => $user->getId(),
                'email' => $user->getEmailAddress(),
                'name' => $user->getDisplayName(),
                'photo' => $user->getPhotoLink(),
            ];
        } catch (Exception $e) {
            Log::error('Google Drive get user info failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
} 