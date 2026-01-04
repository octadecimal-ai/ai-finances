<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WFirmaInvoice;
use App\Models\WFirmaInvoiceContent;
use App\Models\WFirmaExpense;
use App\Models\WFirmaExpensePart;
use App\Models\WFirmaIncome;
use App\Models\WFirmaPayment;
use App\Models\WFirmaTerm;
use App\Models\WFirmaInterest;
use App\Services\Banking\WFirmaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncWFirmaData extends Command
{
    protected $signature = 'wfirma:sync 
                            {period : Okres do synchronizacji (format: YYYY-MM lub YYYY)}
                            {--user-id=1 : ID użytkownika}';

    protected $description = 'Synchronizuje dane z wFirma dla wskazanego okresu';

    public function handle(WFirmaService $wfirmaService): int
    {
        $period = $this->argument('period');
        $userId = (int) $this->option('user-id');

        // Pobierz użytkownika
        $user = User::find($userId);
        if (!$user) {
            $this->error("❌ Użytkownik o ID {$userId} nie istnieje");
            return 1;
        }

        // Parsuj okres i określ zakres dat
        $dateRange = $this->parsePeriod($period);
        if (!$dateRange) {
            $this->error("❌ Nieprawidłowy format okresu. Użyj YYYY-MM lub YYYY");
            return 1;
        }

        $this->info("🔄 Synchronizacja danych z wFirma");
        $this->info("👤 Użytkownik: {$user->name} ({$user->email})");
        $this->info("📅 Okres: {$dateRange['from']} - {$dateRange['to']}");
        $this->newLine();

        // Sprawdź połączenie z wFirma
        if (!$wfirmaService->testConnection()) {
            $this->error("❌ Nie można połączyć się z wFirma API. Sprawdź konfigurację.");
            return 1;
        }

        $filters = [
            'date_from' => $dateRange['from'],
            'date_to' => $dateRange['to'],
        ];

        $stats = [
            'invoices' => 0,
            'invoice_contents' => 0,
            'expenses' => 0,
            'expense_parts' => 0,
            'incomes' => 0,
            'payments' => 0,
            'terms' => 0,
            'interests' => 0,
        ];

        try {
            DB::beginTransaction();

            // 1. Faktury sprzedażowe
            $this->info("📄 Synchronizacja faktur sprzedażowych...");
            $invoices = $wfirmaService->getInvoices($filters);
            if (empty($invoices)) {
                $this->warn("  ⚠️  Brak faktur dla okresu {$dateRange['from']} - {$dateRange['to']}");
            } else {
                foreach ($invoices as $invoiceData) {
                $invoice = $this->syncInvoice($user->id, $invoiceData);
                if ($invoice) {
                    $stats['invoices']++;
                    
                    // Synchronizuj zawartość faktury
                    if (isset($invoiceData['invoicecontents']['invoicecontent'])) {
                        $contents = $invoiceData['invoicecontents']['invoicecontent'];
                        if (!isset($contents[0])) {
                            $contents = [$contents];
                        }
                        foreach ($contents as $contentData) {
                            $this->syncInvoiceContent($invoice->id, $contentData);
                            $stats['invoice_contents']++;
                        }
                    }
                }
                }
                $this->info("  ✅ Zsynchronizowano {$stats['invoices']} faktur i {$stats['invoice_contents']} pozycji");
            }

            // 2. Wydatki
            $this->info("📄 Synchronizacja wydatków...");
            $expenses = $wfirmaService->getExpenses($filters);
            if (empty($expenses)) {
                $this->warn("  ⚠️  Brak wydatków dla okresu {$dateRange['from']} - {$dateRange['to']}");
            } else {
                foreach ($expenses as $expenseData) {
                    $expense = $this->syncExpense($user->id, $expenseData);
                    if ($expense) {
                        $stats['expenses']++;
                        
                        // Synchronizuj części wydatku
                        if (isset($expenseData['expense_parts']['expense_part'])) {
                            $parts = $expenseData['expense_parts']['expense_part'];
                            if (!isset($parts[0])) {
                                $parts = [$parts];
                            }
                            foreach ($parts as $partData) {
                                $this->syncExpensePart($expense->id, $partData);
                                $stats['expense_parts']++;
                            }
                        }
                    }
                }
                $this->info("  ✅ Zsynchronizowano {$stats['expenses']} wydatków i {$stats['expense_parts']} pozycji");
            }

            // 3. Przychody
            $this->info("📄 Synchronizacja przychodów...");
            $incomes = $wfirmaService->getIncomes($filters);
            if (empty($incomes)) {
                $this->warn("  ⚠️  Brak przychodów dla okresu {$dateRange['from']} - {$dateRange['to']}");
            } else {
                foreach ($incomes as $incomeData) {
                    if ($this->syncIncome($user->id, $incomeData)) {
                        $stats['incomes']++;
                    }
                }
                $this->info("  ✅ Zsynchronizowano {$stats['incomes']} przychodów");
            }

            // 4. Płatności
            $this->info("📄 Synchronizacja płatności...");
            $payments = $wfirmaService->getPayments($filters);
            if (empty($payments)) {
                $this->warn("  ⚠️  Brak płatności dla okresu {$dateRange['from']} - {$dateRange['to']}");
            } else {
                foreach ($payments as $paymentData) {
                    if ($this->syncPayment($user->id, $paymentData)) {
                        $stats['payments']++;
                    }
                }
                $this->info("  ✅ Zsynchronizowano {$stats['payments']} płatności");
            }

            // 5. Terminy
            $this->info("📄 Synchronizacja terminów...");
            $terms = $wfirmaService->getTerms($filters);
            if (empty($terms)) {
                $this->warn("  ⚠️  Brak terminów dla okresu {$dateRange['from']} - {$dateRange['to']}");
            } else {
                foreach ($terms as $termData) {
                    if ($this->syncTerm($user->id, $termData)) {
                        $stats['terms']++;
                    }
                }
                $this->info("  ✅ Zsynchronizowano {$stats['terms']} terminów");
            }

            // 6. Rozliczenia ZUS
            $this->info("📄 Synchronizacja rozliczeń ZUS...");
            $interests = $wfirmaService->getZusDeclarations($filters);
            if (empty($interests)) {
                $this->warn("  ⚠️  Brak rozliczeń ZUS w module 'interests' dla okresu {$dateRange['from']} - {$dateRange['to']}");
                $this->warn("  ⚠️  Uwaga: Moduł 'interests' zawiera odsetki podatkowe, nie rozliczenia ZUS.");
                $this->warn("  ⚠️  Rozliczenia ZUS mogą wymagać modułu Kadry i Płace lub dodatkowych uprawnień API.");
            } else {
                foreach ($interests as $interestData) {
                    if ($this->syncInterest($user->id, $interestData)) {
                        $stats['interests']++;
                    }
                }
                $this->info("  ✅ Zsynchronizowano {$stats['interests']} rozliczeń ZUS");
            }

            DB::commit();

            $this->newLine();
            $this->info("✅ Synchronizacja zakończona pomyślnie!");
            $this->table(
                ['Typ', 'Liczba'],
                [
                    ['Faktury sprzedażowe', $stats['invoices']],
                    ['Pozycje faktur', $stats['invoice_contents']],
                    ['Wydatki', $stats['expenses']],
                    ['Pozycje wydatków', $stats['expense_parts']],
                    ['Przychody', $stats['incomes']],
                    ['Płatności', $stats['payments']],
                    ['Terminy', $stats['terms']],
                    ['Rozliczenia ZUS', $stats['interests']],
                ]
            );

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Błąd synchronizacji: " . $e->getMessage());
            Log::error('wFirma sync error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Konwertuje wartość na odpowiedni typ dla bazy danych
     */
    private function convertValue($value, string $field): mixed
    {
        // Jeśli wartość jest tablicą, konwertuj na JSON lub null
        if (is_array($value)) {
            return empty($value) ? null : json_encode($value);
        }
        
        // Jeśli wartość jest null lub pustym stringiem dla pól tekstowych, zwróć null
        if (($value === '' || $value === null) && in_array($field, ['description', 'header', 'footer', 'register_description'])) {
            return null;
        }
        
        return $value;
    }

    /**
     * Parsuje okres i zwraca zakres dat
     */
    private function parsePeriod(string $period): ?array
    {
        // Format YYYY-MM
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            
            if ($month < 1 || $month > 12) {
                return null;
            }
            
            $from = sprintf('%04d-%02d-01', $year, $month);
            $to = date('Y-m-t', strtotime($from)); // Ostatni dzień miesiąca
            
            return ['from' => $from, 'to' => $to];
        }
        
        // Format YYYY
        if (preg_match('/^(\d{4})$/', $period, $matches)) {
            $year = (int) $matches[1];
            
            $from = sprintf('%04d-01-01', $year);
            $to = sprintf('%04d-12-31', $year);
            
            return ['from' => $from, 'to' => $to];
        }
        
        return null;
    }

    /**
     * Synchronizuje fakturę sprzedażową
     */
    private function syncInvoice(int $userId, array $data): ?WFirmaInvoice
    {
        if (!isset($data['id'])) {
            return null;
        }

        return WFirmaInvoice::updateOrCreate(
            [
                'user_id' => $userId,
                'wfirma_id' => (string) $data['id'],
            ],
            array_merge(
                $this->extractInvoiceData($data),
                ['synced_at' => now()]
            )
        );
    }

    /**
     * Synchronizuje zawartość faktury
     */
    private function syncInvoiceContent(int $invoiceId, array $data): ?WFirmaInvoiceContent
    {
        if (!isset($data['id'])) {
            return null;
        }

        return WFirmaInvoiceContent::updateOrCreate(
            [
                'invoice_id' => $invoiceId,
                'wfirma_id' => (string) $data['id'],
            ],
            $this->extractInvoiceContentData($data)
        );
    }

    /**
     * Synchronizuje wydatek
     */
    private function syncExpense(int $userId, array $data): ?WFirmaExpense
    {
        if (!isset($data['id'])) {
            return null;
        }

        return WFirmaExpense::updateOrCreate(
            [
                'user_id' => $userId,
                'wfirma_id' => (string) $data['id'],
            ],
            array_merge(
                $this->extractExpenseData($data),
                ['synced_at' => now()]
            )
        );
    }

    /**
     * Synchronizuje część wydatku
     */
    private function syncExpensePart(int $expenseId, array $data): ?WFirmaExpensePart
    {
        if (!isset($data['id'])) {
            return null;
        }

        return WFirmaExpensePart::updateOrCreate(
            [
                'expense_id' => $expenseId,
                'wfirma_id' => (string) $data['id'],
            ],
            $this->extractExpensePartData($data)
        );
    }

    /**
     * Synchronizuje przychód
     */
    private function syncIncome(int $userId, array $data): ?WFirmaIncome
    {
        if (!isset($data['id'])) {
            return null;
        }

        return WFirmaIncome::updateOrCreate(
            [
                'user_id' => $userId,
                'wfirma_id' => (string) $data['id'],
            ],
            array_merge(
                $this->extractIncomeData($data),
                ['synced_at' => now()]
            )
        );
    }

    /**
     * Synchronizuje płatność
     */
    private function syncPayment(int $userId, array $data): ?WFirmaPayment
    {
        if (!isset($data['id'])) {
            return null;
        }

        return WFirmaPayment::updateOrCreate(
            [
                'user_id' => $userId,
                'wfirma_id' => (string) $data['id'],
            ],
            array_merge(
                $this->extractPaymentData($data),
                ['synced_at' => now()]
            )
        );
    }

    /**
     * Synchronizuje termin
     */
    private function syncTerm(int $userId, array $data): ?WFirmaTerm
    {
        if (!isset($data['id'])) {
            return null;
        }

        return WFirmaTerm::updateOrCreate(
            [
                'user_id' => $userId,
                'wfirma_id' => (string) $data['id'],
            ],
            array_merge(
                $this->extractTermData($data),
                ['synced_at' => now()]
            )
        );
    }

    /**
     * Synchronizuje rozliczenie ZUS
     */
    private function syncInterest(int $userId, array $data): ?WFirmaInterest
    {
        if (!isset($data['id'])) {
            return null;
        }

        return WFirmaInterest::updateOrCreate(
            [
                'user_id' => $userId,
                'wfirma_id' => (string) $data['id'],
            ],
            array_merge(
                $this->extractInterestData($data),
                ['synced_at' => now()]
            )
        );
    }

    /**
     * Wyciąga dane faktury z odpowiedzi API
     */
    private function extractInvoiceData(array $data): array
    {
        $fields = [
            'type', 'date', 'disposaldate', 'disposaldate_empty', 'disposaldate_format',
            'paymentdate', 'paymentmethod', 'paymentstate', 'alreadypaid_initial', 'alreadypaid',
            'currency', 'currency_exchange', 'currency_label', 'currency_date',
            'price_currency_exchange', 'good_price_group_currency_exchange',
            'number', 'day', 'month', 'year', 'fullnumber', 'semitemplatenumber',
            'correction_type', 'corrections', 'schema', 'schema_bill', 'schema_cancelled',
            'schema_receipt_book', 'register_description', 'template', 'auto_send',
            'description', 'header', 'footer', 'user_name', 'netto', 'tax', 'total',
            'total_composed', 'signed', 'hash', 'id_external', 'warehouse_type',
            'notes', 'documents', 'tags', 'price_type', 'series_id', 'contractor_id',
            'receipt_fiscal_printed', 'income_lumpcode', 'income_correction', 'period',
        ];

        // Pola które mogą być tablicami i powinny być konwertowane
        $arrayFields = [
            'currency_label', 'currency_date', 'correction_type', 'register_description',
            'description', 'header', 'footer', 'id_external', 'tags', 'income_lumpcode',
        ];

        $result = ['wfirma_id' => (string) $data['id']];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                
                // Jeśli pole może być tablicą i jest tablicą, konwertuj na JSON lub null
                if (in_array($field, $arrayFields) && is_array($value)) {
                    $result[$field] = empty($value) ? null : json_encode($value);
                } else {
                    $result[$field] = $this->convertValue($value, $field);
                }
            }
        }

        // Obsługa zagnieżdżonych pól
        if (isset($data['series']['id'])) {
            $result['series_id'] = (string) $data['series']['id'];
        }
        
        if (isset($data['contractor']['id'])) {
            $result['contractor_id'] = (string) $data['contractor']['id'];
        }

        // Zapisz pełne dane w metadata
        $result['metadata'] = $data;

        return $result;
    }

    /**
     * Wyciąga dane zawartości faktury z odpowiedzi API
     */
    private function extractInvoiceContentData(array $data): array
    {
        $fields = [
            'name', 'classification', 'unit', 'unit_id', 'count', 'unit_count',
            'price', 'price_modified', 'vat', 'vat_code_id', 'discount', 'discount_percent',
            'netto', 'brutto', 'lumpcode', 'good_id', 'tangiblefixedasset_id',
            'equipment_id', 'vehicle_id',
        ];

        // Pola które mogą być tablicami
        $arrayFields = ['classification'];

        $result = ['wfirma_id' => (string) $data['id']];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                
                // Jeśli pole może być tablicą i jest tablicą, konwertuj na JSON lub null
                if (in_array($field, $arrayFields) && is_array($value)) {
                    $result[$field] = empty($value) ? null : json_encode($value);
                } else {
                    $result[$field] = $this->convertValue($value, $field);
                }
            }
        }

        // Obsługa zagnieżdżonych pól
        if (isset($data['good']['id'])) {
            $result['good_id'] = (string) $data['good']['id'];
        }
        
        if (isset($data['vat_code']['id'])) {
            $result['vat_code_id'] = (string) $data['vat_code']['id'];
        }

        $result['metadata'] = $data;

        return $result;
    }

    /**
     * Wyciąga dane wydatku z odpowiedzi API
     */
    private function extractExpenseData(array $data): array
    {
        $fields = [
            'type', 'date', 'taxregister_date', 'payment_date', 'payment_method',
            'paid', 'alreadypaid_initial', 'currency', 'accounting_effect', 'warehouse_type',
            'schema_vat_cashbox', 'wnt', 'service_import', 'service_import2',
            'cargo_import', 'split_payment', 'draft', 'tax_evaluation_method',
            'contractor_id', 'fullnumber', 'number', 'description', 'netto', 'brutto',
            'vat_content_netto', 'vat_content_tax', 'vat_content_brutto', 'total', 'remaining',
        ];

        // Pola które mogą być tablicami
        $arrayFields = ['description'];

        $result = ['wfirma_id' => (string) $data['id']];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                
                // Jeśli pole może być tablicą i jest tablicą, konwertuj na JSON lub null
                if (in_array($field, $arrayFields) && is_array($value)) {
                    $result[$field] = empty($value) ? null : json_encode($value);
                } else {
                    $result[$field] = $this->convertValue($value, $field);
                }
            }
        }

        // Obsługa zagnieżdżonych pól
        if (isset($data['contractor']['id'])) {
            $result['contractor_id'] = (string) $data['contractor']['id'];
        }

        $result['metadata'] = $data;

        return $result;
    }

    /**
     * Wyciąga dane części wydatku z odpowiedzi API
     */
    private function extractExpensePartData(array $data): array
    {
        $fields = [
            'expense_part_type', 'schema', 'good_action', 'good_id', 'unit', 'unit_id',
            'count', 'price', 'vat_code_id', 'name', 'classification', 'netto', 'brutto',
            'vat', 'discount', 'discount_percent',
        ];

        // Pola które mogą być tablicami
        $arrayFields = ['classification'];

        $result = ['wfirma_id' => (string) $data['id']];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                
                // Jeśli pole może być tablicą i jest tablicą, konwertuj na JSON lub null
                if (in_array($field, $arrayFields) && is_array($value)) {
                    $result[$field] = empty($value) ? null : json_encode($value);
                } else {
                    $result[$field] = $this->convertValue($value, $field);
                }
            }
        }

        // Obsługa zagnieżdżonych pól
        if (isset($data['good']['id'])) {
            $result['good_id'] = (string) $data['good']['id'];
        }
        
        if (isset($data['vat_code']['id'])) {
            $result['vat_code_id'] = (string) $data['vat_code']['id'];
        }

        $result['metadata'] = $data;

        return $result;
    }

    /**
     * Wyciąga dane przychodu z odpowiedzi API
     */
    private function extractIncomeData(array $data): array
    {
        $fields = [
            'type', 'date', 'taxregister_date', 'payment_date', 'payment_method',
            'paid', 'alreadypaid_initial', 'currency', 'accounting_effect', 'warehouse_type',
            'schema_vat_cashbox', 'wnt', 'service_import', 'service_import2',
            'cargo_import', 'split_payment', 'draft', 'tax_evaluation_method',
            'contractor_id', 'fullnumber', 'number', 'description', 'netto', 'brutto',
            'vat_content_netto', 'vat_content_tax', 'vat_content_brutto', 'total', 'remaining',
        ];

        // Pola które mogą być tablicami
        $arrayFields = ['description'];

        $result = ['wfirma_id' => (string) $data['id']];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                
                // Jeśli pole może być tablicą i jest tablicą, konwertuj na JSON lub null
                if (in_array($field, $arrayFields) && is_array($value)) {
                    $result[$field] = empty($value) ? null : json_encode($value);
                } else {
                    $result[$field] = $this->convertValue($value, $field);
                }
            }
        }

        // Obsługa zagnieżdżonych pól
        if (isset($data['contractor']['id'])) {
            $result['contractor_id'] = (string) $data['contractor']['id'];
        }

        $result['metadata'] = $data;

        return $result;
    }

    /**
     * Wyciąga dane płatności z odpowiedzi API
     */
    private function extractPaymentData(array $data): array
    {
        $fields = [
            'date', 'amount', 'currency', 'payment_method', 'payment_cashbox_id',
            'description', 'invoice_id', 'expense_id', 'income_id', 'contractor_id',
            'bank_account_id', 'status',
        ];

        // Pola które mogą być tablicami
        $arrayFields = ['description'];

        $result = ['wfirma_id' => (string) $data['id']];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                
                // Jeśli pole może być tablicą i jest tablicą, konwertuj na JSON lub null
                if (in_array($field, $arrayFields) && is_array($value)) {
                    $result[$field] = empty($value) ? null : json_encode($value);
                } else {
                    $result[$field] = $this->convertValue($value, $field);
                }
            }
        }

        // Obsługa zagnieżdżonych pól
        if (isset($data['invoice']['id'])) {
            $result['invoice_id'] = (string) $data['invoice']['id'];
        }
        
        if (isset($data['expense']['id'])) {
            $result['expense_id'] = (string) $data['expense']['id'];
        }
        
        if (isset($data['income']['id'])) {
            $result['income_id'] = (string) $data['income']['id'];
        }
        
        if (isset($data['contractor']['id'])) {
            $result['contractor_id'] = (string) $data['contractor']['id'];
        }

        $result['metadata'] = $data;

        return $result;
    }

    /**
     * Wyciąga dane terminu z odpowiedzi API
     */
    private function extractTermData(array $data): array
    {
        $fields = [
            'term_group_id', 'date', 'time', 'description', 'title', 'status',
            'reminder', 'reminder_minutes', 'contractor_id', 'invoice_id',
            'expense_id', 'income_id',
        ];

        // Pola które mogą być tablicami
        $arrayFields = ['description'];

        $result = ['wfirma_id' => (string) $data['id']];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                
                // Jeśli pole może być tablicą i jest tablicą, konwertuj na JSON lub null
                if (in_array($field, $arrayFields) && is_array($value)) {
                    $result[$field] = empty($value) ? null : json_encode($value);
                } else {
                    $result[$field] = $this->convertValue($value, $field);
                }
            }
        }

        // Obsługa zagnieżdżonych pól
        if (isset($data['invoice']['id'])) {
            $result['invoice_id'] = (string) $data['invoice']['id'];
        }
        
        if (isset($data['expense']['id'])) {
            $result['expense_id'] = (string) $data['expense']['id'];
        }
        
        if (isset($data['income']['id'])) {
            $result['income_id'] = (string) $data['income']['id'];
        }
        
        if (isset($data['contractor']['id'])) {
            $result['contractor_id'] = (string) $data['contractor']['id'];
        }

        $result['metadata'] = $data;

        return $result;
    }

    /**
     * Wyciąga dane rozliczenia ZUS z odpowiedzi API
     */
    private function extractInterestData(array $data): array
    {
        $fields = [
            'type', 'period', 'date', 'due_date', 'amount', 'currency', 'status',
            'description', 'zus_type', 'employee_id', 'declaration_number',
            'payment_date', 'paid',
        ];

        // Pola które mogą być tablicami
        $arrayFields = ['description'];

        $result = ['wfirma_id' => (string) $data['id']];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                
                // Jeśli pole może być tablicą i jest tablicą, konwertuj na JSON lub null
                if (in_array($field, $arrayFields) && is_array($value)) {
                    $result[$field] = empty($value) ? null : json_encode($value);
                } else {
                    $result[$field] = $this->convertValue($value, $field);
                }
            }
        }

        $result['metadata'] = $data;

        return $result;
    }
}
