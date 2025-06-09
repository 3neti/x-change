<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::get('/', fn () => Inertia::render('Welcome'));

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

use App\Http\Controllers\WalletBalanceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/wallet/balance', [WalletBalanceController::class, 'show'])
        ->name('wallet.balance');
});
