<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\GoogleAuthController;
use App\Http\Controllers\Web\InvoicesController;
use App\Http\Controllers\Web\TransactionsController;

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// Auth routes
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

Route::post('/logout', function () {
    \Illuminate\Support\Facades\Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout')->middleware('auth');

// Google OAuth routes
Route::prefix('auth/google')->name('google.')->group(function () {
    Route::get('/redirect', [GoogleAuthController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [GoogleAuthController::class, 'callback'])->name('callback');
    Route::get('/test', [GoogleAuthController::class, 'test'])->name('test');
    Route::get('/status', [GoogleAuthController::class, 'status'])->name('status');
});

// Faktury - wymagają autoryzacji
Route::middleware(['auth'])->group(function () {
    Route::get('/invoices', [InvoicesController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{id}', [InvoicesController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/bulk-action', [InvoicesController::class, 'bulkAction'])->name('invoices.bulkAction');
});

// Transakcje (płatności) - wymagają autoryzacji
Route::middleware(['auth'])->group(function () {
    Route::get('/transactions', [TransactionsController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{id}', [TransactionsController::class, 'show'])->name('transactions.show');
    Route::post('/transactions/bulk-action', [TransactionsController::class, 'bulkAction'])->name('transactions.bulkAction');
});
