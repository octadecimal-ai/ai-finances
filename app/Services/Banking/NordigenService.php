<?php

namespace App\Services\Banking;

use App\Models\BankAccount;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NordigenService
{
    private string $baseUrl;
    private string $secretId;
    private string $secretKey;
    private int $timeout;
    private int $retryAttempts;

    public function __construct()
    {
        $this->baseUrl = config('banking.nordigen.base_url');
        $this->secretId = config('banking.nordigen.secret_id') ?? '';
        $this->secretKey = config('banking.nordigen.secret_key') ?? '';
        $this->timeout = config('banking.nordigen.timeout', 30);
        $this->retryAttempts = config('banking.nordigen.retry_attempts', 3);
    }

    /**
     * Authenticate with Nordigen API
     */
    public function authenticate(): ?string
    {
        // Check if we have a cached token
        $cachedToken = Cache::get('nordigen_access_token');
        if ($cachedToken) {
            return $cachedToken;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/token/new/', [
                    'secret_id' => $this->secretId,
                    'secret_key' => $this->secretKey,
                ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                $accessToken = $tokenData['access'];
                
                // Cache token for 23 hours (tokens expire in 24 hours)
                Cache::put('nordigen_access_token', $accessToken, 23 * 3600);
                
                return $accessToken;
            }

            Log::error('Nordigen authentication failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Nordigen authentication exception', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get access token (authenticate if needed)
     */
    private function getAccessToken(): ?string
    {
        return $this->authenticate();
    }

    /**
     * Get available institutions
     */
    public function getInstitutions(): array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            return [];
        }

        try {
            $response = Http::withToken($token)
                ->timeout($this->timeout)
                ->get($this->baseUrl . '/institutions/');

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Nordigen get institutions failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];

        } catch (\Exception $e) {
            Log::error('Nordigen get institutions exception', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Create a new requisition
     */
    public function createRequisition(string $institutionId, string $redirectUrl): ?array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            return null;
        }

        try {
            $response = Http::withToken($token)
                ->timeout($this->timeout)
                ->post($this->baseUrl . '/requisitions/', [
                    'institution_id' => $institutionId,
                    'redirect' => $redirectUrl,
                    'user_language' => 'PL',
                    'reference' => 'finances_app_' . time(),
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Nordigen create requisition failed', [
                'institution_id' => $institutionId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Nordigen create requisition exception', [
                'institution_id' => $institutionId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get accounts from requisition
     */
    public function getAccounts(): array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            return [];
        }

        try {
            $response = Http::withToken($token)
                ->timeout($this->timeout)
                ->get($this->baseUrl . '/accounts/');

            if ($response->successful()) {
                return $response->json('accounts', []);
            }

            Log::error('Nordigen get accounts failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];

        } catch (\Exception $e) {
            Log::error('Nordigen get accounts exception', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get transactions for an account
     */
    public function getTransactions(string $accountId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            return [];
        }

        $params = [];
        if ($dateFrom) {
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo) {
            $params['date_to'] = $dateTo;
        }

        try {
            $response = Http::withToken($token)
                ->timeout($this->timeout)
                ->get($this->baseUrl . "/accounts/{$accountId}/transactions/", $params);

            if ($response->successful()) {
                return $response->json('transactions', []);
            }

            Log::error('Nordigen get transactions failed', [
                'account_id' => $accountId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];

        } catch (\Exception $e) {
            Log::error('Nordigen get transactions exception', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get account details
     */
    public function getAccount(string $accountId): ?array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            return null;
        }

        try {
            $response = Http::withToken($token)
                ->timeout($this->timeout)
                ->get($this->baseUrl . "/accounts/{$accountId}/");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Nordigen get account failed', [
                'account_id' => $accountId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Nordigen get account exception', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Sync account with local database
     */
    public function syncAccount($account): array
    {
        $accountId = $account->provider_account_id;
        $transactions = $this->getTransactions($accountId);
        
        $importedCount = 0;
        $errors = [];

        foreach ($transactions as $transactionData) {
            try {
                $imported = $this->importTransaction($account, $transactionData);
                if ($imported) {
                    $importedCount++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'transaction_id' => $transactionData['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'imported_count' => $importedCount,
            'total_count' => count($transactions),
            'errors' => $errors
        ];
    }

    /**
     * Import single transaction
     */
    public function importTransaction($account, array $transactionData): bool
    {
        // Check if transaction already exists
        $existingTransaction = Transaction::where('external_id', $transactionData['id'])
            ->where('bank_account_id', $account->id)
            ->first();

        if ($existingTransaction) {
            return false; // Already imported
        }

        // Create new transaction
        $transaction = Transaction::create([
            'user_id' => $account->user_id,
            'bank_account_id' => $account->id,
            'external_id' => $transactionData['id'],
            'provider' => 'nordigen',
            'type' => $transactionData['transaction_type'] === 'credit' ? 'credit' : 'debit',
            'amount' => abs($transactionData['amount']),
            'currency' => $transactionData['currency'],
            'description' => $transactionData['description'] ?? '',
            'merchant_name' => $transactionData['merchant_name'] ?? null,
            'transaction_date' => $transactionData['booking_date'],
            'status' => $transactionData['status'],
            'reference' => $transactionData['reference'] ?? null,
            'balance_after' => $transactionData['balance'] ?? null,
            'metadata' => [
                'nordigen_id' => $transactionData['id'],
                'transaction_type' => $transactionData['transaction_type'],
                'value_date' => $transactionData['value_date'] ?? null,
                'remittance_information' => $transactionData['remittance_information'] ?? null,
            ]
        ]);

        return true;
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = config('banking.nordigen.webhook_secret');
        
        if (!$secret) {
            Log::warning('Nordigen webhook secret not configured');
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process webhook from Nordigen
     */
    public function processWebhook(array $data): bool
    {
        try {
            $eventType = $data['event'] ?? '';
            
            switch ($eventType) {
                case 'TRANSACTION_CREATED':
                case 'TRANSACTION_UPDATED':
                    return $this->processTransactionWebhook($data);
                    
                case 'ACCOUNT_CREATED':
                case 'ACCOUNT_UPDATED':
                    return $this->processAccountWebhook($data);
                    
                default:
                    Log::info('Nordigen webhook received', [
                        'type' => $eventType,
                        'data' => $data
                    ]);
                    return true;
            }

        } catch (\Exception $e) {
            Log::error('Nordigen webhook processing failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Process transaction webhook
     */
    private function processTransactionWebhook(array $data): bool
    {
        $transactionData = $data['data'] ?? [];
        $accountId = $transactionData['account_id'] ?? null;
        
        if (!$accountId) {
            return false;
        }

        // Find account
        $account = BankAccount::where('provider_account_id', $accountId)
            ->where('provider', 'nordigen')
            ->first();

        if (!$account) {
            Log::warning('Nordigen webhook: Account not found', [
                'account_id' => $accountId
            ]);
            return false;
        }

        // Import transaction
        return $this->importTransaction($account, $transactionData);
    }

    /**
     * Process account webhook
     */
    private function processAccountWebhook(array $data): bool
    {
        $accountData = $data['data'] ?? [];
        $accountId = $accountData['id'] ?? null;
        
        if (!$accountId) {
            return false;
        }

        // Find and update account
        $account = BankAccount::where('provider_account_id', $accountId)
            ->where('provider', 'nordigen')
            ->first();

        if ($account) {
            $account->update([
                'name' => $accountData['name'] ?? $account->name,
                'balance' => $accountData['balance'] ?? $account->balance,
                'status' => $accountData['status'] ?? $account->status,
                'last_sync_at' => now(),
            ]);
        }

        return true;
    }
} 