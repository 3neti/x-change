<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\WithdrawalExecutionContextResolver;

it('resolves first withdrawal execution context', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 150.00,
        overrides: [
            'prefix' => 'TEST-FIRST',
        ],
    ));

    $context = app(WithdrawalExecutionContextResolver::class)->resolve(
        $voucher,
        '09173011987',
    );

    expect($context->claimNumber)->toBe(1)
        ->and($context->sliceNumber)->toBe(1)
        ->and($context->providerReference)->toBe($voucher->code.'-09173011987-S1');
});

it('increments claim and slice number from existing claims', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 150.00,
        overrides: [
            'prefix' => 'TEST-NEXT',
        ],
    ));

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'withdrawn',
        'requested_amount_minor' => 7500,
        'disbursed_amount_minor' => 7500,
        'remaining_balance_minor' => 7500,
        'currency' => 'PHP',
    ]);

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 2,
        'claim_type' => 'withdraw',
        'status' => 'withdrawn',
        'requested_amount_minor' => 5000,
        'disbursed_amount_minor' => 5000,
        'remaining_balance_minor' => 2500,
        'currency' => 'PHP',
    ]);

    $context = app(WithdrawalExecutionContextResolver::class)->resolve(
        $voucher,
        '09173011987',
    );

    expect($context->claimNumber)->toBe(3)
        ->and($context->sliceNumber)->toBe(3)
        ->and($context->providerReference)->toBe($voucher->code.'-09173011987-S3');
});

it('builds provider reference from voucher code account number and slice number', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 150.00,
        overrides: [
            'prefix' => 'TEST-REF',
        ],
    ));

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 4,
        'claim_type' => 'withdraw',
        'status' => 'withdrawn',
        'requested_amount_minor' => 1000,
        'disbursed_amount_minor' => 1000,
        'remaining_balance_minor' => 1000,
        'currency' => 'PHP',
    ]);

    $context = app(WithdrawalExecutionContextResolver::class)->resolve(
        $voucher,
        '09170000000',
    );

    expect($context->providerReference)->toBe($voucher->code.'-09170000000-S5');
});
