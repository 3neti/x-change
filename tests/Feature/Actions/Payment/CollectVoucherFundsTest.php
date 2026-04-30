<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Payment\CollectVoucherFunds;
use LBHurtado\XChange\Exceptions\VoucherCannotCollect;
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

    $result = app(CollectVoucherFunds::class)->handle($voucher, $wallet, [
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

    $result = app(CollectVoucherFunds::class)->handle($voucher, $wallet, [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'failed',
        'provider' => 'manual',
        'provider_reference' => 'REF-FAILED',
        'provider_transaction_id' => 'TXN-FAILED',
    ]);

    expect($result->status)->toBe('failed')
        ->and((float) $wallet->fresh()->balanceFloat)->toBe($balanceBefore);
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

    app(CollectVoucherFunds::class)->handle($voucher, $user->wallet, [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
    ]);
})->throws(VoucherCannotCollect::class);
