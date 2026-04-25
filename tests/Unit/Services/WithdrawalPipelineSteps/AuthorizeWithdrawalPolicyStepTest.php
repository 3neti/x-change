<?php

declare(strict_types=1);

use LBHurtado\Cash\Contracts\CashWithdrawalAuthorizationPolicyContract;
use LBHurtado\Cash\Data\WithdrawalAuthorizationContextData;
use LBHurtado\XChange\Adapters\VoucherWithdrawableInstrumentAdapter;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\AuthorizeWithdrawalPolicyStep;

it('delegates withdrawal authorization policy to cash package', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 1000.00,
        settlementRail: 'INSTAPAY',
    ));

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [
            'vendor_id' => 'vendor-123',
            'authorization' => [
                'approved' => false,
            ],
        ],
    );

    $context->withdrawAmount = 1500.00;

    $policy = Mockery::mock(CashWithdrawalAuthorizationPolicyContract::class);

    $policy->shouldReceive('authorize')
        ->once()
        ->withArgs(function ($instrument, $authorizationContext) use ($voucher) {
            return $instrument instanceof VoucherWithdrawableInstrumentAdapter
                && $instrument->getInstrumentId() === $voucher->id
                && $authorizationContext instanceof WithdrawalAuthorizationContextData
                && $authorizationContext->amount === 1500.00
                && $authorizationContext->vendorId === 'vendor-123'
                && $authorizationContext->approved === false;
        });

    $step = new AuthorizeWithdrawalPolicyStep($policy);

    $result = $step->handle($context, fn ($context) => $context);

    expect($result)->toBe($context);
});

it('fails when withdrawal amount is missing before authorization policy', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 1000.00,
        settlementRail: 'INSTAPAY',
    ));

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [],
    );

    $step = new AuthorizeWithdrawalPolicyStep(
        Mockery::mock(CashWithdrawalAuthorizationPolicyContract::class),
    );

    $step->handle($context, fn ($context) => $context);
})->throws(LogicException::class, 'Withdrawal amount must be resolved before authorization policy.');
