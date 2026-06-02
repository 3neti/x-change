<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Controllers\Web\BalancePageController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimCompleteController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimRedirectController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimStartController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimSubmitController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimSuccessPageController;
use LBHurtado\XChange\Http\Controllers\Web\DashboardPageController;
use LBHurtado\XChange\Http\Controllers\Web\PayCodeCreatePageController;
use LBHurtado\XChange\Http\Controllers\Web\PayCodeIndexPageController;
use LBHurtado\XChange\Http\Controllers\Web\PayCodeShowPageController;
use LBHurtado\XChange\Http\Middleware\ShareXChangeBranding;

$middleware = config('x-change.routes.web_middleware', ['web', 'auth']);

// Authenticated operator routes
Route::prefix('x')->middleware([...$middleware, ShareXChangeBranding::class])->group(function (): void {
    Route::get('dashboard', DashboardPageController::class)->name('x-change.dashboard');

    Route::prefix('pay-codes')->group(function (): void {
        Route::get('/', PayCodeIndexPageController::class)->name('x-change.pay-codes.index');
        Route::get('create', PayCodeCreatePageController::class)->name('x-change.pay-codes.create');
        Route::get('{code}', PayCodeShowPageController::class)->name('x-change.pay-codes.show');
    });

    Route::get('balances', BalancePageController::class)->name('x-change.balances.index');
});

// Public claim routes (no auth required)
Route::prefix('x')->middleware(['web', ShareXChangeBranding::class])->group(function (): void {
    Route::get('claim', ClaimStartController::class)->name('x-change.claim.start');
    Route::post('claim', ClaimStartController::class)->name('x-change.claim.start.submit');
    Route::post('claim/{code}/complete', ClaimCompleteController::class)
        ->withoutMiddleware([VerifyCsrfToken::class])
        ->name('x-change.claim.complete');
    Route::post('claim/{code}/submit', ClaimSubmitController::class)->name('x-change.claim.submit');
    Route::get('claim/{code}/success', ClaimSuccessPageController::class)->name('x-change.claim.success');
    Route::get('claim/{code}/redirect', ClaimRedirectController::class)->name('x-change.claim.redirect');
});
