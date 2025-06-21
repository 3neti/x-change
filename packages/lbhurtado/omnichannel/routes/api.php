<?php

use LBHurtado\OmniChannel\Http\Controllers\SMSController;
use Illuminate\Support\Facades\Route;

Route::post('sms', SMSController::class)->name('sms');
