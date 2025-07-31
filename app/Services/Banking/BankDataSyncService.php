<?php

namespace App\Services\Banking;

use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Notifications\SlackService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class BankDataSyncService
{
    private NordigenService $nordigenService;
    private RevolutService $revolutService;
    private WFirmaService $wfirmaService;
    private SlackService $slackService;

    public function __construct(
        NordigenService $nordigenService,
        RevolutService $revolutService,
        WFirmaService $wfirmaService,
        SlackService $slackService
    ) {
        $this->nordigenService = $nordigenService;
        $this->revolutService = $revolutService;
        $this->wfirmaService = $wfirmaService;
        $this->slackService = $slackService;
    }

    /**
     * Synchronizuje wszystkie konta bankowe użytkownika
     */
    public function syncAllAccounts(User $user): array
    {
        $results = [
            'total_accounts' => 0,
            'successful_syncs' => 0,
            'failed_syncs' => 0,
            'total_transactions' => 0,
            'errors' => [],
        ];

        $accounts = BankAccount::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('sync_enabled', true)
            ->get();

        $results['total_accounts'] = $accounts->count();

        foreach ($accounts as $account) {
            try {
                $syncResult = $this->syncAccount($account);
                
                if ($syncResult['success']) {
                    $results['successful_syncs']++;
                    $results['total_transactions'] += $syncResult['imported_transactions'];
                } else {
                    $results['failed_syncs']++;
                    $results['errors'][] = [
                        'account_id' => $account->id,
                        'account_name' => $account->account_name,
                        'error' => $syncResult['error'],
                    ];
                }
            } catch (Exception $e) {
                $results['failed_syncs']++;
                $results['errors'][] = [
                    'account_id' => $account->id,
                    'account_name' => $account->account_name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Send notification if enabled
        if ($results['successful_syncs'] > 0) {
            $this->slackService->notifySyncCompleted(
                $user,
                $results['total_transactions'],
                'multiple_providers'
            );
        }

        return $results;
    }

    /**
     * Synchronizuje konkretne konto bankowe
     */
    public function syncAccount(BankAccount $account): array
    {
        try {
            $user = $account->user;
            $provider = $account->provider;
            $importedTransactions = 0;

            switch ($provider) {
                case 'nordigen':
                    $success = $this->nordigenService->syncAccount($account);
                    if ($success) {
                        $importedTransactions = $this->countImportedTransactions($account);
                    }
                    break;

                case 'revolut':
                    $success = $this->revolutService->syncAccount($account);
                    if ($success) {
                        $importedTransactions = $this->countImportedTransactions($account);
                    }
                    break;

                case 'wfirma':
                    $success = $this->wfirmaService->syncAccount($account);
                    if ($success) {
                        $importedTransactions = $this->countImportedTransactions($account);
                    }
                    break;

                default:
                    throw new Exception("Nieobsługiwany dostawca: {$provider}");
            }

            if ($success) {
                // Send notification
                $this->slackService->notifySyncCompleted(
                    $user,
                    $importedTransactions,
                    $provider
                );

                return [
                    'success' => true,
                    'imported_transactions' => $importedTransactions,
                    'provider' => $provider,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "Synchronizacja nie powiodła się dla dostawcy: {$provider}",
                    'provider' => $provider,
                ];
            }
        } catch (Exception $e) {
            Log::error('Bank data sync failed', [
                'account_id' => $account->id,
                'provider' => $account->provider,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $account->provider,
            ];
        }
    }

    /**
     * Synchronizuje konta według dostawcy
     */
    public function syncByProvider(string $provider): array
    {
        $accounts = BankAccount::where('provider', $provider)
            ->where('is_active', true)
            ->where('sync_enabled', true)
            ->get();

        $results = [
            'provider' => $provider,
            'total_accounts' => $accounts->count(),
            'successful_syncs' => 0,
            'failed_syncs' => 0,
            'total_transactions' => 0,
            'errors' => [],
        ];

        foreach ($accounts as $account) {
            try {
                $syncResult = $this->syncAccount($account);
                
                if ($syncResult['success']) {
                    $results['successful_syncs']++;
                    $results['total_transactions'] += $syncResult['imported_transactions'];
                } else {
                    $results['failed_syncs']++;
                    $results['errors'][] = [
                        'account_id' => $account->id,
                        'account_name' => $account->account_name,
                        'error' => $syncResult['error'],
                    ];
                }
            } catch (Exception $e) {
                $results['failed_syncs']++;
                $results['errors'][] = [
                    'account_id' => $account->id,
                    'account_name' => $account->account_name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Synchronizuje konta, które wymagają aktualizacji
     */
    public function syncPendingAccounts(): array
    {
        $accounts = BankAccount::where('is_active', true)
            ->where('sync_enabled', true)
            ->where(function ($query) {
                $query->whereNull('last_sync_at')
                    ->orWhere('last_sync_at', '<=', now()->subHours(config('banking.sync.interval', 3600)));
            })
            ->get();

        $results = [
            'total_accounts' => $accounts->count(),
            'successful_syncs' => 0,
            'failed_syncs' => 0,
            'total_transactions' => 0,
            'errors' => [],
        ];

        foreach ($accounts as $account) {
            try {
                $syncResult = $this->syncAccount($account);
                
                if ($syncResult['success']) {
                    $results['successful_syncs']++;
                    $results['total_transactions'] += $syncResult['imported_transactions'];
                } else {
                    $results['failed_syncs']++;
                    $results['errors'][] = [
                        'account_id' => $account->id,
                        'account_name' => $account->account_name,
                        'provider' => $account->provider,
                        'error' => $syncResult['error'],
                    ];
                }
            } catch (Exception $e) {
                $results['failed_syncs']++;
                $results['errors'][] = [
                    'account_id' => $account->id,
                    'account_name' => $account->account_name,
                    'provider' => $account->provider,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Testuje połączenie z dostawcą
     */
    public function testProviderConnection(string $provider): array
    {
        try {
            switch ($provider) {
                case 'nordigen':
                    $success = $this->nordigenService->authenticate();
                    break;

                case 'revolut':
                    $success = $this->revolutService->testConnection();
                    break;

                case 'wfirma':
                    $success = $this->wfirmaService->testConnection();
                    break;

                default:
                    return [
                        'success' => false,
                        'error' => "Nieobsługiwany dostawca: {$provider}",
                    ];
            }

            return [
                'success' => $success,
                'provider' => $provider,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $provider,
            ];
        }
    }

    /**
     * Pobiera instytucje bankowe z Nordigen
     */
    public function getNordigenInstitutions(string $country = 'PL'): array
    {
        return $this->nordigenService->getInstitutions($country);
    }

    /**
     * Tworzy requisition w Nordigen
     */
    public function createNordigenRequisition(string $institutionId, string $redirectUrl): ?string
    {
        return $this->nordigenService->createRequisition($institutionId, $redirectUrl);
    }

    /**
     * Pobiera konta z Nordigen requisition
     */
    public function getNordigenAccounts(string $requisitionId): array
    {
        return $this->nordigenService->getAccounts($requisitionId);
    }

    /**
     * Pobiera URL autoryzacji Revolut
     */
    public function getRevolutAuthorizationUrl(string $state = null): string
    {
        return $this->revolutService->getAuthorizationUrl($state);
    }

    /**
     * Wymienia kod autoryzacyjny Revolut na token
     */
    public function exchangeRevolutCode(string $code): bool
    {
        return $this->revolutService->exchangeCodeForToken($code);
    }

    /**
     * Pobiera konta z Revolut
     */
    public function getRevolutAccounts(): array
    {
        return $this->revolutService->getAccounts();
    }

    /**
     * Pobiera konta bankowe z wFirma
     */
    public function getWFirmaBankAccounts(): array
    {
        return $this->wfirmaService->getBankAccounts();
    }

    /**
     * Pobiera transakcje z wFirma
     */
    public function getWFirmaTransactions(string $accountId = null, string $fromDate = null, string $toDate = null): array
    {
        return $this->wfirmaService->getBankTransactions($accountId, $fromDate, $toDate);
    }

    /**
     * Liczy zaimportowane transakcje dla konta
     */
    private function countImportedTransactions(BankAccount $account): int
    {
        return Transaction::where('bank_account_id', $account->id)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();
    }

    /**
     * Pobiera statystyki synchronizacji
     */
    public function getSyncStatistics(): array
    {
        $totalAccounts = BankAccount::where('is_active', true)->count();
        $syncedAccounts = BankAccount::where('is_active', true)
            ->whereNotNull('last_sync_at')
            ->where('last_sync_at', '>=', now()->subDay())
            ->count();

        $totalTransactions = Transaction::count();
        $todayTransactions = Transaction::where('created_at', '>=', now()->startOfDay())->count();

        return [
            'total_accounts' => $totalAccounts,
            'synced_today' => $syncedAccounts,
            'sync_percentage' => $totalAccounts > 0 ? round(($syncedAccounts / $totalAccounts) * 100, 2) : 0,
            'total_transactions' => $totalTransactions,
            'transactions_today' => $todayTransactions,
        ];
    }

    /**
     * Pobiera logi synchronizacji
     */
    public function getSyncLogs(int $limit = 100): array
    {
        return DB::table('bank_accounts')
            ->select([
                'bank_accounts.id',
                'bank_accounts.account_name',
                'bank_accounts.provider',
                'bank_accounts.last_sync_at',
                'bank_accounts.balance',
                DB::raw('COUNT(transactions.id) as transaction_count')
            ])
            ->leftJoin('transactions', 'bank_accounts.id', '=', 'transactions.bank_account_id')
            ->where('bank_accounts.is_active', true)
            ->groupBy('bank_accounts.id', 'bank_accounts.account_name', 'bank_accounts.provider', 'bank_accounts.last_sync_at', 'bank_accounts.balance')
            ->orderBy('bank_accounts.last_sync_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
} 