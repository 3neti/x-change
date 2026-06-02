<?php

use LBHurtado\XChange\Actions\Claim\PrepareCompiledClaim;

it('returns missing result when compiled claim submission handoff is absent', function () {
    session()->forget('compiled_claim_submission');

    $result = app(PrepareCompiledClaim::class)->handle();

    expect($result->isValid())->toBeFalse()
        ->and($result->submission)->toBeNull()
        ->and($result->voucher)->toBeNull()
        ->and($result->errorMessage)->toBe('Compiled claim submission is missing.');
});

it('returns invalid result when compiled claim voucher cannot be resolved', function () {
    session()->put('compiled_claim_submission', [
        'code' => 'MISSING123',
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    $result = app(PrepareCompiledClaim::class)->handle();

    expect($result->isValid())->toBeFalse()
        ->and($result->submission?->code)->toBe('MISSING123')
        ->and($result->voucher)->toBeNull()
        ->and($result->errorMessage)->toBe('Invalid Pay Code.');
});

it('returns valid result for redeemable compiled claim voucher', function () {
    $voucher = issueVoucher();

    session()->put('compiled_claim_submission', [
        'code' => $voucher->code,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    $result = app(PrepareCompiledClaim::class)->handle();

    expect($result->isValid())->toBeTrue()
        ->and($result->submission?->code)->toBe($voucher->code)
        ->and($result->submission?->inputs)->toBe([
            'first_name' => 'Lester',
        ])
        ->and($result->voucher?->is($voucher))->toBeTrue()
        ->and($result->errorMessage)->toBeNull();
});

it('can forget compiled claim submission handoff after preparation', function () {
    $voucher = issueVoucher();

    session()->put('compiled_claim_submission', [
        'code' => $voucher->code,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    $result = app(PrepareCompiledClaim::class)->handle(forget: true);

    expect($result->isValid())->toBeTrue()
        ->and(session()->has('compiled_claim_submission'))->toBeFalse();
});

it('carries compiled claim submission inputs into preparation result', function () {
    $voucher = issueVoucher();

    session()->put('compiled_claim_submission', [
        'code' => $voucher->code,
        'inputs' => [
            'first_name' => 'Lester',
            'email' => 'lester@example.com',
        ],
    ]);

    $result = app(\LBHurtado\XChange\Actions\Claim\PrepareCompiledClaim::class)->handle();

    expect($result->isValid())->toBeTrue()
        ->and($result->submission?->inputs)->toBe([
            'first_name' => 'Lester',
            'email' => 'lester@example.com',
        ]);
});
