<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Category;
use App\Services\AI\ClaudeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TransactionsController extends Controller
{
    private ClaudeService $claudeService;

    public function __construct(ClaudeService $claudeService)
    {
        $this->claudeService = $claudeService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = Transaction::where('user_id', $user->id)
            ->with(['category', 'bankAccount']);

        // Filters
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('date_from')) {
            $query->where('transaction_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('transaction_date', '<=', $request->date_to);
        }

        if ($request->has('amount_min')) {
            $query->where('amount', '>=', $request->amount_min);
        }

        if ($request->has('amount_max')) {
            $query->where('amount', '<=', $request->amount_max);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('merchant_name', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'transaction_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 50);
        $transactions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        
        $transaction = Transaction::where('user_id', $user->id)
            ->with(['category', 'bankAccount'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $transaction,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric',
            'currency' => 'required|string|size:3',
            'transaction_date' => 'required|date',
            'type' => 'required|in:credit,debit',
            'category_id' => 'nullable|exists:categories,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'merchant_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'description' => $request->description,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'transaction_date' => $request->transaction_date,
            'type' => $request->type,
            'category_id' => $request->category_id,
            'bank_account_id' => $request->bank_account_id,
            'merchant_name' => $request->merchant_name,
            'notes' => $request->notes,
            'status' => 'completed',
        ]);

        // Auto-analyze with Claude if enabled
        if (config('claude.features.transaction_analysis')) {
            $this->claudeService->analyzeTransaction($transaction);
        }

        return response()->json([
            'success' => true,
            'data' => $transaction->load(['category', 'bankAccount']),
            'message' => 'Transakcja została utworzona pomyślnie.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        
        $transaction = Transaction::where('user_id', $user->id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'description' => 'sometimes|required|string|max:500',
            'amount' => 'sometimes|required|numeric',
            'currency' => 'sometimes|required|string|size:3',
            'transaction_date' => 'sometimes|required|date',
            'type' => 'sometimes|required|in:credit,debit',
            'category_id' => 'nullable|exists:categories,id',
            'merchant_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $transaction->update($request->only([
            'description', 'amount', 'currency', 'transaction_date',
            'type', 'category_id', 'merchant_name', 'notes',
        ]));

        return response()->json([
            'success' => true,
            'data' => $transaction->load(['category', 'bankAccount']),
            'message' => 'Transakcja została zaktualizowana pomyślnie.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        
        $transaction = Transaction::where('user_id', $user->id)
            ->findOrFail($id);

        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transakcja została usunięta pomyślnie.',
        ]);
    }

    public function analyze(int $id): JsonResponse
    {
        $user = Auth::user();
        
        $transaction = Transaction::where('user_id', $user->id)
            ->findOrFail($id);

        $analysis = $this->claudeService->analyzeTransaction($transaction);

        return response()->json([
            'success' => true,
            'data' => [
                'transaction' => $transaction,
                'analysis' => $analysis,
            ],
        ]);
    }

    public function suggestCategory(int $id): JsonResponse
    {
        $user = Auth::user();
        
        $transaction = Transaction::where('user_id', $user->id)
            ->findOrFail($id);

        $suggestedCategory = $this->claudeService->suggestCategory($transaction);

        return response()->json([
            'success' => true,
            'data' => [
                'transaction' => $transaction,
                'suggested_category' => $suggestedCategory,
            ],
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = Transaction::where('user_id', $user->id);

        // Date range filter
        if ($request->has('date_from')) {
            $query->where('transaction_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('transaction_date', '<=', $request->date_to);
        }

        $statistics = [
            'total_transactions' => $query->count(),
            'total_income' => $query->income()->sum('amount'),
            'total_expenses' => abs($query->expense()->sum('amount')),
            'net_amount' => $query->sum('amount'),
            'average_transaction' => $query->avg('amount'),
            'by_category' => $query->with('category')
                ->selectRaw('category_id, SUM(amount) as total_amount, COUNT(*) as count')
                ->groupBy('category_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'category' => $item->category?->name ?? 'Nieprzypisana',
                        'total_amount' => $item->total_amount,
                        'count' => $item->count,
                    ];
                }),
            'by_month' => $query->selectRaw('YEAR(transaction_date) as year, MONTH(transaction_date) as month, SUM(amount) as total_amount')
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'period' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                        'total_amount' => $item->total_amount,
                    ];
                }),
        ];

        return response()->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::active()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
} 