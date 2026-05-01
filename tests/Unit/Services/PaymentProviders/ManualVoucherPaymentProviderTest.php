<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\PaymentProviders\ManualVoucherPaymentProvider;

it('confirms succeeded manual payment', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
            ],
        ],
    ));

    $result = app(ManualVoucherPaymentProvider::class)->confirm($voucher, [
        'amount' => 100.00,
        'currency' => 'php',
        'status' => 'succeeded',
        'provider_reference' => 'REF-MANUAL-1',
        'provider_transaction_id' => 'TXN-MANUAL-1',
        'payer' => [
            'name' => 'Juan Dela Cruz',
            'mobile' => '09171234567',
        ],
    ]);

    expect($result->status)->toBe('succeeded')
        ->and($result->voucher_code)->toBe($voucher->code)
        ->and($result->amount)->toBe(100.00)
        ->and($result->currency)->toBe('PHP')
        ->and($result->provider)->toBe('manual')
        ->and($result->provider_reference)->toBe('REF-MANUAL-1')
        ->and(data_get($result->payer, 'mobile'))->toBe('09171234567');
});

it('returns failed result for failed manual payment', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
            ],
        ],
    ));

    $result = app(ManualVoucherPaymentProvider::class)->confirm($voucher, [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'failed',
        'provider_reference' => 'REF-MANUAL-FAILED',
    ]);

    expect($result->status)->toBe('failed')
        ->and($result->amount)->toBe(100.00)
        ->and($result->provider)->toBe('manual')
        ->and($result->succeeded())->toBeFalse();
});
