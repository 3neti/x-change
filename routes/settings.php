<?php

use App\Http\Controllers\Settings\{ChannelController, MerchantController, ProfileController, TokenController};
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/channel', [ChannelController::class, 'edit'])->name('channel.edit');
    Route::patch('settings/channel', [ChannelController::class, 'update'])->name('channel.update');

    Route::get('settings/merchant', [MerchantController::class, 'edit'])->name('merchant.edit');
    Route::patch('settings/merchant', [MerchantController::class, 'update'])->name('merchant.update');

    Route::get('settings/token', [TokenController::class, 'edit'])->name('token.edit');
    Route::patch('settings/token', [TokenController::class, 'update'])->name('token.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');
});
