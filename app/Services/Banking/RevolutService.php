<?php

namespace App\Services\Banking;

use App\Models\BankAccount;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RevolutService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private int $timeout;
    private int $retryAttempts;

    public function __construct()
    {
        $this->baseUrl = config('banking.revolut.base_url');
        $this->clientId = config('banking.revolut.client_id') ?? '';
        $this->clientSecret = config('banking.revolut.client_secret') ?? '';
        $this->redirectUri = config('banking.revolut.redirect_uri') ?? '';
        $this->timeout = config('banking.revolut.timeout', 30);
        $this->retryAttempts = config('banking.revolut.retry_attempts', 3);
    }

    /**
     * Get authorization URL for OAuth 2.0 flow
     */
    public function getAuthorizationUrl(?string $state = null): string
    {
        $state = $state ?? bin2hex(random_bytes(16));
        
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'read:accounts read:transactions',
            'state' => $state,
        ];

        return $this->baseUrl . '/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(string $code): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/oauth/token', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri,
                ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                
                // Cache tokens
                Cache::put('revolut_access_token', $tokenData['access_token'], $tokenData['expires_in'] - 60);
                Cache::put('revolut_refresh_token', $tokenData['refresh_token'], 86400 * 30); // 30 days
                
                return $tokenData;
            }

            Log::error('Revolut token exchange failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Revolut token exchange exception', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshToken(): ?array
    {
        $refreshToken = Cache::get('revolut_refresh_token');
        
        if (!$refreshToken) {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl . '/oauth/token', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                
                // Cache new tokens
                Cache::put('revolut_access_token', $tokenData['access_token'], $tokenData['expires_in'] - 60);
                Cache::put('revolut_refresh_token', $tokenData['refresh_token'], 86400 * 30);
                
                return $tokenData;
            }

            Log::error('Revolut token refresh failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Revolut token refresh exception', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get access token (from cache or refresh)
     */
    private function getAccessToken(): ?string
    {
        $accessToken = Cache::get('revolut_access_token');
        
        if (!$accessToken) {
            $refreshed = $this->refreshToken();
            $accessToken = $refreshed['access_token'] ?? null;
        }
        
        return $accessToken;
    }

    /**
     * Get user accounts
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
                ->get($this->baseUrl . '/accounts');

            if ($response->successful()) {
                return $response->json('accounts', []);
            }

            Log::error('Revolut get accounts failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];

        } catch (\Exception $e) {
            Log::error('Revolut get accounts exception', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get account transactions
     */
    public function getTransactions(string $accountId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            return [];
        }

        $params = [];
        if ($fromDate) {
            $params['from'] = $fromDate;
        }
        if ($toDate) {
            $params['to'] = $toDate;
        }

        try {
            $response = Http::withToken($token)
                ->timeout($this->timeout)
                ->get($this->baseUrl . "/accounts/{$accountId}/transactions", $params);

            if ($response->successful()) {
                return $response->json('transactions', []);
            }

            Log::error('Revolut get transactions failed', [
                'account_id' => $accountId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];

        } catch (\Exception $e) {
            Log::error('Revolut get transactions exception', [
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
                ->get($this->baseUrl . "/accounts/{$accountId}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Revolut get account failed', [
                'account_id' => $accountId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Revolut get account exception', [
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
            'provider' => 'revolut',
            'type' => $transactionData['type'] === 'credit' ? 'credit' : 'debit',
            'amount' => abs($transactionData['amount']),
            'currency' => $transactionData['currency'],
            'description' => $transactionData['description'] ?? '',
            'merchant_name' => $transactionData['merchant'] ?? null,
            'transaction_date' => $transactionData['created_at'],
            'status' => $transactionData['state'],
            'reference' => $transactionData['reference'] ?? null,
            'balance_after' => $transactionData['balance'] ?? null,
            'metadata' => [
                'revolut_id' => $transactionData['id'],
                'category' => $transactionData['category'] ?? null,
                'counterparty' => $transactionData['counterparty'] ?? null,
            ]
        ]);

        return true;
    }

    /**
     * Process webhook from Revolut
     */
    public function processWebhook(array $data): bool
    {
        try {
            $eventType = $data['type'] ?? '';
            
            switch ($eventType) {
                case 'TransactionCreated':
                case 'TransactionUpdated':
                    return $this->processTransactionWebhook($data);
                    
                case 'AccountCreated':
                case 'AccountUpdated':
                    return $this->processAccountWebhook($data);
                    
                default:
                    Log::info('Revolut webhook received', [
                        'type' => $eventType,
                        'data' => $data
                    ]);
                    return true;
            }

        } catch (\Exception $e) {
            Log::error('Revolut webhook processing failed', [
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
            ->where('provider', 'revolut')
            ->first();

        if (!$account) {
            Log::warning('Revolut webhook: Account not found', [
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
            ->where('provider', 'revolut')
            ->first();

        if ($account) {
            $account->update([
                'name' => $accountData['name'] ?? $account->name,
                'balance' => $accountData['balance'] ?? $account->balance,
                'status' => $accountData['state'] ?? $account->status,
                'last_sync_at' => now(),
            ]);
        }

        return true;
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = config('banking.revolut.webhook_secret');
        
        if (!$secret) {
            Log::warning('Revolut webhook secret not configured');
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Test connection to Revolut API
     */
    public function testConnection(): bool
    {
        try {
            $token = $this->getAccessToken();
            
            if (!$token) {
                return false;
            }

            $response = Http::withToken($token)
                ->timeout($this->timeout)
                ->get($this->baseUrl . '/accounts');

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Revolut connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
} 