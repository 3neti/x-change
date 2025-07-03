<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Route, Session};
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', \App\Http\Middleware\Redeem\AddInputsMiddleware::class])
        ->post('/test-add-inputs/{voucher}', function (Voucher $voucher) {
            return 'ok';
        });
});

it('stores input fields in session', function () {
    $voucher = Vouchers::create();
    $input = [
        'name' => 'Juan Dela Cruz',
        'address' => '123 Street',
        'birthdate' => '1990-01-01',
        'email' => 'juan@example.com',
        'gross_monthly_income' => '25000',
        'country' => 'PH',
    ];
    Session::put("redeem.{$voucher->code}.inputs", $input);

    $this->post("/test-add-inputs/{$voucher->code}", $input)
        ->assertOk();

    expect(Session::get("redeem.{$voucher->code}.inputs"))->toMatchArray($input);
});
