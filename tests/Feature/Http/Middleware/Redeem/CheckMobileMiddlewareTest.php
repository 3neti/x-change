<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Route;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', \App\Http\Middleware\Redeem\CheckMobileMiddleware::class])
        ->post('/test-check-mobile/{voucher}', function (Voucher $voucher) {
            return 'ok';
        });
});

it('accepts a valid PH mobile number', function () {
    $voucher = Vouchers::create();
    Session::put("redeem.{$voucher->code}.mobile", '09171234567');
    $this->post("/test-check-mobile/{$voucher->code}", ['mobile' => '09171234567'])
        ->assertOk();

    expect(Session::get("redeem.{$voucher->code}.mobile"))->toBe('09171234567');
});

it('rejects invalid mobile number', function () {
    $voucher = Vouchers::create();
    Session::put("redeem.{$voucher->code}.mobile", '12345');
    $this->post("/test-check-mobile/{$voucher->code}", ['mobile' =>'12345'])
        ->assertBadRequest();
});
