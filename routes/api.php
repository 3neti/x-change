<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Controllers\PayCode\EstimatePayCodeController;
use LBHurtado\XChange\Http\Controllers\PayCode\GeneratePayCodeController;

$prefix = trim((string) config('x-change.routes.api_prefix', 'api/x'), '/');
$version = trim((string) config('x-change.routes.api_version', 'v1'), '/');

Route::prefix($prefix.'/'.$version)->group(function (): void {
    Route::post('/pay-codes/estimate', EstimatePayCodeController::class)
        ->name('x-change.api.pay-codes.estimate');

    Route::post('/pay-codes', GeneratePayCodeController::class)
        ->name('x-change.api.pay-codes.generate');
});
