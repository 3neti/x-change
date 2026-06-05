<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Claim\BuildCompiledFormClaimPayload;
use LBHurtado\XChange\Actions\Claim\SubmitCompiledFormClaim;
use LBHurtado\XChange\Data\PreparedCompiledClaimData;

it('submits compiled form claim through the payload builder', function () {
    $voucher = issueVoucher();

    $prepared = new PreparedCompiledClaimData(
        code: $voucher->code,
        voucherId: $voucher->getKey(),
        inputs: [
            'mobile' => '09467438575',
        ],
    );

    $action = new SubmitCompiledFormClaim(
        new BuildCompiledFormClaimPayload
    );

    expect($action->handle($voucher, $prepared))->toBe([
        'source' => 'compiled_form',
        'code' => $voucher->code,
        'voucher_id' => $voucher->getKey(),
        'inputs' => [
            'mobile' => '09467438575',
        ],
    ]);
});
