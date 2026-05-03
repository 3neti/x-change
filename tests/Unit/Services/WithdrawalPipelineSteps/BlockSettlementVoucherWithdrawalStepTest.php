<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\BlockSettlementVoucherWithdrawalStep;

it('blocks settlement vouchers before withdrawal disbursement', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [],
    );

    app(BlockSettlementVoucherWithdrawalStep::class)->handle(
        context: $context,
        next: fn () => true,
    );
})->throws(VoucherRequiresSettlementEnvelope::class);

it('allows non-settlement vouchers through withdrawal disbursement block step', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'disbursable',
            ],
        ],
    ));

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [],
    );

    $result = app(BlockSettlementVoucherWithdrawalStep::class)->handle(
        context: $context,
        next: fn ($context) => 'continued',
    );

    expect($result)->toBe('continued');
});

it('runs by default for all withdrawal contexts', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'disbursable',
            ],
        ],
    ));

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [],
    );

    expect(BlockSettlementVoucherWithdrawalStep::shouldRun($context))->toBeTrue();
});
