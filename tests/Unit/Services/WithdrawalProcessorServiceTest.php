<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Services\DefaultWithdrawalProcessorService;
use LBHurtado\XChange\Services\WithdrawalPipeline;

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

    $service = new DefaultWithdrawalProcessorService($pipeline);

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

    $service = new DefaultWithdrawalProcessorService($pipeline);

    $service->process($voucher, []);
})->throws(LogicException::class, 'Withdrawal result was not built.');
