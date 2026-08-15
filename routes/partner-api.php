<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Controllers\PartnerApi\ShowPartnerCapabilitiesController;
use LBHurtado\XChange\Http\Middleware\EnsurePartnerApiClient;

$prefix = trim((string) config('x-change.partner_api.prefix', 'api/partner/v1'), '/');

Route::prefix($prefix)
    ->as('x-change.partner-api.')
    ->middleware('throttle:x-change-partner-api')
    ->group(function (): void {
        Route::middleware(EnsurePartnerApiClient::using('capabilities:read'))
            ->get('/capabilities', ShowPartnerCapabilitiesController::class)
            ->name('capabilities.show');
    });
