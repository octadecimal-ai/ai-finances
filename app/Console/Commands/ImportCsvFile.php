<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Import\CsvImportService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;

class ImportCsvFile extends Command
{
    protected $signature = 'import:csv-file 
                            {file : Ścieżka do pliku CSV}
                            {--format=revolut : Format CSV (revolut, mbank, ing, pko)}
                            {--user-id=1 : ID użytkownika}
                            {--bank-account-id= : ID konta bankowego}';

    protected $description = 'Importuj plik CSV z transakcjami';

    public function handle(CsvImportService $csvImportService): int
    {
        $filePath = $this->argument('file');
        $format = $this->option('format');
        $userId = (int) $this->option('user-id');
        $bankAccountId = $this->option('bank-account-id') ? (int) $this->option('bank-account-id') : null;

        // Sprawdź czy plik istnieje
        if (!file_exists($filePath)) {
            $this->error("❌ Plik nie istnieje: {$filePath}");
            return 1;
        }

        // Pobierz użytkownika
        $user = User::find($userId);
        if (!$user) {
            $this->error("❌ Użytkownik o ID {$userId} nie istnieje");
            return 1;
        }

        $this->info("📁 Importowanie pliku: {$filePath}");
        $this->info("📋 Format: {$format}");
        $this->info("👤 Użytkownik: {$user->name} ({$user->email})");

        try {
            // Utwórz UploadedFile z istniejącego pliku
            $file = new File($filePath);
            $uploadedFile = new UploadedFile(
                $file->getPathname(),
                $file->getFilename(),
                $file->getMimeType(),
                null,
                true
            );

            // Importuj plik
            $result = $csvImportService->importCsv($user, $uploadedFile, $format, $bankAccountId);

            if ($result['success']) {
                $this->info("✅ Zaimportowano {$result['imported_count']} transakcji");
                
                if (!empty($result['errors'])) {
                    $this->warn("⚠️  Wystąpiło " . count($result['errors']) . " błędów:");
                    foreach (array_slice($result['errors'], 0, 5) as $error) {
                        $this->line("  - Wiersz {$error['row']}: {$error['error']}");
                    }
                    if (count($result['errors']) > 5) {
                        $this->line("  ... i " . (count($result['errors']) - 5) . " więcej");
                    }
                }

                return 0;
            } else {
                $this->error("❌ Błąd importu: " . ($result['error'] ?? 'Nieznany błąd'));
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Błąd: " . $e->getMessage());
            return 1;
        }
    }
}
