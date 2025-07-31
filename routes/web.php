<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\GoogleAuthController;

Route::get('/', function () {
    return view('welcome');
});

// Google OAuth routes
Route::prefix('auth/google')->name('google.')->group(function () {
    Route::get('/redirect', [GoogleAuthController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [GoogleAuthController::class, 'callback'])->name('callback');
    Route::get('/test', [GoogleAuthController::class, 'test'])->name('test');
    Route::get('/status', [GoogleAuthController::class, 'status'])->name('status');
});
