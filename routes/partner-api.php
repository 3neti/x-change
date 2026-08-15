<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Controllers\PartnerApi\CancelPartnerPayCodeController;
use LBHurtado\XChange\Http\Controllers\PartnerApi\EstimatePartnerPayCodeController;
use LBHurtado\XChange\Http\Controllers\PartnerApi\IssuePartnerPayCodeController;
use LBHurtado\XChange\Http\Controllers\PartnerApi\ShowPartnerCapabilitiesController;
use LBHurtado\XChange\Http\Controllers\PartnerApi\ShowPartnerPayCodeController;
use LBHurtado\XChange\Http\Middleware\EnsurePartnerApiClient;

$prefix = trim((string) config('x-change.partner_api.prefix', 'api/partner/v1'), '/');

Route::prefix($prefix)
    ->as('x-change.partner-api.')
    ->middleware('throttle:x-change-partner-api')
    ->group(function (): void {
        Route::middleware(EnsurePartnerApiClient::using('capabilities:read'))
            ->get('/capabilities', ShowPartnerCapabilitiesController::class)
            ->name('capabilities.show');

        Route::middleware(EnsurePartnerApiClient::using('pay-codes:estimate'))
            ->post('/pay-code-estimates', EstimatePartnerPayCodeController::class)
            ->name('pay-code-estimates.store');

        Route::middleware(EnsurePartnerApiClient::using('pay-codes:issue'))
            ->post('/pay-codes', IssuePartnerPayCodeController::class)
            ->name('pay-codes.store');

        Route::middleware(EnsurePartnerApiClient::using('pay-codes:read'))
            ->get('/pay-codes/{code}', ShowPartnerPayCodeController::class)
            ->name('pay-codes.show');

        Route::middleware(EnsurePartnerApiClient::using('pay-codes:cancel'))
            ->post('/pay-codes/{code}/cancellation', CancelPartnerPayCodeController::class)
            ->name('pay-codes.cancellation.store');
    });
