<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Controllers\PayCode\EstimatePayCodeController;

Route::post('/pay-codes/estimate', EstimatePayCodeController::class)
    ->name('x-change.api.pay-codes.estimate');
