<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Route, Session};
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', \App\Http\Middleware\Redeem\SignTransactionMiddleware::class])
        ->post('/test-signature/{voucher}', function (Voucher $voucher) {
            return 'ok';
        });
});

it('saves signature in session if present', function () {
    $voucher = Vouchers::create();
    $signature = 'data:image/png;base64,abc123==';
    Session::put("redeem.{$voucher->code}.signature", $signature);

    $this->post("/test-signature/{$voucher->code}", ['signature' => $signature])
        ->assertOk();

    expect(Session::get("redeem.{$voucher->code}.signature"))->toBe($signature);
});

it('fails if no signature is provided', function () {
    $voucher = Vouchers::create();
    $this->post("/test-signature/{$voucher->code}", [])
        ->assertBadRequest();
});
