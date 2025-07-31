<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TransactionsController;
use App\Http\Controllers\Api\BankDataController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\AnalyticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->group(function () {
    // User info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Transactions
    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionsController::class, 'index']);
        Route::get('/statistics', [TransactionsController::class, 'statistics']);
        Route::get('/categories', [TransactionsController::class, 'categories']);
        Route::post('/', [TransactionsController::class, 'store']);
        Route::get('/{id}', [TransactionsController::class, 'show']);
        Route::put('/{id}', [TransactionsController::class, 'update']);
        Route::delete('/{id}', [TransactionsController::class, 'destroy']);
        Route::post('/{id}/analyze', [TransactionsController::class, 'analyze']);
        Route::post('/{id}/suggest-category', [TransactionsController::class, 'suggestCategory']);
    });

    // Bank Data
    Route::prefix('banking')->group(function () {
        Route::get('/accounts', [BankDataController::class, 'accounts']);
        Route::get('/accounts/{id}', [BankDataController::class, 'showAccount']);
        Route::post('/accounts', [BankDataController::class, 'storeAccount']);
        Route::put('/accounts/{id}', [BankDataController::class, 'updateAccount']);
        Route::delete('/accounts/{id}', [BankDataController::class, 'destroyAccount']);
        Route::post('/accounts/{id}/sync', [BankDataController::class, 'syncAccount']);
        Route::get('/institutions', [BankDataController::class, 'institutions']);
        Route::post('/requisitions', [BankDataController::class, 'createRequisition']);
        Route::get('/requisitions/{id}', [BankDataController::class, 'getRequisition']);
    });

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/', [ReportsController::class, 'index']);
        Route::post('/', [ReportsController::class, 'generate']);
        Route::get('/{id}', [ReportsController::class, 'show']);
        Route::get('/{id}/download', [ReportsController::class, 'download']);
        Route::delete('/{id}', [ReportsController::class, 'destroy']);
    });

    // Analytics
    Route::prefix('analytics')->group(function () {
        Route::get('/overview', [AnalyticsController::class, 'overview']);
        Route::get('/spending-patterns', [AnalyticsController::class, 'spendingPatterns']);
        Route::get('/budget-analysis', [AnalyticsController::class, 'budgetAnalysis']);
        Route::get('/trends', [AnalyticsController::class, 'trends']);
        Route::get('/insights', [AnalyticsController::class, 'insights']);
    });

    // Import
    Route::prefix('import')->group(function () {
        Route::post('/csv', [TransactionsController::class, 'importCsv']);
        Route::get('/csv/formats', [TransactionsController::class, 'getSupportedFormats']);
    });

    // AI Features
    Route::prefix('ai')->group(function () {
        Route::post('/analyze-transaction/{id}', [TransactionsController::class, 'analyze']);
        Route::post('/suggest-category/{id}', [TransactionsController::class, 'suggestCategory']);
        Route::post('/budget-recommendations', [AnalyticsController::class, 'budgetRecommendations']);
        Route::post('/financial-insights', [AnalyticsController::class, 'financialInsights']);
    });
});

// Public routes (if any)
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0',
    ]);
});

// Webhook routes
Route::prefix('webhooks')->group(function () {
    Route::post('/nordigen', [BankDataController::class, 'nordigenWebhook']);
    Route::post('/revolut', [BankDataController::class, 'revolutWebhook']);
    Route::post('/slack', [AnalyticsController::class, 'slackWebhook']);
}); 