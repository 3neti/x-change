<?php

use LBHurtado\PaymentGateway\Http\Controllers\ConfirmDisbursementController;
use LBHurtado\PaymentGateway\Http\Controllers\ConfirmDepositController;
use Illuminate\Support\Facades\Route;

Route::post('confirm-deposit', ConfirmDepositController::class)
    ->name('confirm-deposit');

Route::post('confirm-disbursement', ConfirmDisbursementController::class)
    ->name('confirm-disbursement');
