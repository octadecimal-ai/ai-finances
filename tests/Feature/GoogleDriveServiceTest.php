<?php

namespace Tests\Feature;

use App\Services\Google\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleDriveServiceTest extends TestCase
{
    use RefreshDatabase;

    private GoogleDriveService $googleDriveService;

    protected function setUp(): void
    {
        parent::setUp();
        
        try {
            $this->googleDriveService = new GoogleDriveService();
        } catch (\Exception $e) {
            // Jeśli OAuth wymaga autoryzacji, to jest normalne
            if (strpos($e->getMessage(), 'Google Drive authorization required') !== false) {
                $this->markTestSkipped('OAuth wymaga autoryzacji - to jest normalne dla OAuth flow');
            }
            throw $e;
        }
    }

    /**
     * Test sprawdzający podstawowe funkcjonalności Google Drive API
     * oraz upload plików (jeśli dostępny OAuth)
     */
    public function test_basic_google_drive_functionality()
    {
        // Test połączenia z Google Drive
        $this->assertTrue(
            $this->googleDriveService->testConnection(),
            'Nie można połączyć się z Google Drive API'
        );

        // Pobierz informacje o użytkowniku
        $userInfo = $this->googleDriveService->getUserInfo();
        $this->assertNotNull($userInfo, 'Nie można pobrać informacji o użytkowniku');
        $this->assertArrayHasKey('email', $userInfo);
        $this->assertArrayHasKey('name', $userInfo);

        echo "✓ Połączenie z Google Drive: OK\n";
        echo "✓ Użytkownik: {$userInfo['name']} ({$userInfo['email']})\n";

        // Sprawdź użycie przestrzeni dyskowej
        $storageUsage = $this->googleDriveService->getStorageUsage();
        $this->assertNotNull($storageUsage, 'Nie można pobrać informacji o przestrzeni dyskowej');
        $this->assertArrayHasKey('total', $storageUsage);
        $this->assertArrayHasKey('used', $storageUsage);

        echo "✓ Przestrzeń dyskowa: {$storageUsage['used']} / {$storageUsage['total']} bajtów\n";

        // Utwórz folder testowy
        $folderName = 'Test_Finances_' . date('Y-m-d_H-i-s');
        $folder = $this->googleDriveService->createFolder($folderName, null);
        $this->assertNotNull($folder, 'Nie można utworzyć folderu testowego');
        $this->assertArrayHasKey('id', $folder);

        echo "✓ Utworzono folder: {$folder['name']} (ID: {$folder['id']})\n";

        // Pobierz metadane folderu
        $folderMetadata = $this->googleDriveService->getFileMetadata($folder['id']);
        $this->assertNotNull($folderMetadata, 'Nie można pobrać metadanych folderu');
        $this->assertEquals($folderName, $folderMetadata['name']);

        echo "✓ Metadane folderu: OK\n";

        // Lista plików w folderze (powinna być pusta)
        $files = $this->googleDriveService->listFiles($folder['id']);
        $this->assertIsArray($files, 'Lista plików powinna być tablicą');
        $this->assertCount(0, $files, 'Nowy folder powinien być pusty');

        echo "✓ Lista plików w folderze: " . count($files) . " plików\n";

        // Wyszukaj folder
        $searchResults = $this->googleDriveService->searchFiles('Test_Finances');
        $this->assertIsArray($searchResults, 'Wyniki wyszukiwania powinny być tablicą');

        echo "✓ Wyszukiwanie folderów: znaleziono " . count($searchResults) . " elementów\n";

        // Wyświetl link do folderu
        echo "✓ Link do folderu: {$folder['web_view_link']}\n";

        // Test uploadu pliku (jeśli OAuth jest dostępny)
        $this->test_file_upload($folder['id']);

        echo "\n🎉 Test podstawowych funkcjonalności GoogleDriveService zakończony sukcesem!\n";
        echo "Połączenie, tworzenie folderów, pobieranie metadanych działają poprawnie.\n";
    }

    /**
     * Test uploadu pliku (tylko jeśli OAuth jest dostępny)
     */
    private function test_file_upload(?string $folderId = null): void
    {
        try {
            // Utwórz prosty plik CSV z danymi testowymi
            $testData = [
                ['Data', 'Opis', 'Kwota', 'Kategoria'],
                ['2025-07-31', 'Zakupy spożywcze', '150.50', 'Żywność'],
                ['2025-07-31', 'Benzyna', '200.00', 'Transport'],
                ['2025-07-31', 'Kino', '45.00', 'Rozrywka'],
            ];

            $csvContent = '';
            foreach ($testData as $row) {
                $csvContent .= implode(',', $row) . "\n";
            }

            // Zapisz plik CSV tymczasowo
            $tempFile = tempnam(sys_get_temp_dir(), 'test_finances_');
            file_put_contents($tempFile, $csvContent);

            // Upload pliku do Google Drive
            $fileName = 'test_transactions_' . date('Y-m-d_H-i-s') . '.csv';
            
            $this->assertFileExists($tempFile, 'Plik tymczasowy nie został utworzony');
            echo "✓ Plik tymczasowy: {$tempFile} (rozmiar: " . filesize($tempFile) . " bajtów)\n";
            
            $uploadedFile = $this->googleDriveService->uploadFile($tempFile, $fileName, $folderId);
            
            if ($uploadedFile === null) {
                $this->fail('Upload pliku nie powiódł się - test wymaga poprawnej konfiguracji OAuth');
            } else {
                $this->assertArrayHasKey('id', $uploadedFile);
                echo "✓ Przesłano plik: {$uploadedFile['name']} (ID: {$uploadedFile['id']})\n";

                // Pobierz metadane pliku
                $fileMetadata = $this->googleDriveService->getFileMetadata($uploadedFile['id']);
                $this->assertNotNull($fileMetadata, 'Nie można pobrać metadanych pliku');
                echo "✓ Metadane pliku: rozmiar {$fileMetadata['size']} bajtów\n";

                // Pobierz plik z powrotem i sprawdź zawartość
                $downloadPath = tempnam(sys_get_temp_dir(), 'downloaded_');
                $downloadSuccess = $this->googleDriveService->downloadFile($uploadedFile['id'], $downloadPath);
                $this->assertTrue($downloadSuccess, 'Nie można pobrać pliku z Google Drive');

                $downloadedContent = file_get_contents($downloadPath);
                $this->assertEquals($csvContent, $downloadedContent, 'Zawartość pobranego pliku nie zgadza się z oryginałem');

                echo "✓ Pobrano i zweryfikowano plik: OK\n";
                echo "✓ Link do pliku: {$uploadedFile['web_view_link']}\n";

                // Czyszczenie
                unlink($downloadPath);
            }

            // Czyszczenie
            unlink($tempFile);

        } catch (\Exception $e) {
            $this->fail('Test uploadu nie powiódł się: ' . $e->getMessage());
        }
    }

    /**
     * Test sprawdzający pobranie pliku z Google Drive i weryfikację zgodności
     */
    public function test_file_download_and_verification()
    {
        try {
            // Utwórz folder testowy dla tego testu
            $folderName = 'Test_Download_' . date('Y-m-d_H-i-s');
            $folder = $this->googleDriveService->createFolder($folderName, null);
            $this->assertNotNull($folder, 'Nie można utworzyć folderu testowego');
            
            echo "✓ Utworzono folder testowy: {$folder['name']}\n";

            // Przygotuj różne typy plików testowych
            $testFiles = [
                'text' => [
                    'content' => "To jest plik testowy.\nZawiera polskie znaki: ąćęłńóśźż\nData: " . date('Y-m-d H:i:s'),
                    'filename' => 'test_text_file_' . date('Y-m-d_H-i-s') . '.txt',
                    'mime_type' => 'text/plain'
                ],
                'csv' => [
                    'content' => "Data,Opis,Kwota,Kategoria\n2025-07-31,\"Zakupy spożywcze\",150.50,Żywność\n2025-07-31,\"Benzyna\",200.00,Transport",
                    'filename' => 'test_csv_file_' . date('Y-m-d_H-i-s') . '.csv',
                    'mime_type' => 'text/csv'
                ],
                'json' => [
                    'content' => json_encode([
                        'test' => true,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'data' => ['ąćęłńóśźż', 'test', 123, 45.67]
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    'filename' => 'test_json_file_' . date('Y-m-d_H-i-s') . '.json',
                    'mime_type' => 'application/json'
                ]
            ];

            foreach ($testFiles as $type => $fileData) {
                echo "\n--- Test pobierania pliku typu: {$type} ---\n";
                
                // Utwórz plik tymczasowy
                $tempUploadFile = tempnam(sys_get_temp_dir(), 'test_upload_');
                file_put_contents($tempUploadFile, $fileData['content']);
                
                $this->assertFileExists($tempUploadFile, 'Plik tymczasowy nie został utworzony');
                echo "✓ Utworzono plik tymczasowy: " . basename($tempUploadFile) . " (rozmiar: " . filesize($tempUploadFile) . " bajtów)\n";
                
                // Upload pliku na Google Drive
                $uploadedFile = $this->googleDriveService->uploadFile(
                    $tempUploadFile, 
                    $fileData['filename'], 
                    $folder['id']
                );
                
                if ($uploadedFile === null) {
                    $this->fail("Upload pliku nie powiódł się dla typu {$type}");
                }
                
                $this->assertArrayHasKey('id', $uploadedFile);
                echo "✓ Przesłano plik: {$uploadedFile['name']} (ID: {$uploadedFile['id']})\n";
                
                // Pobierz metadane pliku
                $fileMetadata = $this->googleDriveService->getFileMetadata($uploadedFile['id']);
                $this->assertNotNull($fileMetadata, 'Nie można pobrać metadanych pliku');
                $this->assertEquals($fileData['filename'], $fileMetadata['name']);
                echo "✓ Metadane pliku: rozmiar {$fileMetadata['size']} bajtów, typ MIME: {$fileMetadata['mime_type']}\n";
                
                // Pobierz plik z Google Drive
                $tempDownloadFile = tempnam(sys_get_temp_dir(), 'test_download_');
                $downloadSuccess = $this->googleDriveService->downloadFile($uploadedFile['id'], $tempDownloadFile);
                
                $this->assertTrue($downloadSuccess, 'Nie można pobrać pliku z Google Drive');
                $this->assertFileExists($tempDownloadFile, 'Pobrany plik nie istnieje');
                echo "✓ Plik został pobrany pomyślnie\n";
                
                // Weryfikuj zawartość pliku
                $originalContent = $fileData['content'];
                $downloadedContent = file_get_contents($tempDownloadFile);
                
                $this->assertEquals($originalContent, $downloadedContent, 'Zawartość pobranego pliku nie zgadza się z oryginałem');
                echo "✓ Zawartość pliku jest identyczna z oryginałem\n";
                
                // Weryfikuj rozmiar pliku
                $originalSize = strlen($originalContent);
                $downloadedSize = filesize($tempDownloadFile);
                
                $this->assertEquals($originalSize, $downloadedSize, 'Rozmiar pobranego pliku nie zgadza się z oryginałem');
                echo "✓ Rozmiar pliku jest identyczny: {$originalSize} bajtów\n";
                
                // Weryfikuj hash pliku (dla dodatkowej pewności)
                $originalHash = md5($originalContent);
                $downloadedHash = md5_file($tempDownloadFile);
                
                $this->assertEquals($originalHash, $downloadedHash, 'Hash pobranego pliku nie zgadza się z oryginałem');
                echo "✓ Hash MD5 pliku jest identyczny: {$originalHash}\n";
                
                // Sprawdź czy plik może być ponownie przeczytany
                $reReadContent = file_get_contents($tempDownloadFile);
                $this->assertEquals($originalContent, $reReadContent, 'Ponowne odczytanie pliku dało inne wyniki');
                echo "✓ Ponowne odczytanie pliku: OK\n";
                
                // Czyszczenie plików tymczasowych
                unlink($tempUploadFile);
                unlink($tempDownloadFile);
                
                echo "✓ Test pobierania i weryfikacji pliku typu {$type}: ZAKOŃCZONY SUKCESEM\n";
            }

            echo "\n🎉 Test pobierania i weryfikacji plików zakończony sukcesem!\n";
            echo "Wszystkie typy plików zostały poprawnie przesłane, pobrane i zweryfikowane.\n";

        } catch (\Exception $e) {
            $this->fail('Test pobierania pliku nie powiódł się: ' . $e->getMessage());
        }
    }

    /**
     * Test sprawdzający obsługę błędów
     */
    public function test_error_handling()
    {
        // Test z nieprawidłowym ID pliku
        $fileMetadata = $this->googleDriveService->getFileMetadata('invalid_file_id');
        $this->assertNull($fileMetadata, 'Powinno zwrócić null dla nieprawidłowego ID pliku');

        // Test pobierania nieistniejącego pliku
        $downloadSuccess = $this->googleDriveService->downloadFile('invalid_file_id', '/tmp/test');
        $this->assertFalse($downloadSuccess, 'Powinno zwrócić false dla nieprawidłowego ID pliku');

        echo "✓ Obsługa błędów: OK\n";
    }
} 