<?php

namespace App\Services\Banking;

use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class RevolutService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private int $timeout;
    private int $retryAttempts;
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->baseUrl = config('banking.revolut.base_url');
        $this->clientId = config('banking.revolut.client_id');
        $this->clientSecret = config('banking.revolut.client_secret');
        $this->redirectUri = config('banking.revolut.redirect_uri');
        $this->timeout = config('banking.revolut.timeout', 30);
        $this->retryAttempts = config('banking.revolut.retry_attempts', 3);
    }

    public function getAuthorizationUrl(string $state = null): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'read',
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return $this->baseUrl . '/oauth/authorize?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code): bool
    {
        try {
            $response = Http::timeout($this->timeout)
                ->retry($this->retryAttempts, 1000)
                ->post($this->baseUrl . '/oauth/token', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri,
                ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                $this->accessToken = $tokenData['access_token'];
                
                // Cache token
                Cache::put('revolut_access_token', $this->accessToken, 3600); // 1 hour
                Cache::put('revolut_refresh_token', $tokenData['refresh_token'], 30 * 24 * 3600); // 30 days
                
                return true;
            }

            Log::error('Revolut token exchange failed', [
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Revolut token exchange error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function refreshToken(): bool
    {
        $refreshToken = Cache::get('revolut_refresh_token');
        
        if (!$refreshToken) {
            return false;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->retry($this->retryAttempts, 1000)
                ->post($this->baseUrl . '/oauth/token', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                $this->accessToken = $tokenData['access_token'];
                
                Cache::put('revolut_access_token', $this->accessToken, 3600);
                Cache::put('revolut_refresh_token', $tokenData['refresh_token'], 30 * 24 * 3600);
                
                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error('Revolut token refresh error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getAccounts(): array
    {
        if (!$this->ensureAuthenticated()) {
            return [];
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout($this->timeout)
                ->retry($this->retryAttempts, 1000)
                ->get($this->baseUrl . '/accounts');

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to fetch Revolut accounts', [
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Revolut accounts error', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getTransactions(string $accountId, string $fromDate = null, string $toDate = null): array
    {
        if (!$this->ensureAuthenticated()) {
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
            $response = Http::withToken($this->accessToken)
                ->timeout($this->timeout)
                ->retry($this->retryAttempts, 1000)
                ->get($this->baseUrl . "/accounts/{$accountId}/transactions", $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to fetch Revolut transactions', [
                'account_id' => $accountId,
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Revolut transactions error', [
                'error' => $e->getMessage(),
                'account_id' => $accountId,
            ]);
            return [];
        }
    }

    public function getAccountBalance(string $accountId): float
    {
        if (!$this->ensureAuthenticated()) {
            return 0.0;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout($this->timeout)
                ->retry($this->retryAttempts, 1000)
                ->get($this->baseUrl . "/accounts/{$accountId}/balance");

            if ($response->successful()) {
                $balanceData = $response->json();
                return (float) ($balanceData['balance'] ?? 0);
            }

            return 0.0;
        } catch (Exception $e) {
            Log::error('Failed to get Revolut account balance', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }
    }

    public function syncAccount(BankAccount $account): bool
    {
        try {
            $transactions = $this->getTransactions(
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
                'balance' => $this->getAccountBalance($account->provider_account_id),
            ]);

            Log::info('Revolut account sync completed', [
                'account_id' => $account->id,
                'imported_transactions' => $importedCount,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Revolut account sync failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function ensureAuthenticated(): bool
    {
        if ($this->accessToken) {
            return true;
        }

        // Try to get from cache
        $cachedToken = Cache::get('revolut_access_token');
        if ($cachedToken) {
            $this->accessToken = $cachedToken;
            return true;
        }

        // Try to refresh token
        return $this->refreshToken();
    }

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
                'description' => $transactionData['description'] ?? 'Unknown',
                'amount' => $transactionData['amount'] ?? 0,
                'currency' => $transactionData['currency'] ?? 'EUR',
                'transaction_date' => $transactionData['created_at'] ?? now(),
                'type' => ($transactionData['amount'] ?? 0) >= 0 ? 'credit' : 'debit',
                'status' => 'completed',
                'merchant_name' => $transactionData['merchant'] ?? null,
                'reference' => $transactionData['reference'] ?? null,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to import Revolut transaction', [
                'transaction_data' => $transactionData,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getWebhookSecret(): string
    {
        return config('banking.webhooks.secret', '');
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $this->getWebhookSecret());
        return hash_equals($expectedSignature, $signature);
    }

    public function processWebhook(array $data): bool
    {
        try {
            $eventType = $data['event'] ?? '';
            
            switch ($eventType) {
                case 'transaction.created':
                case 'transaction.updated':
                    return $this->processTransactionWebhook($data);
                    
                case 'account.updated':
                    return $this->processAccountWebhook($data);
                    
                default:
                    Log::info('Unhandled Revolut webhook event', [
                        'event' => $eventType,
                        'data' => $data,
                    ]);
                    return true;
            }
        } catch (Exception $e) {
            Log::error('Revolut webhook processing error', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            return false;
        }
    }

    private function processTransactionWebhook(array $data): bool
    {
        // Find account by provider_account_id
        $account = BankAccount::where('provider', 'revolut')
            ->where('provider_account_id', $data['account_id'] ?? '')
            ->first();

        if (!$account) {
            return false;
        }

        // Import the transaction
        return $this->importTransaction($account, $data['transaction'] ?? []);
    }

    private function processAccountWebhook(array $data): bool
    {
        $account = BankAccount::where('provider', 'revolut')
            ->where('provider_account_id', $data['account_id'] ?? '')
            ->first();

        if (!$account) {
            return false;
        }

        // Update account balance
        $account->update([
            'balance' => $this->getAccountBalance($account->provider_account_id),
        ]);

        return true;
    }

    /**
     * Testuje połączenie z Revolut API
     */
    public function testConnection(): bool
    {
        try {
            if (!$this->ensureAuthenticated()) {
                return false;
            }

            $response = Http::withToken($this->accessToken)
                ->timeout($this->timeout)
                ->get($this->baseUrl . '/accounts');

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Revolut connection test failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
} 