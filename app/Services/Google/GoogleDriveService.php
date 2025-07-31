<?php

namespace App\Services\Google;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleDriveService
{
    /** @var Client */
    private $client;
    
    /** @var Drive */
    private $service;
    
    private string $applicationName;
    private array $scopes;
    private ?string $credentialsPath;
    private string $tokenPath;

    /**
     * @throws \Google\Exception
     */
    public function __construct()
    {
        $this->applicationName = config('google.drive.application_name', 'Finances App');
        $this->scopes = config('google.drive.scopes', [
            'https://www.googleapis.com/auth/drive',
            'https://www.googleapis.com/auth/drive.file',
        ]);
        
        // Użyj credentials z konfiguracji zamiast pliku
        $this->credentialsPath = null; // Będziemy używać credentials z config
        $this->tokenPath = storage_path('app/google_token.json');

        $this->initializeClient();
    }

    /**
     * @return void
     * @throws \Google\Exception
     */
    private function initializeClient(): void
    {
        try {
            $this->client = new Client();
            $this->client->setApplicationName($this->applicationName);
            $this->client->setScopes($this->scopes);
            
            // Użyj credentials z konfiguracji
            $credentials = config('google.credentials');
            $credentialsType = config('google.credentials.type', 'service_account');
            
            if ($credentials && $credentialsType === 'service_account') {
                // Service Account flow
                $this->client->setAuthConfig($credentials);
                
                // For service account, we don't need to handle token refresh manually
                // The client will handle it automatically
            } else {
                // OAuth flow
                $this->client->setClientId(config('google.drive.client_id'));
                $this->client->setClientSecret(config('google.drive.client_secret'));
                $this->client->setRedirectUri(config('google.drive.redirect_uri'));
                $this->client->setAccessType('offline');
                $this->client->setPrompt('select_account consent');

                // Load previously authorized token from a cache
                if (file_exists($this->tokenPath)) {
                    $tokenContent = file_get_contents($this->tokenPath);
                    if ($tokenContent !== false) {
                        $accessToken = json_decode($tokenContent, true);
                        if ($accessToken !== null) {
                            $this->client->setAccessToken($accessToken);
                        }
                    }
                }

                // If there is no previous token or it has expired
                if ($this->client->isAccessTokenExpired()) {
                    if ($this->client->getRefreshToken()) {
                        $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                    } else {
                        // Request authorization from the user
                        $authUrl = $this->client->createAuthUrl();
                        Log::info('Google Drive authorization required', ['auth_url' => $authUrl]);
                        throw new \Exception('Google Drive authorization required. Please visit: ' . $authUrl);
                    }

                    // Save the token to a file
                    if (!is_dir(dirname($this->tokenPath))) {
                        mkdir(dirname($this->tokenPath), 0700, true);
                    }
                    file_put_contents($this->tokenPath, json_encode($this->client->getAccessToken()));
                }
            }

            $this->service = new Drive($this->client);

        } catch (\Exception $e) {
            Log::error('Google Drive client initialization failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get authorization URL for OAuth 2.0 flow
     */
    public function getAuthorizationUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(string $code): bool
    {
        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);
            $this->client->setAccessToken($accessToken);

            // Save the token to a file
            if (!is_dir(dirname($this->tokenPath))) {
                mkdir(dirname($this->tokenPath), 0700, true);
            }
            file_put_contents($this->tokenPath, json_encode($this->client->getAccessToken()));

            return true;

        } catch (\Exception $e) {
            Log::error('Google Drive token exchange failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Test connection to Google Drive API
     */
    public function testConnection(): bool
    {
        try {
            $about = $this->service->about->get(['fields' => 'user,storageQuota']);
            return $about !== null;
        } catch (\Exception $e) {
            Log::error('Google Drive connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get user info
     */
    public function getUserInfo(): ?array
    {
        try {
            $about = $this->service->about->get(['fields' => 'user']);
            $user = $about->getUser();
            
            return [
                'id' => $user->getPermissionId() ?? null,
                'email' => $user->getEmailAddress() ?? null,
                'name' => $user->getDisplayName() ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Google Drive get user info failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get storage usage statistics
     */
    public function getStorageUsage(): ?array
    {
        try {
            $about = $this->service->about->get(['fields' => 'storageQuota']);
            $quota = $about->getStorageQuota();

            return [
                'total' => $quota->getLimit(),
                'used' => $quota->getUsage(),
                'available' => $quota->getLimit() - $quota->getUsage(),
            ];
        } catch (\Exception $e) {
            Log::error('Google Drive get storage usage failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Upload file to Google Drive
     */
    public function uploadFile(string $filePath, string $fileName, ?string $parentFolderId = null): ?array
    {
        try {
            $fileMetadata = new DriveFile([
                'name' => $fileName,
            ]);

            if ($parentFolderId) {
                $fileMetadata->setParents([$parentFolderId]);
            }

            $content = file_get_contents($filePath);
            
            // Spróbuj użyć Shared Drive jeśli jest skonfigurowany
            $sharedDriveId = config('google.drive.shared_drive_id');
            $params = [
                'data' => $content,
                'mimeType' => mime_content_type($filePath),
                'uploadType' => 'multipart',
                'fields' => 'id,name,size,createdTime,modifiedTime,webViewLink'
            ];
            
            if ($sharedDriveId) {
                $params['supportsAllDrives'] = true;
                $params['includeItemsFromAllDrives'] = true;
                $params['corpora'] = 'allDrives';
            }
            
            $file = $this->service->files->create($fileMetadata, $params);

            return [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'size' => $file->getSize(),
                'created_time' => $file->getCreatedTime(),
                'modified_time' => $file->getModifiedTime(),
                'web_view_link' => $file->getWebViewLink(),
            ];

        } catch (\Exception $e) {
            Log::error('Google Drive upload file failed', [
                'file_path' => $filePath,
                'file_name' => $fileName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Download file from Google Drive
     */
    public function downloadFile(string $fileId, string $destinationPath): bool
    {
        try {
            // Pobierz metadane pliku najpierw
            $file = $this->service->files->get($fileId);
            
            // Sprawdź czy plik nie jest folderem
            if ($file->getMimeType() === 'application/vnd.google-apps.folder') {
                Log::error('Google Drive download file failed - cannot download folder', [
                    'file_id' => $fileId,
                    'mime_type' => $file->getMimeType()
                ]);
                return false;
            }
            
            // Sprawdź czy plik nie jest Google Docs/Sheets/Slides
            if (strpos($file->getMimeType(), 'application/vnd.google-apps') === 0) {
                Log::error('Google Drive download file failed - cannot download Google Apps files directly', [
                    'file_id' => $fileId,
                    'mime_type' => $file->getMimeType()
                ]);
                return false;
            }
            
            // Pobierz zawartość pliku
            $content = $this->service->files->get($fileId, [
                'alt' => 'media'
            ]);

            // Sprawdź czy zawartość nie jest pusta
            if (empty($content)) {
                Log::error('Google Drive download file failed - empty content', [
                    'file_id' => $fileId,
                    'file_size' => $file->getSize()
                ]);
                return false;
            }

            // Zapisz do pliku
            $result = file_put_contents($destinationPath, $content);
            
            if ($result === false) {
                Log::error('Google Drive download file failed - cannot write to destination', [
                    'file_id' => $fileId,
                    'destination_path' => $destinationPath
                ]);
                return false;
            }

            // Sprawdź czy plik został zapisany poprawnie
            if (!file_exists($destinationPath) || filesize($destinationPath) === 0) {
                Log::error('Google Drive download file failed - file not written or empty', [
                    'file_id' => $fileId,
                    'destination_path' => $destinationPath,
                    'file_exists' => file_exists($destinationPath),
                    'file_size' => file_exists($destinationPath) ? filesize($destinationPath) : 'N/A'
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Google Drive download file failed', [
                'file_id' => $fileId,
                'destination_path' => $destinationPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete file from Google Drive
     */
    public function deleteFile(string $fileId): bool
    {
        try {
            $this->service->files->delete($fileId);
            return true;

        } catch (\Exception $e) {
            Log::error('Google Drive delete file failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update file in Google Drive
     */
    public function updateFile(string $fileId, string $filePath): bool
    {
        try {
            $content = file_get_contents($filePath);
            $fileMetadata = new DriveFile();
            
            $this->service->files->update($fileId, $fileMetadata, [
                'data' => $content,
                'mimeType' => mime_content_type($filePath),
                'uploadType' => 'multipart'
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Google Drive update file failed', [
                'file_id' => $fileId,
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create folder in Google Drive
     */
    public function createFolder(string $folderName, ?string $parentFolderId = null): ?array
    {
        try {
            $fileMetadata = new DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
            ]);

            if ($parentFolderId) {
                $fileMetadata->setParents([$parentFolderId]);
            }

            $folder = $this->service->files->create($fileMetadata, [
                'fields' => 'id,name,createdTime,webViewLink'
            ]);

            return [
                'id' => $folder->getId(),
                'name' => $folder->getName(),
                'created_time' => $folder->getCreatedTime(),
                'web_view_link' => $folder->getWebViewLink(),
            ];

        } catch (\Exception $e) {
            Log::error('Google Drive create folder failed', [
                'folder_name' => $folderName,
                'parent_folder_id' => $parentFolderId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * List files in Google Drive
     */
    public function listFiles(?string $folderId = null, int $pageSize = 10): array
    {
        try {
            $query = "trashed = false";
            if ($folderId) {
                $query .= " and '$folderId' in parents";
            }

            $results = $this->service->files->listFiles([
                'pageSize' => $pageSize,
                'fields' => 'nextPageToken, files(id, name, mimeType, size, createdTime, modifiedTime, webViewLink)',
                'q' => $query,
                'orderBy' => 'modifiedTime desc'
            ]);

            $files = [];
            foreach ($results->getFiles() as $file) {
                $files[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'created_time' => $file->getCreatedTime(),
                    'modified_time' => $file->getModifiedTime(),
                    'web_view_link' => $file->getWebViewLink(),
                ];
            }

            return $files;

        } catch (\Exception $e) {
            Log::error('Google Drive list files failed', [
                'folder_id' => $folderId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Search files in Google Drive
     */
    public function searchFiles(string $query, int $pageSize = 10): array
    {
        try {
            $results = $this->service->files->listFiles([
                'pageSize' => $pageSize,
                'fields' => 'nextPageToken, files(id, name, mimeType, size, createdTime, modifiedTime, webViewLink)',
                'q' => "name contains '$query' and trashed = false",
                'orderBy' => 'modifiedTime desc'
            ]);

            $files = [];
            foreach ($results->getFiles() as $file) {
                $files[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'created_time' => $file->getCreatedTime(),
                    'modified_time' => $file->getModifiedTime(),
                    'web_view_link' => $file->getWebViewLink(),
                ];
            }

            return $files;

        } catch (\Exception $e) {
            Log::error('Google Drive search files failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get file metadata
     */
    public function getFileMetadata(string $fileId): ?array
    {
        try {
            $file = $this->service->files->get($fileId, [
                'fields' => 'id,name,mimeType,size,createdTime,modifiedTime,webViewLink,parents'
            ]);

            return [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'created_time' => $file->getCreatedTime(),
                'modified_time' => $file->getModifiedTime(),
                'web_view_link' => $file->getWebViewLink(),
                'parents' => $file->getParents(),
            ];

        } catch (\Exception $e) {
            Log::error('Google Drive get file metadata failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Share file with specific permissions
     */
    public function shareFile(string $fileId, string $email, string $role = 'reader'): bool
    {
        try {
            $permission = new Permission([
                'type' => 'user',
                'role' => $role,
                'emailAddress' => $email,
            ]);

            $this->service->permissions->create($fileId, $permission);

            return true;

        } catch (\Exception $e) {
            Log::error('Google Drive share file failed', [
                'file_id' => $fileId,
                'email' => $email,
                'role' => $role,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get file permissions
     */
    public function getFilePermissions(string $fileId): array
    {
        try {
            $permissions = $this->service->permissions->listPermissions($fileId);

            $result = [];
            foreach ($permissions->getPermissions() as $permission) {
                $result[] = [
                    'id' => $permission->getId(),
                    'type' => $permission->getType(),
                    'role' => $permission->getRole(),
                    'email_address' => $permission->getEmailAddress(),
                    'display_name' => $permission->getDisplayName(),
                ];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Google Drive get file permissions failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
