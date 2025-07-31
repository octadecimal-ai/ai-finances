<?php

namespace App\Services\Banking;

use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class NordigenService
{
    private string $baseUrl;
    private string $secretId;
    private string $secretKey;
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->baseUrl = config('banking.nordigen.base_url');
        $this->secretId = config('banking.nordigen.secret_id');
        $this->secretKey = config('banking.nordigen.secret_key');
    }

    public function authenticate(): bool
    {
        try {
            $response = Http::timeout(config('banking.nordigen.timeout'))
                ->post($this->baseUrl . '/token/new/', [
                    'secret_id' => $this->secretId,
                    'secret_key' => $this->secretKey,
                ]);

            if ($response->successful()) {
                $this->accessToken = $response->json('access');
                return true;
            }

            Log::error('Nordigen authentication failed', [
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Nordigen authentication error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getInstitutions(string $country = 'PL'): array
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(config('banking.nordigen.timeout'))
                ->get($this->baseUrl . "/institutions/?country={$country}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to fetch Nordigen institutions', [
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Nordigen institutions error', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function createRequisition(string $institutionId, string $redirectUrl): ?string
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(config('banking.nordigen.timeout'))
                ->post($this->baseUrl . '/requisitions/', [
                    'institution_id' => $institutionId,
                    'redirect' => $redirectUrl,
                    'user_language' => 'PL',
                ]);

            if ($response->successful()) {
                return $response->json('id');
            }

            Log::error('Failed to create Nordigen requisition', [
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Nordigen requisition creation error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getAccounts(string $requisitionId): array
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(config('banking.nordigen.timeout'))
                ->get($this->baseUrl . "/requisitions/{$requisitionId}/");

            if ($response->successful()) {
                return $response->json('accounts', []);
            }

            Log::error('Failed to fetch Nordigen accounts', [
                'requisition_id' => $requisitionId,
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Nordigen accounts error', [
                'error' => $e->getMessage(),
                'requisition_id' => $requisitionId,
            ]);
            return [];
        }
    }

    public function getTransactions(string $accountId, string $dateFrom = null, string $dateTo = null): array
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        $params = [];
        if ($dateFrom) {
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo) {
            $params['date_to'] = $dateTo;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(config('banking.nordigen.timeout'))
                ->get($this->baseUrl . "/accounts/{$accountId}/transactions/", $params);

            if ($response->successful()) {
                return $response->json('transactions', []);
            }

            Log::error('Failed to fetch Nordigen transactions', [
                'account_id' => $accountId,
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Nordigen transactions error', [
                'error' => $e->getMessage(),
                'account_id' => $accountId,
            ]);
            return [];
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

            Log::info('Nordigen account sync completed', [
                'account_id' => $account->id,
                'imported_transactions' => $importedCount,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Nordigen account sync failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function importTransaction(BankAccount $account, array $transactionData): bool
    {
        try {
            $existingTransaction = Transaction::where('external_id', $transactionData['transactionId'])
                ->where('bank_account_id', $account->id)
                ->first();

            if ($existingTransaction) {
                return false; // Already imported
            }

            Transaction::create([
                'user_id' => $account->user_id,
                'bank_account_id' => $account->id,
                'external_id' => $transactionData['transactionId'],
                'description' => $transactionData['remittanceInformationUnstructured'] ?? $transactionData['remittanceInformationStructured'] ?? 'Unknown',
                'amount' => $transactionData['transactionAmount']['amount'],
                'currency' => $transactionData['transactionAmount']['currency'],
                'transaction_date' => $transactionData['bookingDate'],
                'value_date' => $transactionData['valueDate'] ?? null,
                'type' => $transactionData['transactionAmount']['amount'] >= 0 ? 'credit' : 'debit',
                'status' => 'completed',
                'merchant_name' => $transactionData['debtorName'] ?? $transactionData['creditorName'] ?? null,
                'reference' => $transactionData['endToEndId'] ?? null,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to import Nordigen transaction', [
                'transaction_data' => $transactionData,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function getAccountBalance(string $accountId): float
    {
        if (!$this->accessToken) {
            $this->authenticate();
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(config('banking.nordigen.timeout'))
                ->get($this->baseUrl . "/accounts/{$accountId}/balances/");

            if ($response->successful()) {
                $balances = $response->json('balances', []);
                foreach ($balances as $balance) {
                    if ($balance['balanceType'] === 'interimAvailable') {
                        return (float) $balance['balanceAmount']['amount'];
                    }
                }
            }

            return 0.0;
        } catch (Exception $e) {
            Log::error('Failed to get Nordigen account balance', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }
    }
} 