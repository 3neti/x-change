<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Claim\BuildCompiledFormClaimPayload;
use LBHurtado\XChange\Actions\Claim\SubmitCompiledFormClaim;
use LBHurtado\XChange\Data\PreparedCompiledClaimData;
use LBHurtado\XChange\Support\Claim\ClaimEvidenceSynchronizer;

it('syncs compiled form claim evidence before returning payload', function () {
    $voucher = issueVoucher();

    $prepared = new PreparedCompiledClaimData(
        code: $voucher->code,
        voucherId: $voucher->getKey(),
        inputs: [
            'mobile' => '09173011987',
        ],
    );

    $evidence = Mockery::mock(ClaimEvidenceSynchronizer::class);
    $evidence
        ->shouldReceive('sync')
        ->once()
        ->with([
            'source' => 'compiled_form',
            'code' => $voucher->code,
            'voucher_id' => $voucher->getKey(),
            'inputs' => [
                'mobile' => '09173011987',
            ],
        ]);

    $action = new SubmitCompiledFormClaim(
        new BuildCompiledFormClaimPayload,
        $evidence,
    );

    expect($action->handle($voucher, $prepared))->toBe([
        'source' => 'compiled_form',
        'code' => $voucher->code,
        'voucher_id' => $voucher->getKey(),
        'inputs' => [
            'mobile' => '09173011987',
        ],
    ]);
});
