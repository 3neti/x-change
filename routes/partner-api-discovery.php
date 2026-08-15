<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Controllers\PartnerApi\ShowPartnerApiDiscoveryController;
use LBHurtado\XChange\Http\Controllers\PartnerApi\ShowPartnerApiOpenApiController;

Route::middleware('throttle:x-change-partner-api-discovery')->group(function (): void {
    Route::get('/.well-known/oauth-authorization-server', [ShowPartnerApiDiscoveryController::class, 'authorizationServer'])
        ->name('x-change.partner-api.discovery.authorization-server');
    Route::get('/.well-known/oauth-protected-resource', [ShowPartnerApiDiscoveryController::class, 'protectedResource'])
        ->name('x-change.partner-api.discovery.protected-resource');
    Route::get('/.well-known/x-change-partner-api', [ShowPartnerApiDiscoveryController::class, 'partnerApi'])
        ->name('x-change.partner-api.discovery.index');
    Route::get('/api/partner', [ShowPartnerApiDiscoveryController::class, 'partnerApi'])
        ->name('x-change.partner-api.discovery.alias');
    Route::get('/api/partner/openapi.json', ShowPartnerApiOpenApiController::class)
        ->name('x-change.partner-api.discovery.openapi');
    Route::get('/llms.txt', [ShowPartnerApiDiscoveryController::class, 'llms'])
        ->name('x-change.partner-api.discovery.llms');
});
