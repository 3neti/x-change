<?php

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Models\Voucher;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', App\Http\Middleware\Redeem\CheckVoucherMiddleware::class])
        ->get('/test-plugin/{voucher}/{plugin}', [\App\Http\Controllers\Redeem\RedeemPluginController::class, 'show']);

    Route::middleware(['web', App\Http\Middleware\Redeem\CheckVoucherMiddleware::class])
        ->post('/test-plugin/{voucher}/{plugin}', [\App\Http\Controllers\Redeem\RedeemPluginController::class, 'store']);
});

it('renders inputs plugin page and stores session data', function () {
    $voucher = Vouchers::create();

    // GET the inputs page
    get("/test-plugin/{$voucher->code}/inputs")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Redeem/Inputs'));

    // POST the inputs form
    post("/test-plugin/{$voucher->code}/inputs", [
        'name' => 'Juan Dela Cruz',
        'address' => '123 Main St',
        'birthdate' => '1990-01-01',
        'email' => 'juan@example.com',
        'gross_monthly_income' => 15000,
    ])->assertRedirect(route('redeem.finalize', $voucher));

    expect(Session::get("redeem.{$voucher->code}.inputs"))->toMatchArray([
        'name' => 'Juan Dela Cruz',
        'address' => '123 Main St',
        'birthdate' => '1990-01-01',
        'email' => 'juan@example.com',
        'gross_monthly_income' => 15000,
    ]);
});

it('renders signature plugin page and stores signature', function () {
    $voucher = Vouchers::create();

    get("/test-plugin/{$voucher->code}/signature")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Redeem/Signature'));

    post("/test-plugin/{$voucher->code}/signature", [
        'signature' => 'data:image/png;base64,...',
    ])->assertRedirect(route('redeem.finalize', $voucher));

    expect(Session::get("redeem.{$voucher->code}.signature.signature"))->toBe('data:image/png;base64,...');

    expect(Session::get("redeem.{$voucher->code}.signature"))
        ->toMatchArray(['signature' => 'data:image/png;base64,...']);
});
