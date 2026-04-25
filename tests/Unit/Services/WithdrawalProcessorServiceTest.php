<?php

declare(strict_types=1);

use LBHurtado\Cash\Exceptions\WithdrawalApprovalRequired;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Services\DefaultWithdrawalProcessorService;
use LBHurtado\XChange\Services\WithdrawalPipeline;
use LBHurtado\XChange\Services\WithdrawalResultFactory;

it('returns result built by withdrawal pipeline', function () {
    $voucher = issueVoucher();

    $expected = new WithdrawPayCodeResultData(
        voucher_code: (string) $voucher->code,
        withdrawn: true,
        status: 'withdrawn',
        requested_amount: 100.00,
        disbursed_amount: 100.00,
        currency: 'PHP',
        remaining_balance: 0.0,
        slice_number: 1,
        remaining_slices: 0,
        slice_mode: null,
        redeemer: [],
        bank_account: [],
        disbursement: [],
        messages: ['Voucher withdrawal successful.'],
    );

    $pipeline = Mockery::mock(WithdrawalPipeline::class);
    $pipeline->shouldReceive('process')
        ->once()
        ->withArgs(fn ($context) =>
            $context instanceof WithdrawalPipelineContextData
            && $context->voucher->is($voucher)
            && $context->payload === ['mobile' => '09171234567']
        )
        ->andReturn(new WithdrawalPipelineContextData(
            voucher: $voucher,
            payload: ['mobile' => '09171234567'],
            result: $expected,
        ));

    $withdrawalResultFactory = app(WithdrawalResultFactory::class);

    $service = new DefaultWithdrawalProcessorService($pipeline, $withdrawalResultFactory);

    expect($service->process($voucher, ['mobile' => '09171234567']))->toBe($expected);
});

it('fails when withdrawal pipeline does not build result', function () {
    $voucher = issueVoucher();

    $pipeline = Mockery::mock(WithdrawalPipeline::class);
    $pipeline->shouldReceive('process')
        ->once()
        ->andReturn(new WithdrawalPipelineContextData(
            voucher: $voucher,
            payload: [],
        ));

    $withdrawalResultFactory = app(WithdrawalResultFactory::class);

    $service = new DefaultWithdrawalProcessorService($pipeline, $withdrawalResultFactory);

    $service->process($voucher, []);
})->throws(RuntimeException::class, 'Withdrawal pipeline did not build a withdrawal result.');

it('returns approval required result when withdrawal policy requires approval', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 1000.00,
        settlementRail: 'INSTAPAY',
    ));

    $pipeline = Mockery::mock(WithdrawalPipeline::class);

    $pipeline->shouldReceive('process')
        ->once()
        ->andThrow(WithdrawalApprovalRequired::forThreshold(
            amount: 1500.00,
            threshold: 1000.00,
        ));

    $service = new DefaultWithdrawalProcessorService(
        withdrawalPipeline: $pipeline,
        withdrawalResultFactory: app(WithdrawalResultFactory::class),
    );

    $result = $service->process($voucher, [
        'mobile' => '09171234567',
        'amount' => 1500.00,
    ]);

    expect($result->withdrawn)->toBeFalse()
        ->and($result->status)->toBe('approval_required')
        ->and($result->disbursed_amount)->toBe(0.0)
        ->and($result->messages)->toContain('Withdrawal approval is required for amounts above 1000.');
});
