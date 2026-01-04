<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\Import\InvoiceParserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportInvoices extends Command
{
    protected $signature = 'import:invoices 
                            {file : Ścieżka do pliku PDF/CSV lub katalogu z fakturami}
                            {--user-id=1 : ID użytkownika}
                            {--source-type=cursor : Typ źródła (cursor, anthropic, google, openai, ovh, etc.)}';

    protected $description = 'Importuj faktury z plików PDF lub CSV (OVH)';

    public function handle(InvoiceParserService $parserService): int
    {
        $filePath = $this->argument('file');
        $userId = (int) $this->option('user-id');
        $sourceType = $this->option('source-type');

        // Sprawdź czy plik/katalog istnieje
        if (!file_exists($filePath)) {
            $this->error("❌ Plik/katalog nie istnieje: {$filePath}");
            return 1;
        }

        // Pobierz użytkownika
        $user = User::find($userId);
        if (!$user) {
            $this->error("❌ Użytkownik o ID {$userId} nie istnieje");
            return 1;
        }

        $this->info("📁 Importowanie faktur z: {$filePath}");
        $this->info("👤 Użytkownik: {$user->name} ({$user->email})");
        $this->info("📋 Typ źródła: {$sourceType}");

        $files = [];
        
        // Jeśli to plik
        if (is_file($filePath)) {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if ($extension === 'pdf' || ($extension === 'csv' && $sourceType === 'ovh')) {
                $files[] = $filePath;
            } else {
                $this->error("❌ Nieobsługiwany typ pliku: {$extension}");
                return 1;
            }
        } 
        // Jeśli to katalog
        elseif (is_dir($filePath)) {
            // Dla OVH szukaj CSV, dla innych PDF
            if ($sourceType === 'ovh') {
                $files = glob($filePath . '/*.csv');
            } else {
                $files = glob($filePath . '/*.pdf');
            }
            
            if (empty($files)) {
                $fileType = $sourceType === 'ovh' ? 'CSV' : 'PDF';
                $this->warn("⚠️  Nie znaleziono plików {$fileType} w katalogu: {$filePath}");
                return 0;
            }
        } else {
            $this->error("❌ Nieprawidłowa ścieżka: {$filePath}");
            return 1;
        }

        $importedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($files as $file) {
            try {
                $this->line("📄 Przetwarzanie: " . basename($file));

                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $isCsv = ($extension === 'csv' && $sourceType === 'ovh');

                // Dla CSV OVH parsuj wszystkie faktury z pliku
                if ($isCsv) {
                    $allInvoices = $parserService->parseAllInvoicesFromCsv($file, $sourceType);
                    
                    foreach ($allInvoices as $invoiceData) {
                        // Sprawdź czy faktura już istnieje (na podstawie numeru faktury)
                        $existingInvoice = Invoice::where('user_id', $user->id)
                            ->where('invoice_number', $invoiceData['invoice_number'])
                            ->where('source_type', $sourceType)
                            ->first();

                        if ($existingInvoice) {
                            $this->warn("  ⏭️  Pominięto: {$invoiceData['invoice_number']} (już zaimportowana)");
                            $skippedCount++;
                            continue;
                        }

                        // Zapisz fakturę do bazy
                        DB::beginTransaction();
                        
                        try {
                            $invoice = Invoice::create([
                                'user_id' => $user->id,
                                'invoice_number' => $invoiceData['invoice_number'],
                                'invoice_date' => $invoiceData['invoice_date'] ? date('Y-m-d H:i:s', strtotime($invoiceData['invoice_date'])) : null,
                                'issue_date' => $invoiceData['issue_date'] ? date('Y-m-d H:i:s', strtotime($invoiceData['issue_date'])) : null,
                                'due_date' => $invoiceData['due_date'] ? date('Y-m-d H:i:s', strtotime($invoiceData['due_date'])) : null,
                                'seller_name' => $invoiceData['seller_name'],
                                'seller_tax_id' => $invoiceData['seller_tax_id'],
                                'seller_address' => $invoiceData['seller_address'],
                                'seller_email' => $invoiceData['seller_email'],
                                'seller_phone' => $invoiceData['seller_phone'],
                                'seller_account_number' => $invoiceData['seller_account_number'],
                                'buyer_name' => $invoiceData['buyer_name'],
                                'buyer_tax_id' => $invoiceData['buyer_tax_id'],
                                'buyer_address' => $invoiceData['buyer_address'],
                                'buyer_email' => $invoiceData['buyer_email'],
                                'buyer_phone' => $invoiceData['buyer_phone'],
                                'subtotal' => $invoiceData['subtotal'],
                                'tax_amount' => $invoiceData['tax_amount'],
                                'total_amount' => $invoiceData['total_amount'],
                                'currency' => $invoiceData['currency'],
                                'payment_method' => $invoiceData['payment_method'],
                                'payment_status' => $invoiceData['payment_status'] ?? 'pending',
                                'file_path' => $file,
                                'file_name' => basename($file),
                                'source_type' => $sourceType,
                                'metadata' => $invoiceData['metadata'],
                                'parsed_at' => now(),
                            ]);

                            // Zapisz pozycje faktury (jeśli są)
                            foreach ($invoiceData['items'] as $itemData) {
                                InvoiceItem::create([
                                    'invoice_id' => $invoice->id,
                                    'position' => $itemData['position'],
                                    'name' => $itemData['name'],
                                    'description' => $itemData['description'] ?? null,
                                    'quantity' => $itemData['quantity'],
                                    'unit' => $itemData['unit'] ?? null,
                                    'unit_price' => $itemData['unit_price'],
                                    'net_amount' => $itemData['net_amount'],
                                    'tax_rate' => $itemData['tax_rate'],
                                    'tax_amount' => $itemData['tax_amount'],
                                    'gross_amount' => $itemData['gross_amount'],
                                ]);
                            }

                            DB::commit();
                            
                            $this->info("  ✅ Zaimportowano: {$invoice->invoice_number} ({$invoice->total_amount} {$invoice->currency})");
                            $importedCount++;

                        } catch (\Exception $e) {
                            DB::rollBack();
                            throw $e;
                        }
                    }
                } else {
                    // Dla PDF parsuj pojedynczą fakturę
                    // Sprawdź czy faktura już istnieje (na podstawie nazwy pliku)
                    $existingInvoice = Invoice::where('user_id', $user->id)
                        ->where('file_name', basename($file))
                        ->first();

                    if ($existingInvoice) {
                        $this->warn("  ⏭️  Pominięto (już zaimportowana)");
                        $skippedCount++;
                        continue;
                    }

                    // Parsuj fakturę
                    $invoiceData = $parserService->parseInvoice($file, $sourceType);
                    
                    // Zapisz fakturę do bazy
                    DB::beginTransaction();
                    
                    try {
                        $invoice = Invoice::create([
                            'user_id' => $user->id,
                            'invoice_number' => $invoiceData['invoice_number'],
                            'invoice_date' => $invoiceData['invoice_date'] ? date('Y-m-d H:i:s', strtotime($invoiceData['invoice_date'])) : null,
                            'issue_date' => $invoiceData['issue_date'] ? date('Y-m-d H:i:s', strtotime($invoiceData['issue_date'])) : null,
                            'due_date' => $invoiceData['due_date'] ? date('Y-m-d H:i:s', strtotime($invoiceData['due_date'])) : null,
                            'seller_name' => $invoiceData['seller_name'],
                            'seller_tax_id' => $invoiceData['seller_tax_id'],
                            'seller_address' => $invoiceData['seller_address'],
                            'seller_email' => $invoiceData['seller_email'],
                            'seller_phone' => $invoiceData['seller_phone'],
                            'seller_account_number' => $invoiceData['seller_account_number'],
                            'buyer_name' => $invoiceData['buyer_name'],
                            'buyer_tax_id' => $invoiceData['buyer_tax_id'],
                            'buyer_address' => $invoiceData['buyer_address'],
                            'buyer_email' => $invoiceData['buyer_email'],
                            'buyer_phone' => $invoiceData['buyer_phone'],
                            'subtotal' => $invoiceData['subtotal'],
                            'tax_amount' => $invoiceData['tax_amount'],
                            'total_amount' => $invoiceData['total_amount'],
                            'currency' => $invoiceData['currency'],
                            'payment_method' => $invoiceData['payment_method'],
                            'file_path' => $file,
                            'file_name' => basename($file),
                            'source_type' => $sourceType,
                            'metadata' => $invoiceData['metadata'],
                            'parsed_at' => now(),
                        ]);

                        // Zapisz pozycje faktury
                        foreach ($invoiceData['items'] as $itemData) {
                            InvoiceItem::create([
                                'invoice_id' => $invoice->id,
                                'position' => $itemData['position'],
                                'name' => $itemData['name'],
                                'description' => $itemData['description'] ?? null,
                                'quantity' => $itemData['quantity'],
                                'unit' => $itemData['unit'] ?? null,
                                'unit_price' => $itemData['unit_price'],
                                'net_amount' => $itemData['net_amount'],
                                'tax_rate' => $itemData['tax_rate'],
                                'tax_amount' => $itemData['tax_amount'],
                                'gross_amount' => $itemData['gross_amount'],
                            ]);
                        }

                        DB::commit();
                        
                        $this->info("  ✅ Zaimportowano: {$invoice->invoice_number} ({$invoice->total_amount} {$invoice->currency})");
                        $importedCount++;

                    } catch (\Exception $e) {
                        DB::rollBack();
                        throw $e;
                    }
                }

            } catch (\Exception $e) {
                $this->error("  ❌ Błąd: " . $e->getMessage());
                Log::error('Invoice import failed', [
                    'file' => $file,
                    'error' => $e->getMessage(),
                ]);
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("📊 Podsumowanie:");
        $this->info("  ✅ Zaimportowano: {$importedCount}");
        $this->info("  ⏭️  Pominięto: {$skippedCount}");
        $this->info("  ❌ Błędy: {$errorCount}");

        return $errorCount > 0 ? 1 : 0;
    }
}
