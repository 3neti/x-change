<?php

use LBHurtado\XChange\Actions\Claim\PrepareCompiledClaim;
use LBHurtado\XChange\Actions\Claim\StorePreparedCompiledClaim;

it('stores prepared compiled claim state in session flash data', function () {
    $voucher = issueVoucher();

    session()->put('compiled_claim_submission', [
        'code' => $voucher->code,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    $prepared = app(PrepareCompiledClaim::class)->handle();

    $payload = app(StorePreparedCompiledClaim::class)->handle($prepared);

    expect($payload)->toBe([
        'code' => $voucher->code,
        'voucher_id' => $voucher->getKey(),
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ])->and(session()->get('compiled_claim_prepared'))->toBe([
        'code' => $voucher->code,
        'voucher_id' => $voucher->getKey(),
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);
});
