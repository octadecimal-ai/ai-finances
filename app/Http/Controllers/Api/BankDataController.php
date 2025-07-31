<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\User;
use App\Services\Banking\BankDataSyncService;
use App\Services\Banking\NordigenService;
use App\Services\Banking\RevolutService;
use App\Services\Banking\WFirmaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BankDataController extends Controller
{
    private BankDataSyncService $syncService;
    private NordigenService $nordigenService;
    private RevolutService $revolutService;
    private WFirmaService $wfirmaService;

    public function __construct(
        BankDataSyncService $syncService,
        NordigenService $nordigenService,
        RevolutService $revolutService,
        WFirmaService $wfirmaService
    ) {
        $this->syncService = $syncService;
        $this->nordigenService = $nordigenService;
        $this->revolutService = $revolutService;
        $this->wfirmaService = $wfirmaService;
    }

    /**
     * Pobiera listę kont bankowych użytkownika
     */
    public function accounts(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $accounts = BankAccount::where('user_id', $user->id)
            ->with(['transactions' => function ($query) {
                $query->latest()->limit(10);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    /**
     * Pobiera szczegóły konta bankowego
     */
    public function showAccount(int $id): JsonResponse
    {
        $user = Auth::user();
        
        $account = BankAccount::where('user_id', $user->id)
            ->with(['transactions' => function ($query) {
                $query->latest()->limit(50);
            }])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $account,
        ]);
    }

    /**
     * Tworzy nowe konto bankowe
     */
    public function storeAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:34',
            'currency' => 'required|string|size:3',
            'provider' => 'required|in:nordigen,revolut,wfirma',
            'provider_account_id' => 'nullable|string|max:255',
            'balance' => 'nullable|numeric',
            'sync_enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        
        $account = BankAccount::create([
            'user_id' => $user->id,
            'bank_name' => $request->bank_name,
            'account_name' => $request->account_name,
            'account_number' => $request->account_number,
            'iban' => $request->iban,
            'currency' => $request->currency,
            'provider' => $request->provider,
            'provider_account_id' => $request->provider_account_id,
            'balance' => $request->balance ?? 0,
            'sync_enabled' => $request->sync_enabled ?? true,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $account,
            'message' => 'Konto bankowe zostało utworzone pomyślnie.',
        ], 201);
    }

    /**
     * Aktualizuje konto bankowe
     */
    public function updateAccount(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        
        $account = BankAccount::where('user_id', $user->id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'bank_name' => 'sometimes|required|string|max:255',
            'account_name' => 'sometimes|required|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:34',
            'currency' => 'sometimes|required|string|size:3',
            'balance' => 'nullable|numeric',
            'sync_enabled' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $account->update($request->only([
            'bank_name', 'account_name', 'account_number', 'iban',
            'currency', 'balance', 'sync_enabled', 'is_active',
        ]));

        return response()->json([
            'success' => true,
            'data' => $account,
            'message' => 'Konto bankowe zostało zaktualizowane pomyślnie.',
        ]);
    }

    /**
     * Usuwa konto bankowe
     */
    public function destroyAccount(int $id): JsonResponse
    {
        $user = Auth::user();
        
        $account = BankAccount::where('user_id', $user->id)
            ->findOrFail($id);

        $account->delete();

        return response()->json([
            'success' => true,
            'message' => 'Konto bankowe zostało usunięte pomyślnie.',
        ]);
    }

    /**
     * Synchronizuje konkretne konto bankowe
     */
    public function syncAccount(int $id): JsonResponse
    {
        $user = Auth::user();
        
        $account = BankAccount::where('user_id', $user->id)
            ->findOrFail($id);

        $result = $this->syncService->syncAccount($account);

        return response()->json([
            'success' => $result['success'],
            'data' => $result,
            'message' => $result['success'] 
                ? 'Synchronizacja zakończona pomyślnie.' 
                : 'Synchronizacja nie powiodła się.',
        ]);
    }

    /**
     * Synchronizuje wszystkie konta użytkownika
     */
    public function syncAllAccounts(): JsonResponse
    {
        $user = Auth::user();
        
        $result = $this->syncService->syncAllAccounts($user);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Synchronizacja wszystkich kont zakończona.',
        ]);
    }

    /**
     * Pobiera instytucje bankowe z Nordigen
     */
    public function institutions(Request $request): JsonResponse
    {
        $country = $request->get('country', 'PL');
        $institutions = $this->syncService->getNordigenInstitutions($country);

        return response()->json([
            'success' => true,
            'data' => $institutions,
        ]);
    }

    /**
     * Tworzy requisition w Nordigen
     */
    public function createRequisition(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'institution_id' => 'required|string',
            'redirect_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $requisitionId = $this->syncService->createNordigenRequisition(
            $request->institution_id,
            $request->redirect_url
        );

        if (!$requisitionId) {
            return response()->json([
                'success' => false,
                'message' => 'Nie udało się utworzyć requisition.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'requisition_id' => $requisitionId,
            ],
        ]);
    }

    /**
     * Pobiera konta z Nordigen requisition
     */
    public function getRequisition(string $id): JsonResponse
    {
        $accounts = $this->syncService->getNordigenAccounts($id);

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    /**
     * Pobiera URL autoryzacji Revolut
     */
    public function getRevolutAuthUrl(Request $request): JsonResponse
    {
        $state = $request->get('state');
        $authUrl = $this->syncService->getRevolutAuthorizationUrl($state);

        return response()->json([
            'success' => true,
            'data' => [
                'auth_url' => $authUrl,
            ],
        ]);
    }

    /**
     * Wymienia kod autoryzacyjny Revolut na token
     */
    public function exchangeRevolutCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $success = $this->syncService->exchangeRevolutCode($request->code);

        return response()->json([
            'success' => $success,
            'message' => $success 
                ? 'Autoryzacja Revolut zakończona pomyślnie.' 
                : 'Autoryzacja Revolut nie powiodła się.',
        ]);
    }

    /**
     * Pobiera konta z Revolut
     */
    public function getRevolutAccounts(): JsonResponse
    {
        $accounts = $this->syncService->getRevolutAccounts();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    /**
     * Pobiera konta bankowe z wFirma
     */
    public function getWFirmaAccounts(): JsonResponse
    {
        $accounts = $this->syncService->getWFirmaBankAccounts();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    /**
     * Pobiera transakcje z wFirma
     */
    public function getWFirmaTransactions(Request $request): JsonResponse
    {
        $accountId = $request->get('account_id');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $transactions = $this->syncService->getWFirmaTransactions($accountId, $fromDate, $toDate);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Testuje połączenie z dostawcą
     */
    public function testConnection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:nordigen,revolut,wfirma',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->syncService->testProviderConnection($request->provider);

        return response()->json([
            'success' => $result['success'],
            'data' => $result,
            'message' => $result['success'] 
                ? 'Połączenie z dostawcą działa poprawnie.' 
                : 'Połączenie z dostawcą nie powiodło się.',
        ]);
    }

    /**
     * Pobiera statystyki synchronizacji
     */
    public function getSyncStatistics(): JsonResponse
    {
        $statistics = $this->syncService->getSyncStatistics();

        return response()->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }

    /**
     * Pobiera logi synchronizacji
     */
    public function getSyncLogs(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 100);
        $logs = $this->syncService->getSyncLogs($limit);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Webhook dla Nordigen
     */
    public function nordigenWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Signature');

        // Verify webhook signature
        if (!$this->nordigenService->verifyWebhookSignature($payload, $signature)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature',
            ], 401);
        }

        $data = $request->json()->all();
        $success = $this->nordigenService->processWebhook($data);

        return response()->json([
            'success' => $success,
        ]);
    }

    /**
     * Webhook dla Revolut
     */
    public function revolutWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Revolut-Signature');

        // Verify webhook signature
        if (!$this->revolutService->verifyWebhookSignature($payload, $signature)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature',
            ], 401);
        }

        $data = $request->json()->all();
        $success = $this->revolutService->processWebhook($data);

        return response()->json([
            'success' => $success,
        ]);
    }
} 