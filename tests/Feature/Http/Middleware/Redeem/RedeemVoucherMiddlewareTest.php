<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Route, Session};
use Propaganistas\LaravelPhone\PhoneNumber;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use LBHurtado\Voucher\Models\Voucher;
use App\Actions\EncashCheck;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', \App\Http\Middleware\Redeem\RedeemVoucherMiddleware::class])
        ->get('/test-redeem/{voucher}', fn (Voucher $voucher) => 'ok');
});

it('passes when voucher exists and EncashCheck succeeds', function () {
    $voucher = Vouchers::create();
    $code = $voucher->code;

    // Arrange session
    $mobile = '09171234567';
    $country = 'PH';
    Session::put("redeem.{$code}.mobile", $mobile);
    Session::put("redeem.{$code}.country", $country);

    // Optional extra meta
    $inputs = ['name' => 'John'];
    $signature = 'abc123';
    Session::put("redeem.{$code}.inputs", $inputs);
    Session::put("redeem.{$code}.signature", $signature);

    $meta = [
        'inputs'    => $inputs,
        'signature' => $signature,
    ];

    $phone = new PhoneNumber($mobile, $country);

    EncashCheck::mock()->shouldReceive('handle')
        ->once()
        ->withArgs(function (Voucher $v, PhoneNumber $p, array $m) use ($voucher, $phone, $meta) {
            return $v->is($voucher)
                && $p->formatE164() === $phone->formatE164()
                && $m === $meta;
        })
        ->andReturnTrue();

    $this->get("/test-redeem/{$code}")
        ->assertOk();
});

it('aborts if mobile number is missing from session', function () {
    $voucher = Vouchers::create();
    $code = $voucher->code;

    // Only country set, no mobile
    Session::put("redeem.{$code}.country", 'PH');

    $this->get("/test-redeem/{$code}")
        ->assertStatus(400); // bad request
});

it('still passes if EncashCheck throws an exception', function () {
    $voucher = Vouchers::create();
    $code = $voucher->code;

    Session::put("redeem.{$code}.mobile", '09171234567');
    Session::put("redeem.{$code}.country", 'PH');

    EncashCheck::mock()
        ->shouldReceive('handle')
        ->once()
        ->andThrow(new Exception('Redemption failed'));

    $this->get("/test-redeem/{$code}")
        ->assertOk(); // middleware handles error gracefully
});
