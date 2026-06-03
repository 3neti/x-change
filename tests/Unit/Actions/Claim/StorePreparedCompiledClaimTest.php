<?php

use LBHurtado\XChange\Actions\Claim\PrepareCompiledClaim;
use LBHurtado\XChange\Actions\Claim\StorePreparedCompiledClaim;
use LBHurtado\XChange\Data\CompiledClaimPreparationResult;
use LBHurtado\XChange\Data\CompiledClaimSubmissionData;
use LBHurtado\XChange\Support\Claim\CompiledClaimSessionKeys;

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

it('stores normalized prepared compiled claim payload', function () {
    $voucher = issueVoucher();

    $submission = new CompiledClaimSubmissionData(
        code: 'TEST123',
        inputs: [
            'first_name' => 'Lester',
        ],
    );

    $result = CompiledClaimPreparationResult::valid(
        submission: $submission,
        voucher: $voucher,
    );

    $payload = app(StorePreparedCompiledClaim::class)->handle($result);

    expect($payload)->toBe([
        'code' => 'TEST123',
        'voucher_id' => $voucher->getKey(),
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ])
        ->and(
            session(CompiledClaimSessionKeys::PREPARED)
        )->toBe($payload);

});

it('does not store invalid prepared compiled claim payload', function () {
    $result = CompiledClaimPreparationResult::missingSubmission();

    $payload = app(StorePreparedCompiledClaim::class)->handle($result);

    expect($payload)->toBe([])
        ->and(
            session()->has(CompiledClaimSessionKeys::PREPARED)
        )->toBeFalse();
});
