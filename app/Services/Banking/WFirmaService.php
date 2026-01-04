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
     * Pobiera faktury z wFirma
     */
    public function getInvoices(array $filters = []): array
    {
        try {
            // wFirma używa endpointu /invoices/find dla wyszukiwania
            // Dla filtrowania po dacie trzeba użyć struktury XML/JSON z conditions
            $requestData = [];
            
            // Jeśli są filtry daty, użyj struktury conditions
            if (isset($filters['date_from']) || isset($filters['date_to'])) {
                $conditions = [];
                
                if (isset($filters['date_from'])) {
                    $conditions[] = [
                        'field' => 'date',
                        'operator' => 'ge', // greater or equal
                        'value' => $filters['date_from'],
                    ];
                }
                
                if (isset($filters['date_to'])) {
                    $conditions[] = [
                        'field' => 'date',
                        'operator' => 'le', // less or equal
                        'value' => $filters['date_to'],
                    ];
                }
                
                // wFirma wymaga struktury: invoices > parameters > conditions > condition[]
                // (api wrapper jest dodawany automatycznie)
                $requestData = [
                    'invoices' => [
                        'parameters' => [
                            'conditions' => [
                                'condition' => $conditions,
                            ],
                        ],
                    ],
                ];
                
                // Dodaj limit jeśli jest
                if (isset($filters['limit'])) {
                    $requestData['invoices']['parameters']['limit'] = $filters['limit'];
                }
            } else {
                // Bez filtrów daty - użyj prostych parametrów
                if (isset($filters['limit'])) {
                    $requestData = [
                        'invoices' => [
                            'parameters' => [
                                'limit' => $filters['limit'],
                            ],
                        ],
                    ];
                }
            }
            
            // wFirma wymaga POST dla find z conditions, GET dla prostych zapytań
            $method = !empty($requestData) && isset($requestData['invoices']['parameters']['conditions']) ? 'POST' : 'GET';
            
            // Debug: loguj request data (tylko w development)
            if (config('app.debug')) {
                Log::debug('wFirma getInvoices request', [
                    'method' => $method,
                    'endpoint' => '/invoices/find',
                    'request_data' => $requestData,
                    'filters' => $filters,
                ]);
            }
            
            $response = $this->makeRequest($method, '/invoices/find', $requestData);
            
            // Debug: loguj response (tylko w development)
            if (config('app.debug')) {
                Log::debug('wFirma getInvoices response', [
                    'response_keys' => $response ? array_keys($response) : [],
                    'has_invoices' => isset($response['invoices']),
                    'status' => $response['status'] ?? null,
                    'response_sample' => $response ? json_encode(array_slice($response, 0, 3)) : null,
                ]);
            }
            
            // Sprawdź status odpowiedzi
            if ($response && isset($response['status'])) {
                $status = $response['status'];
                $code = is_array($status) ? ($status['code'] ?? null) : $status;
                
                if ($code && $code !== 'OK') {
                    Log::warning('wFirma getInvoices status', [
                        'status_code' => $code,
                        'full_status' => $status,
                    ]);
                }
            }

            if ($response && isset($response['invoices'])) {
                // wFirma zwraca faktury w strukturze: invoices > invoice (może być tablica lub pojedynczy obiekt)
                $invoices = $response['invoices'];
                
                // Jeśli jest pole 'invoice' (pojedyncza faktura lub tablica)
                if (isset($invoices['invoice'])) {
                    $invoice = $invoices['invoice'];
                    // Jeśli to tablica z indeksami numerycznymi
                    if (isset($invoice[0])) {
                        return $invoice;
                    }
                    // Jeśli to pojedyncza faktura
                    return [$invoice];
                }
                
                // Jeśli faktury są bezpośrednio w tablicy
                if (is_array($invoices)) {
                    return $invoices;
                }
            }

            return [];
        } catch (Exception $e) {
            Log::error('wFirma getInvoices error', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            return [];
        }
    }

    /**
     * Pobiera wydatki z wFirma
     */
    public function getExpenses(array $filters = []): array
    {
        try {
            $response = $this->makeRequest('GET', '/expenses', $filters);

            if ($response && isset($response['expenses'])) {
                return $response['expenses'];
            }

            return [];
        } catch (Exception $e) {
            Log::error('wFirma getExpenses error', [
                'error' => $e->getMessage(),
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
            $response = $this->makeRequest('GET', '/incomes', $filters);

            if ($response && isset($response['incomes'])) {
                return $response['incomes'];
            }

            return [];
        } catch (Exception $e) {
            Log::error('wFirma getIncomes error', [
                'error' => $e->getMessage(),
            ]);
            return [];
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