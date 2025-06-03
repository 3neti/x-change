<?php

use LBHurtado\PaymentGateway\Http\Controllers\ConfirmDepositController;
use Illuminate\Support\Facades\Route;

Route::post('confirm-deposit', ConfirmDepositController::class)
    ->name('confirm-deposit');
