<?php

namespace App\Services\Banking;

use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class WFirmaService
{
    private string $baseUrl;
    private string $accessKey;
    private string $secretKey;
    private string $appKey;
    private ?string $companyId;
    private int $timeout;
    private int $retryAttempts;

    public function __construct()
    {
        $this->baseUrl = config('banking.wfirma.base_url', 'https://api2.wfirma.pl');
        $this->accessKey = config('banking.wfirma.access_key') ?? '';
        $this->secretKey = config('banking.wfirma.secret_key') ?? '';
        $this->appKey = config('banking.wfirma.app_key') ?? '';
        $this->companyId = config('banking.wfirma.company_id');
        $this->timeout = config('banking.wfirma.timeout', 30);
        $this->retryAttempts = config('banking.wfirma.retry_attempts', 3);
    }

    /**
     * Pobiera listę kont bankowych z wFirma
     */
    public function getBankAccounts(): array
    {
        try {
            $response = $this->makeRequest('GET', '/bank_accounts');

            if ($response && isset($response['bank_accounts'])) {
                return $response['bank_accounts'];
            }

            return [];
        } catch (Exception $e) {
            Log::error('wFirma getBankAccounts error', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Pobiera transakcje bankowe z wFirma
     */
    public function getBankTransactions(string $accountId = null, string $fromDate = null, string $toDate = null): array
    {
        try {
            $params = [];
            
            if ($accountId) {
                $params['bank_account_id'] = $accountId;
            }
            
            if ($fromDate) {
                $params['date_from'] = $fromDate;
            }
            
            if ($toDate) {
                $params['date_to'] = $toDate;
            }

            $response = $this->makeRequest('GET', '/bank_transactions', $params);

            if ($response && isset($response['bank_transactions'])) {
                return $response['bank_transactions'];
            }

            return [];
        } catch (Exception $e) {
            Log::error('wFirma getBankTransactions error', [
                'error' => $e->getMessage(),
                'account_id' => $accountId,
            ]);
            return [];
        }
    }

    /**
     * Pobiera saldo konta bankowego
     */
    public function getBankAccountBalance(string $accountId): float
    {
        try {
            $response = $this->makeRequest('GET', "/bank_accounts/{$accountId}");

            if ($response && isset($response['bank_account'])) {
                return (float) ($response['bank_account']['balance'] ?? 0);
            }

            return 0.0;
        } catch (Exception $e) {
            Log::error('wFirma getBankAccountBalance error', [
                'error' => $e->getMessage(),
                'account_id' => $accountId,
            ]);
            return 0.0;
        }
    }

    /**
     * Synchronizuje konto bankowe z wFirma
     */
    public function syncAccount(BankAccount $account): bool
    {
        try {
            $transactions = $this->getBankTransactions(
                $account->provider_account_id,
                now()->subDays(30)->format('Y-m-d'),
                now()->format('Y-m-d')
            );

            $importedCount = 0;
            foreach ($transactions as $transactionData) {
                if ($this->importTransaction($account, $transactionData)) {
                    $importedCount++;
                }
            }

            $account->update([
                'last_sync_at' => now(),
                'balance' => $this->getBankAccountBalance($account->provider_account_id),
            ]);

            Log::info('wFirma account sync completed', [
                'account_id' => $account->id,
                'imported_transactions' => $importedCount,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('wFirma account sync failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Pobiera faktury sprzedażowe z wFirma
     */
    public function getInvoices(array $filters = []): array
    {
        try {
            return $this->findModuleData('invoices', 'invoice', $filters);
        } catch (Exception $e) {
            Log::error('wFirma getInvoices error', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            return [];
        }
    }

    /**
     * Pobiera wydatki (faktury wydatkowe) z wFirma
     */
    public function getExpenses(array $filters = []): array
    {
        try {
            return $this->findModuleData('expenses', 'expense', $filters);
        } catch (Exception $e) {
            Log::error('wFirma getExpenses error', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            return [];
        }
    }

    /**
     * Pobiera przychody z wFirma
     */
    public function getIncomes(array $filters = []): array
    {
        try {
            return $this->findModuleData('incomes', 'income', $filters);
        } catch (Exception $e) {
            Log::error('wFirma getIncomes error', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            return [];
        }
    }

    /**
     * Pobiera rozliczenia podatkowe JPK_VAT z wFirma
     */
    public function getTaxDeclarationsVat(array $filters = []): array
    {
        try {
            return $this->findModuleData('declaration_body_jpkvat', 'declaration_body_jpkvat', $filters);
        } catch (Exception $e) {
            Log::error('wFirma getTaxDeclarationsVat error', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            return [];
        }
    }

    /**
     * Pobiera rozliczenia podatkowe PIT z wFirma
     */
    public function getTaxDeclarationsPit(array $filters = []): array
    {
        try {
            return $this->findModuleData('declaration_body_pit', 'declaration_body_pit', $filters);
        } catch (Exception $e) {
            Log::error('wFirma getTaxDeclarationsPit error', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            return [];
        }
    }

    /**
     * Pobiera wszystkie rozliczenia podatkowe (VAT i PIT) z wFirma
     */
    public function getTaxDeclarations(array $filters = []): array
    {
        try {
            $vatDeclarations = $this->getTaxDeclarationsVat($filters);
            $pitDeclarations = $this->getTaxDeclarationsPit($filters);
            
            return [
                'vat' => $vatDeclarations,
                'pit' => $pitDeclarations,
            ];
        } catch (Exception $e) {
            Log::error('wFirma getTaxDeclarations error', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            return ['vat' => [], 'pit' => []];
        }
    }

    /**
     * Pobiera rozliczenia ZUS z wFirma
     * 
     * Uwaga: wFirma API nie posiada dedykowanego modułu dla rozliczeń ZUS.
     * Rozliczenia ZUS są dostępne w module Kadry i Płace, który może wymagać
     * dodatkowych uprawnień i może nie być dostępny przez standardowe API.
     * 
     * W przypadku gdy ZUS jest dostępny, może być w module 'interests' lub
     * wymagać bezpośredniej integracji z e-ZUS/PUE.
     * 
     * @param array $filters Filtry daty (date_from, date_to) i limit
     * @return array Pusta tablica lub dane z modułu interests jeśli dostępne
     */
    public function getZusDeclarations(array $filters = []): array
    {
        try {
            // Próba 1: Sprawdź czy ZUS jest w module 'interests'
            // (interests może zawierać różne rodzaje rozliczeń)
            try {
                // Moduł interests może nie obsługiwać filtrowania po date poprawnie
                // Pobierz wszystkie rozliczenia i przefiltruj lokalnie jeśli są filtry daty
                if (isset($filters['date_from']) || isset($filters['date_to'])) {
                    // Pobierz wszystkie rozliczenia (z limitem) i przefiltruj lokalnie
                    $allInterests = $this->findModuleData('interests', 'interest', ['limit' => 1000]);
                    
                    if (!empty($allInterests)) {
                        $filtered = [];
                        $fromDate = $filters['date_from'] ?? '1900-01-01';
                        $toDate = $filters['date_to'] ?? '2099-12-31';
                        
                        foreach ($allInterests as $interest) {
                            // Sprawdź czy data pasuje do zakresu
                            $interestDate = null;
                            if (isset($interest['date'])) {
                                $interestDate = $interest['date'];
                            } elseif (isset($interest['period'])) {
                                // Period w formacie YYYY-MM, użyj pierwszego dnia miesiąca
                                $period = $interest['period'];
                                if (preg_match('/^(\d{4})-(\d{2})$/', $period, $matches)) {
                                    $interestDate = $matches[1] . '-' . $matches[2] . '-01';
                                }
                            }
                            
                            if ($interestDate && $interestDate >= $fromDate && $interestDate <= $toDate) {
                                $filtered[] = $interest;
                            }
                        }
                        
                        if (!empty($filtered)) {
                            Log::info('wFirma getZusDeclarations - znaleziono dane w module interests (przefiltrowane)', [
                                'count' => count($filtered),
                                'total' => count($allInterests),
                            ]);
                            return $filtered;
                        }
                    }
                } else {
                    // Bez filtrów daty - użyj standardowego podejścia
                    $interests = $this->findModuleData('interests', 'interest', $filters);
                    
                    if (!empty($interests)) {
                        Log::info('wFirma getZusDeclarations - znaleziono dane w module interests', [
                            'count' => count($interests),
                        ]);
                        return $interests;
                    }
                }
            } catch (Exception $e) {
                Log::debug('wFirma getZusDeclarations - module interests nie zwrócił danych', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Jeśli nie znaleziono danych, zwróć pustą tablicę
            Log::info('wFirma getZusDeclarations - brak danych ZUS w dostępnych modułach API');
            return [];
        } catch (Exception $e) {
            Log::error('wFirma getZusDeclarations error', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            return [];
        }
    }

    /**
     * Pobiera płatności z wFirma
     * 
     * @param array $filters Filtry daty (date_from, date_to), limit, invoice_id, expense_id, income_id
     * @return array Lista płatności
     */
    public function getPayments(array $filters = []): array
    {
        try {
            return $this->findModuleData('payments', 'payment', $filters);
        } catch (Exception $e) {
            Log::error('wFirma getPayments error', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            return [];
        }
    }

    /**
     * Pobiera terminy (terminarz) z wFirma
     * 
     * @param array $filters Filtry daty (date_from, date_to), limit, invoice_id, expense_id, income_id
     * @return array Lista terminów
     */
    public function getTerms(array $filters = []): array
    {
        try {
            return $this->findModuleData('terms', 'term', $filters);
        } catch (Exception $e) {
            Log::error('wFirma getTerms error', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            return [];
        }
    }

    /**
     * Pobiera zawartość faktury (pozycje faktury) z wFirma
     * 
     * @param string|int $invoiceId ID faktury w wFirma
     * @return array Lista pozycji faktury
     */
    public function getInvoiceContents(string|int $invoiceId): array
    {
        try {
            $response = $this->makeRequest('GET', "/invoices/{$invoiceId}");
            
            if ($response && isset($response['invoice']['invoicecontents'])) {
                $contents = $response['invoice']['invoicecontents'];
                
                // Jeśli to tablica z indeksami numerycznymi
                if (isset($contents['invoicecontent'])) {
                    $content = $contents['invoicecontent'];
                    if (isset($content[0])) {
                        return $content;
                    }
                    return [$content];
                }
                
                // Jeśli pozycje są bezpośrednio w tablicy
                if (is_array($contents)) {
                    return $contents;
                }
            }
            
            return [];
        } catch (Exception $e) {
            Log::error('wFirma getInvoiceContents error', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoiceId,
            ]);
            return [];
        }
    }

    /**
     * Pobiera części wydatku (pozycje wydatku) z wFirma
     * 
     * @param string|int $expenseId ID wydatku w wFirma
     * @return array Lista pozycji wydatku
     */
    public function getExpenseParts(string|int $expenseId): array
    {
        try {
            $response = $this->makeRequest('GET', "/expenses/{$expenseId}");
            
            if ($response && isset($response['expense']['expense_parts'])) {
                $parts = $response['expense']['expense_parts'];
                
                // Jeśli to tablica z indeksami numerycznymi
                if (isset($parts['expense_part'])) {
                    $part = $parts['expense_part'];
                    if (isset($part[0])) {
                        return $part;
                    }
                    return [$part];
                }
                
                // Jeśli pozycje są bezpośrednio w tablicy
                if (is_array($parts)) {
                    return $parts;
                }
            }
            
            return [];
        } catch (Exception $e) {
            Log::error('wFirma getExpenseParts error', [
                'error' => $e->getMessage(),
                'expense_id' => $expenseId,
            ]);
            return [];
        }
    }

    /**
     * Pobiera pojedynczą fakturę sprzedażową z wFirma
     * 
     * @param string|int $invoiceId ID faktury w wFirma
     * @return array|null Dane faktury lub null jeśli nie znaleziono
     */
    public function getInvoice(string|int $invoiceId): ?array
    {
        try {
            $response = $this->makeRequest('GET', "/invoices/{$invoiceId}");
            
            if ($response && isset($response['invoice'])) {
                return $response['invoice'];
            }
            
            return null;
        } catch (Exception $e) {
            Log::error('wFirma getInvoice error', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoiceId,
            ]);
            return null;
        }
    }

    /**
     * Pobiera pojedynczy wydatek z wFirma
     * 
     * @param string|int $expenseId ID wydatku w wFirma
     * @return array|null Dane wydatku lub null jeśli nie znaleziono
     */
    public function getExpense(string|int $expenseId): ?array
    {
        try {
            $response = $this->makeRequest('GET', "/expenses/{$expenseId}");
            
            if ($response && isset($response['expense'])) {
                return $response['expense'];
            }
            
            return null;
        } catch (Exception $e) {
            Log::error('wFirma getExpense error', [
                'error' => $e->getMessage(),
                'expense_id' => $expenseId,
            ]);
            return null;
        }
    }

    /**
     * Pobiera pojedynczy przychód z wFirma
     * 
     * @param string|int $incomeId ID przychodu w wFirma
     * @return array|null Dane przychodu lub null jeśli nie znaleziono
     */
    public function getIncome(string|int $incomeId): ?array
    {
        try {
            $response = $this->makeRequest('GET', "/incomes/{$incomeId}");
            
            if ($response && isset($response['income'])) {
                return $response['income'];
            }
            
            return null;
        } catch (Exception $e) {
            Log::error('wFirma getIncome error', [
                'error' => $e->getMessage(),
                'income_id' => $incomeId,
            ]);
            return null;
        }
    }

    /**
     * Pobiera pojedynczą płatność z wFirma
     * 
     * @param string|int $paymentId ID płatności w wFirma
     * @return array|null Dane płatności lub null jeśli nie znaleziono
     */
    public function getPayment(string|int $paymentId): ?array
    {
        try {
            $response = $this->makeRequest('GET', "/payments/{$paymentId}");
            
            if ($response && isset($response['payment'])) {
                return $response['payment'];
            }
            
            return null;
        } catch (Exception $e) {
            Log::error('wFirma getPayment error', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
            ]);
            return null;
        }
    }

    /**
     * Pobiera pojedynczy termin z wFirma
     * 
     * @param string|int $termId ID terminu w wFirma
     * @return array|null Dane terminu lub null jeśli nie znaleziono
     */
    public function getTerm(string|int $termId): ?array
    {
        try {
            $response = $this->makeRequest('GET', "/terms/{$termId}");
            
            if ($response && isset($response['term'])) {
                return $response['term'];
            }
            
            return null;
        } catch (Exception $e) {
            Log::error('wFirma getTerm error', [
                'error' => $e->getMessage(),
                'term_id' => $termId,
            ]);
            return null;
        }
    }

    /**
     * Pobiera pojedyncze rozliczenie ZUS z wFirma
     * 
     * @param string|int $interestId ID rozliczenia w wFirma
     * @return array|null Dane rozliczenia lub null jeśli nie znaleziono
     */
    public function getInterest(string|int $interestId): ?array
    {
        try {
            $response = $this->makeRequest('GET', "/interests/{$interestId}");
            
            if ($response && isset($response['interest'])) {
                return $response['interest'];
            }
            
            return null;
        } catch (Exception $e) {
            Log::error('wFirma getInterest error', [
                'error' => $e->getMessage(),
                'interest_id' => $interestId,
            ]);
            return null;
        }
    }

    /**
     * Tworzy nową transakcję bankową w wFirma
     */
    public function createBankTransaction(array $transactionData): ?array
    {
        try {
            $response = $this->makeRequest('POST', '/bank_transactions', [
                'bank_transaction' => $transactionData
            ]);

            if ($response && isset($response['bank_transaction'])) {
                return $response['bank_transaction'];
            }

            return null;
        } catch (Exception $e) {
            Log::error('wFirma createBankTransaction error', [
                'error' => $e->getMessage(),
                'data' => $transactionData,
            ]);
            return null;
        }
    }

    /**
     * Aktualizuje transakcję bankową w wFirma
     */
    public function updateBankTransaction(string $transactionId, array $transactionData): ?array
    {
        try {
            $response = $this->makeRequest('PUT', "/bank_transactions/{$transactionId}", [
                'bank_transaction' => $transactionData
            ]);

            if ($response && isset($response['bank_transaction'])) {
                return $response['bank_transaction'];
            }

            return null;
        } catch (Exception $e) {
            Log::error('wFirma updateBankTransaction error', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'data' => $transactionData,
            ]);
            return null;
        }
    }

    /**
     * Pobiera kategorie z wFirma
     */
    public function getCategories(): array
    {
        try {
            $response = $this->makeRequest('GET', '/categories');

            if ($response && isset($response['categories'])) {
                return $response['categories'];
            }

            return [];
        } catch (Exception $e) {
            Log::error('wFirma getCategories error', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Pobiera kontrahentów z wFirma
     */
    public function getContractors(): array
    {
        try {
            $response = $this->makeRequest('GET', '/contractors');

            if ($response && isset($response['contractors'])) {
                return $response['contractors'];
            }

            return [];
        } catch (Exception $e) {
            Log::error('wFirma getContractors error', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Tworzy nowego kontrahenta w wFirma
     */
    public function createContractor(array $contractorData): ?array
    {
        try {
            $response = $this->makeRequest('POST', '/contractors', [
                'contractor' => $contractorData
            ]);

            if ($response && isset($response['contractor'])) {
                return $response['contractor'];
            }

            return null;
        } catch (Exception $e) {
            Log::error('wFirma createContractor error', [
                'error' => $e->getMessage(),
                'data' => $contractorData,
            ]);
            return null;
        }
    }

    /**
     * Tworzy nową fakturę w wFirma
     */
    public function createInvoice(array $invoiceData): ?array
    {
        try {
            $response = $this->makeRequest('POST', '/invoices', [
                'invoice' => $invoiceData
            ]);

            if ($response && isset($response['invoice'])) {
                return $response['invoice'];
            }

            return null;
        } catch (Exception $e) {
            Log::error('wFirma createInvoice error', [
                'error' => $e->getMessage(),
                'data' => $invoiceData,
            ]);
            return null;
        }
    }

    /**
     * Pobiera fakturę w formacie PDF
     */
    public function downloadInvoicePdf(int $invoiceId): ?string
    {
        try {
            $url = $this->baseUrl . '/invoices/' . $invoiceId . '/download';
            
            if ($this->companyId) {
                $url .= (strpos($url, '?') !== false ? '&' : '?') . 'company_id=' . $this->companyId;
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'accessKey' => $this->accessKey,
                    'secretKey' => $this->secretKey,
                    'appKey' => $this->appKey,
                    'Accept' => 'application/pdf',
                ])
                ->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            Log::error('wFirma downloadInvoicePdf failed', [
                'invoice_id' => $invoiceId,
                'status' => $response->status(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('wFirma downloadInvoicePdf error', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Wykonuje zapytanie do API wFirma z autoryzacją OAuth 1.0
     */
    private function makeRequest(string $method, string $endpoint, array $params = []): ?array
    {
        $baseUrl = $this->baseUrl . $endpoint;
        
        // Dla GET - parametry w URL, dla POST/PUT - w body jako JSON
        $queryParams = [];
        $bodyParams = [];
        
        if ($method === 'GET') {
            $queryParams = $params;
        } else {
            $bodyParams = $params;
        }
        
        // Dodaj company_id do parametrów query (wymagane przez wFirma)
        if ($this->companyId) {
            $queryParams['company_id'] = $this->companyId;
        }
        
        // Utwórz pełny URL z parametrami query
        $url = $baseUrl;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        // wFirma używa API Key autoryzacji (nie OAuth 1.0!)
        // Wymagane nagłówki: accessKey, secretKey, appKey
        // Dla POST z conditions może wymagać XML zamiast JSON
        $useXml = $method === 'POST' && !empty($bodyParams);
        
        $headers = [
            'accessKey' => $this->accessKey,
            'secretKey' => $this->secretKey,
            'appKey' => $this->appKey,
        ];
        
        if ($useXml) {
            $headers['Content-Type'] = 'application/xml';
            $headers['Accept'] = 'application/xml';
        } else {
            $headers['Content-Type'] = 'application/json';
            $headers['Accept'] = 'application/json';
        }
        
        // Debug: loguj nagłówki (tylko w development, bez wartości kluczy)
        if (config('app.debug')) {
            Log::debug('wFirma API Key Headers', [
                'method' => $method,
                'base_url' => $baseUrl,
                'query_params' => $queryParams,
                'accessKey_set' => !empty($this->accessKey),
                'secretKey_set' => !empty($this->secretKey),
                'appKey_set' => !empty($this->appKey),
            ]);
        }

        try {
            $request = Http::timeout($this->timeout)
                ->retry($this->retryAttempts, 1000)
                ->withHeaders($headers);

            if ($method === 'GET') {
                // Dla GET parametry są już w URL
                $response = $request->get($url);
            } elseif ($method === 'POST') {
                if ($useXml) {
                    // Konwertuj bodyParams na XML
                    $xmlBody = $this->arrayToXml($bodyParams);
                    $response = $request->withBody($xmlBody, 'application/xml')->post($url);
                } else {
                    $response = $request->post($url, $bodyParams);
                }
            } elseif ($method === 'PUT') {
                if ($useXml) {
                    $xmlBody = $this->arrayToXml($bodyParams);
                    $response = $request->withBody($xmlBody, 'application/xml')->put($url);
                } else {
                    $response = $request->put($url, $bodyParams);
                }
            } elseif ($method === 'DELETE') {
                $response = $request->delete($url);
            } else {
                throw new Exception("Unsupported HTTP method: {$method}");
            }

            if ($response->successful()) {
                // wFirma może zwracać XML lub JSON - sprawdź content-type
                $contentType = $response->header('Content-Type');
                if (strpos($contentType, 'xml') !== false || $useXml) {
                    // Parsuj XML
                    $xml = simplexml_load_string($response->body());
                    if ($xml) {
                        return json_decode(json_encode($xml), true);
                    }
                }
                return $response->json();
            }

            Log::error('wFirma API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'url' => $url,
                'response' => $response->body(),
                'status' => $response->status(),
                'headers' => $response->headers(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('wFirma API request error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }


    /**
     * Uniwersalna metoda do pobierania danych z modułu wFirma z filtrowaniem
     * 
     * @param string $moduleName Nazwa modułu wFirma (np. 'invoices', 'expenses', 'payments')
     * @param string $itemName Nazwa pojedynczego elementu w odpowiedzi (np. 'invoice', 'expense', 'payment')
     * @param array $filters Filtry: date_from, date_to, limit, oraz dodatkowe filtry specyficzne dla modułu
     * @return array Lista elementów
     */
    private function findModuleData(string $moduleName, string $itemName, array $filters = []): array
    {
        $requestData = [];
        $conditions = [];
        $hasConditions = false;
        
        // Filtry daty
        if (isset($filters['date_from'])) {
            $conditions[] = [
                'field' => 'date',
                'operator' => 'ge', // greater or equal
                'value' => $filters['date_from'],
            ];
            $hasConditions = true;
        }
        
        if (isset($filters['date_to'])) {
            $conditions[] = [
                'field' => 'date',
                'operator' => 'le', // less or equal
                'value' => $filters['date_to'],
            ];
            $hasConditions = true;
        }
        
        // Dodatkowe filtry specyficzne dla modułu (np. invoice_id, expense_id, income_id)
        $additionalFilters = ['invoice_id', 'expense_id', 'income_id', 'contractor_id', 'paymentstate', 'paid', 'type', 'period', 'zus_type'];
        foreach ($additionalFilters as $filterKey) {
            if (isset($filters[$filterKey])) {
                $conditions[] = [
                    'field' => $filterKey,
                    'operator' => 'eq', // equal
                    'value' => $filters[$filterKey],
                ];
                $hasConditions = true;
            }
        }
        
        // Jeśli są warunki, użyj struktury conditions
        if ($hasConditions) {
            $requestData = [
                $moduleName => [
                    'parameters' => [
                        'conditions' => [
                            'condition' => $conditions,
                        ],
                    ],
                ],
            ];
            
            // Dodaj limit jeśli jest
            if (isset($filters['limit'])) {
                $requestData[$moduleName]['parameters']['limit'] = $filters['limit'];
            }
        } else {
            // Bez warunków - użyj prostych parametrów
            if (isset($filters['limit'])) {
                $requestData = [
                    $moduleName => [
                        'parameters' => [
                            'limit' => $filters['limit'],
                        ],
                    ],
                ];
            }
        }
        
        // wFirma wymaga POST dla find z conditions, GET dla prostych zapytań
        $method = $hasConditions ? 'POST' : 'GET';
        
        $response = $this->makeRequest($method, "/{$moduleName}/find", $requestData);
        
        // Sprawdź status odpowiedzi
        if ($response && isset($response['status'])) {
            $status = $response['status'];
            $code = is_array($status) ? ($status['code'] ?? null) : $status;
            
            if ($code && $code !== 'OK') {
                Log::warning("wFirma {$moduleName} status", [
                    'status_code' => $code,
                    'full_status' => $status,
                ]);
            }
        }
        
        if ($response && isset($response[$moduleName])) {
            $items = $response[$moduleName];
            
            // Sprawdź czy to tylko metadane (parameters) bez danych
            if (isset($items['parameters']) && !isset($items[$itemName])) {
                // Brak danych - zwróć pustą tablicę
                return [];
            }
            
            // Jeśli jest pole z nazwą pojedynczej pozycji (np. 'expense', 'invoice')
            if (isset($items[$itemName])) {
                $item = $items[$itemName];
                // Jeśli to tablica z indeksami numerycznymi
                if (isset($item[0])) {
                    return $item;
                }
                // Jeśli to pojedyncza pozycja
                return [$item];
            }
            
            // Jeśli pozycje są bezpośrednio w tablicy (ale nie parameters)
            if (is_array($items) && !isset($items['parameters'])) {
                return $items;
            }
        }
        
        return [];
    }

    /**
     * Konwertuje tablicę na XML dla wFirma API
     */
    private function arrayToXml(array $data, string $rootElement = 'api'): string
    {
        $xml = new \SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><{$rootElement}></{$rootElement}>");
        $this->arrayToXmlRecursive($data, $xml);
        return $xml->asXML();
    }

    /**
     * Rekurencyjnie konwertuje tablicę na XML
     */
    private function arrayToXmlRecursive(array $data, \SimpleXMLElement &$xml): void
    {
        foreach ($data as $key => $value) {
            // Obsługa kluczy numerycznych (dla tablic)
            if (is_numeric($key)) {
                $key = 'item';
            }
            
            if (is_array($value)) {
                // Jeśli wartość jest tablicą, sprawdź czy to tablica asocjacyjna czy numeryczna
                if (isset($value[0]) && is_numeric(array_keys($value)[0])) {
                    // Tablica numeryczna - każdy element jako osobny węzeł
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $subnode = $xml->addChild($key);
                            $this->arrayToXmlRecursive($item, $subnode);
                        } else {
                            $xml->addChild($key, htmlspecialchars($item));
                        }
                    }
                } else {
                    // Tablica asocjacyjna - rekurencyjnie
                    $subnode = $xml->addChild($key);
                    $this->arrayToXmlRecursive($value, $subnode);
                }
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }

    /**
     * Importuje transakcję z wFirma do lokalnej bazy
     */
    private function importTransaction(BankAccount $account, array $transactionData): bool
    {
        try {
            $existingTransaction = Transaction::where('external_id', $transactionData['id'])
                ->where('bank_account_id', $account->id)
                ->first();

            if ($existingTransaction) {
                return false; // Already imported
            }

            Transaction::create([
                'user_id' => $account->user_id,
                'bank_account_id' => $account->id,
                'external_id' => $transactionData['id'],
                'description' => $transactionData['description'] ?? $transactionData['title'] ?? 'Unknown',
                'amount' => $transactionData['amount'] ?? 0,
                'currency' => $transactionData['currency'] ?? 'PLN',
                'transaction_date' => $transactionData['date'] ?? now(),
                'type' => ($transactionData['amount'] ?? 0) >= 0 ? 'credit' : 'debit',
                'status' => 'completed',
                'merchant_name' => $transactionData['contractor_name'] ?? null,
                'reference' => $transactionData['reference'] ?? null,
                'notes' => $transactionData['notes'] ?? null,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to import wFirma transaction', [
                'transaction_data' => $transactionData,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Pobiera raporty finansowe z wFirma
     */
    public function getFinancialReports(string $reportType, array $filters = []): ?array
    {
        try {
            $response = $this->makeRequest('GET', "/reports/{$reportType}", $filters);

            if ($response && isset($response['report'])) {
                return $response['report'];
            }

            return null;
        } catch (Exception $e) {
            Log::error('wFirma getFinancialReports error', [
                'error' => $e->getMessage(),
                'report_type' => $reportType,
            ]);
            return null;
        }
    }

    /**
     * Pobiera statystyki finansowe z wFirma
     */
    public function getFinancialStatistics(array $filters = []): ?array
    {
        try {
            $response = $this->makeRequest('GET', '/statistics', $filters);

            if ($response && isset($response['statistics'])) {
                return $response['statistics'];
            }

            return null;
        } catch (Exception $e) {
            Log::error('wFirma getFinancialStatistics error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Weryfikuje połączenie z wFirma API
     */
    public function testConnection(): bool
    {
        try {
            // Sprawdź czy company_id jest ustawione
            if (empty($this->companyId)) {
                Log::error('wFirma connection test failed - Company ID is required');
                return false;
            }
            
            // Test połączenia - próba pobrania listy kontrahentów (lub innego prostego endpointu)
            $response = $this->makeRequest('GET', '/contractors', ['limit' => 1]);
            return $response !== null;
        } catch (Exception $e) {
            Log::error('wFirma connection test failed', [
                'error' => $e->getMessage(),
                'company_id_set' => !empty($this->companyId),
            ]);
            return false;
        }
    }
} 