<?php

use LBHurtado\XChange\Data\WithdrawalPipelineContextData;

it('enables all built-in withdrawal pipeline steps by default', function () {
    $context = new WithdrawalPipelineContextData(
        voucher: issueVoucher(),
        payload: [],
    );

    foreach (config('x-change.withdrawal.pipeline.steps') as $step) {
        expect($step::shouldRun($context))->toBeTrue();
    }
});
