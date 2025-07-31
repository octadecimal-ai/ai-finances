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
    public function __construct(
        private ClaudeService $claudeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $query = Transaction::where('user_id', $user->id);

        // Filtry
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->byDateRange($request->date_from, $request->date_to);
        }

        $transactions = $query->with(['category', 'bankAccount'])
            ->orderBy('transaction_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($transactions);
    }

    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $transaction = Transaction::where('user_id', $user->id)
            ->with(['category', 'bankAccount'])
            ->findOrFail($id);

        return response()->json($transaction);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'description' => 'required|string|max:255',
            'transaction_date' => 'required|date',
            'merchant_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'bank_account_id' => $request->bank_account_id,
            'category_id' => $request->category_id,
            'type' => $request->type,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'description' => $request->description,
            'transaction_date' => $request->transaction_date,
            'merchant_name' => $request->merchant_name,
        ]);

        return response()->json($transaction, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $transaction = Transaction::where('user_id', $user->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'string|max:255',
            'merchant_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaction->update($request->only(['category_id', 'description', 'merchant_name']));

        return response()->json($transaction);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $transaction = Transaction::where('user_id', $user->id)->findOrFail($id);
        $transaction->delete();

        return response()->json(['message' => 'Transaction deleted successfully']);
    }

    public function statistics(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $query = Transaction::where('user_id', $user->id);

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->byDateRange($request->date_from, $request->date_to);
        }

        $statistics = [
            'total_income' => (float) $query->clone()->income()->sum('amount'),
            'total_expenses' => (float) $query->clone()->expense()->sum('amount'),
            'transaction_count' => $query->clone()->count(),
            'average_transaction' => (float) $query->clone()->avg('amount'),
        ];

        // Statystyki według kategorii
        $categoryStats = $query->clone()
            ->selectRaw('category_id, SUM(amount) as total_amount, COUNT(*) as count')
            ->groupBy('category_id')
            ->with('category')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category !== null ? $item->category->name : 'Nieprzypisana',
                    'total_amount' => (float) $item->total_amount,
                    'count' => $item->count,
                ];
            });

        $statistics['by_category'] = $categoryStats;

        return response()->json($statistics);
    }

    public function analyze(int $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $transaction = Transaction::where('user_id', $user->id)
            ->with(['category', 'bankAccount'])
            ->findOrFail($id);

        $analysis = $this->claudeService->analyzeTransaction($transaction);

        return response()->json($analysis);
    }

    public function suggestCategory(int $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $transaction = Transaction::where('user_id', $user->id)->findOrFail($id);
        $categories = Category::active()->get();

        $suggestion = $this->claudeService->suggestCategory($transaction, $categories);

        return response()->json($suggestion);
    }
} 