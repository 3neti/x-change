<?php

use Illuminate\Support\Facades\Route;
use LBHurtado\Voucher\Http\Controllers\VoucherGenerationController;

Route::post('/vouchers/generate', VoucherGenerationController::class)->name('vouchers.generate');
