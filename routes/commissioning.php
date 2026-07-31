<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Controllers\Web\Commissioning\CommissioningChecklistController;
use LBHurtado\XChange\Http\Controllers\Web\Commissioning\CommissioningStatusController;
use LBHurtado\XChange\Http\Controllers\Web\Commissioning\CommissioningStylesheetController;
use LBHurtado\XChange\Http\Controllers\Web\Commissioning\OperationalReadinessController;

Route::middleware('web')->group(function (): void {
    Route::get('/x/commissioning', CommissioningStatusController::class)
        ->name('x-change.commissioning.status');
    Route::get('/x/commissioning/checklist', [CommissioningChecklistController::class, 'show'])
        ->name('x-change.commissioning.checklist');
    Route::post('/x/commissioning/checklist', [CommissioningChecklistController::class, 'unlock'])
        ->middleware('throttle:5,1')
        ->name('x-change.commissioning.checklist.unlock');
});

Route::get('/x/commissioning/assets/commissioning.css', CommissioningStylesheetController::class)
    ->name('x-change.commissioning.assets.css');

Route::get('/x/ready', OperationalReadinessController::class)
    ->name('x-change.operational-readiness');
