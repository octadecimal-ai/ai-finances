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
    private string $accessToken;
    private string $companyId;
    private int $timeout;
    private int $retryAttempts;

    public function __construct()
    {
        $this->baseUrl = config('banking.wfirma.base_url', 'https://api2.wfirma.pl');
        $this->accessToken = config('banking.wfirma.access_token');
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
            $response = $this->makeRequest('GET', '/invoices', $filters);

            if ($response && isset($response['invoices'])) {
                return $response['invoices'];
            }

            return [];
        } catch (Exception $e) {
            Log::error('wFirma getInvoices error', [
                'error' => $e->getMessage(),
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
     * Wykonuje zapytanie do API wFirma
     */
    private function makeRequest(string $method, string $endpoint, array $params = []): ?array
    {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        try {
            $request = Http::timeout($this->timeout)
                ->retry($this->retryAttempts, 1000)
                ->withHeaders($headers);

            if ($method === 'GET') {
                $response = $request->get($url, $params);
            } elseif ($method === 'POST') {
                $response = $request->post($url, $params);
            } elseif ($method === 'PUT') {
                $response = $request->put($url, $params);
            } elseif ($method === 'DELETE') {
                $response = $request->delete($url);
            } else {
                throw new Exception("Unsupported HTTP method: {$method}");
            }

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('wFirma API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('wFirma API request error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            return null;
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
            $response = $this->makeRequest('GET', '/companies/' . $this->companyId);
            return $response !== null;
        } catch (Exception $e) {
            Log::error('wFirma connection test failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
} 