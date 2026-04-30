<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Payment\CollectVoucherFunds;
use LBHurtado\XChange\Contracts\VoucherPaymentConfirmationContract;
use LBHurtado\XChange\Exceptions\VoucherCannotCollect;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function () {
    config()->set('x-change.onboarding.issuer_model', User::class);
});

it('credits wallet when collecting funds for collectible voucher', function () {
    $user = actingAsTestUser();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'metadata' => [
                'flow_type' => 'collectible',
            ],
        ],
    ));

    $wallet = $user->wallet;
    $balanceBefore = (float) $wallet->balanceFloat;

    $result = app(CollectVoucherFunds::class)->handle($voucher, [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider' => 'manual',
        'provider_reference' => 'REF-123',
        'provider_transaction_id' => 'TXN-123',
        'payer' => [
            'name' => 'Juan Dela Cruz',
            'mobile' => '09171234567',
        ],
        'meta' => [
            'source' => 'test',
        ],
    ]);

    $wallet = $wallet->fresh();

    expect($result->status)->toBe('collected')
        ->and($result->voucher_code)->toBe($voucher->code)
        ->and($result->amount)->toBe(100.00)
        ->and((float) $wallet->balanceFloat)->toBe($balanceBefore + 100.00)
        ->and(data_get($result->wallet, 'transaction_id'))->not->toBeNull();

    $collection = VoucherCollection::query()
        ->where('voucher_id', $voucher->id)
        ->latest('id')
        ->first();

    expect($collection)->not->toBeNull()
        ->and($collection->status)->toBe('collected')
        ->and($collection->requested_amount_minor)->toBe(10000)
        ->and($collection->collected_amount_minor)->toBe(10000)
        ->and($collection->provider)->toBe('manual')
        ->and($collection->provider_reference)->toBe('REF-123')
        ->and($collection->provider_transaction_id)->toBe('TXN-123')
        ->and($collection->payer_mobile)->toBe('09171234567')
        ->and($collection->wallet_transaction_id)->not->toBeNull();
});

it('does not credit wallet when payment confirmation did not succeed', function () {
    $user = actingAsTestUser();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'metadata' => [
                'flow_type' => 'collectible',
            ],
        ],
    ));

    $wallet = $user->wallet;
    $balanceBefore = (float) $wallet->balanceFloat;

    $result = app(CollectVoucherFunds::class)->handle($voucher, [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'failed',
        'provider' => 'manual',
        'provider_reference' => 'REF-FAILED',
        'provider_transaction_id' => 'TXN-FAILED',
    ]);

    expect($result->status)->toBe('failed')
        ->and((float) $wallet->fresh()->balanceFloat)->toBe($balanceBefore);

    $collection = VoucherCollection::query()
        ->where('voucher_id', $voucher->id)
        ->latest('id')
        ->first();

    expect($collection)->not->toBeNull()
        ->and($collection->status)->toBe('failed')
        ->and($collection->requested_amount_minor)->toBe(10000)
        ->and($collection->collected_amount_minor)->toBe(0)
        ->and($collection->wallet_transaction_id)->toBeNull();
});

it('blocks collection for disbursable vouchers', function () {
    $user = actingAsTestUser();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'metadata' => [
                'flow_type' => 'disbursable',
            ],
        ],
    ));

    app(CollectVoucherFunds::class)->handle($voucher, [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
    ]);
})->throws(VoucherCannotCollect::class);

it('does not call payment confirmation when voucher cannot collect', function () {
    $user = actingAsTestUser();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'metadata' => [
                'flow_type' => 'disbursable',
            ],
        ],
    ));

    $wallet = $user->wallet;

    $confirmation = Mockery::mock(VoucherPaymentConfirmationContract::class);
    $confirmation->shouldNotReceive('confirm');

    app()->instance(VoucherPaymentConfirmationContract::class, $confirmation);

    app(CollectVoucherFunds::class)->handle($voucher, [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
    ]);
})->throws(VoucherCannotCollect::class);

