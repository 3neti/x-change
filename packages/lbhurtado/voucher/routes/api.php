<?php

use LBHurtado\Voucher\Http\Controllers\VoucherGenerationController;
use LBHurtado\Voucher\Http\Controllers\VoucherDataController;
use Illuminate\Support\Facades\Route;

Route::post('/vouchers/generate', VoucherGenerationController::class)->name('vouchers.generate');
Route::get('/vouchers/data/{code}', VoucherDataController::class)->name('vouchers.data');
