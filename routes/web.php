<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Controllers\Web\BalancePageController;
use LBHurtado\XChange\Http\Controllers\Web\DashboardPageController;
use LBHurtado\XChange\Http\Controllers\Web\PayCodeCreatePageController;
use LBHurtado\XChange\Http\Controllers\Web\PayCodeIndexPageController;
use LBHurtado\XChange\Http\Controllers\Web\PayCodeShowPageController;
use LBHurtado\XChange\Http\Middleware\ShareXChangeBranding;

$middleware = config('x-change.routes.web_middleware', ['web', 'auth']);

Route::prefix('x')->middleware([...$middleware, ShareXChangeBranding::class])->group(function (): void {
    Route::get('dashboard', DashboardPageController::class)->name('x-change.dashboard');

    Route::prefix('pay-codes')->group(function (): void {
        Route::get('/', PayCodeIndexPageController::class)->name('x-change.pay-codes.index');
        Route::get('create', PayCodeCreatePageController::class)->name('x-change.pay-codes.create');
        Route::get('{code}', PayCodeShowPageController::class)->name('x-change.pay-codes.show');
    });

    Route::get('balances', BalancePageController::class)->name('x-change.balances.index');
});
