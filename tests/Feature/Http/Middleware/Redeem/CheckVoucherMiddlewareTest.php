<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', \App\Http\Middleware\Redeem\CheckVoucherMiddleware::class])
        ->get('/test-check-voucher/{voucher}', fn (Voucher $voucher) => 'ok');
});

it('passes when voucher is redeemable', function () {
    $voucher = Vouchers::create();
    Vouchers::shouldReceive('redeemable')
        ->once()
        ->with($voucher->code)
        ->andReturnTrue();

    $this->get("/test-check-voucher/{$voucher->code}")
        ->assertOk();
});

it('fails when voucher is not redeemable', function () {
    $voucher = Vouchers::create();
    Vouchers::shouldReceive('redeemable')
        ->once()
        ->with($voucher->code)
        ->andReturnFalse();

    $this->get("/test-check-voucher/{$voucher->code}")
        ->assertStatus(400); // BAD_REQUEST
});

it('fails when voucher is not found', function () {
    $this->get('/test-check-voucher/INVALID')
        ->assertNotFound(); // 404 from Laravel route model binding
});
