<?php

declare(strict_types=1);

use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\XChange\Actions\Funding\IssueTreasuryBackedPayCode;

it('honors an explicitly declared voucher type and initial state', function () {
    $requester = actingAsTestUser(0);

    $voucher = app(IssueTreasuryBackedPayCode::class)->handle(
        issuer: $requester,
        instructions: [
            'cash' => [
                'amount' => 0,
                'currency' => 'PHP',
                'validation' => ['country' => 'PH'],
            ],
            'inputs' => ['fields' => []],
            'feedback' => [],
            'rider' => [],
            'count' => 1,
            'prefix' => 'FUND',
            'mask' => '****',
            'voucher_type' => VoucherType::PAYABLE->value,
            'target_amount' => 17,
            'rules' => [
                'allow_overpayment' => false,
            ],
            'metadata' => [
                'flow_type' => 'collectible',
            ],
        ],
        expiresAt: now()->addWeek(),
        initialState: VoucherState::LOCKED,
    );

    expect($voucher->owner->is($requester))->toBeTrue()
        ->and($voucher->voucher_type)->toBe(VoucherType::PAYABLE)
        ->and($voucher->state)->toBe(VoucherState::LOCKED)
        ->and($voucher->instructions->cash->amount)->toBe(0.0)
        ->and($voucher->instructions->target_amount)->toBe(17.0);
});

it('preserves redeemable active defaults for existing callers', function () {
    $issuer = actingAsTestUser(0);

    $voucher = app(IssueTreasuryBackedPayCode::class)->handle(
        issuer: $issuer,
        instructions: [
            'cash' => [
                'amount' => 17,
                'currency' => 'PHP',
                'validation' => ['country' => 'PH'],
            ],
            'inputs' => ['fields' => []],
            'feedback' => [],
            'rider' => [],
            'count' => 1,
            'prefix' => 'FUND',
            'mask' => '****',
        ],
        expiresAt: now()->addWeek(),
    );

    expect($voucher->voucher_type)->toBe(VoucherType::REDEEMABLE)
        ->and($voucher->state)->toBe(VoucherState::ACTIVE);
});
