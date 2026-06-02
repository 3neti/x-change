<?php

use LBHurtado\XChange\Actions\Claim\ReadPreparedCompiledClaim;

it('reads prepared compiled claim from session', function () {
    session()->put('compiled_claim_prepared', [
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    $prepared = app(ReadPreparedCompiledClaim::class)->handle();

    expect($prepared)->not->toBeNull()
        ->and($prepared->code)->toBe('TEST123')
        ->and($prepared->voucherId)->toBe(123)
        ->and($prepared->inputs)->toBe([
            'first_name' => 'Lester',
        ])
        ->and(session()->has('compiled_claim_prepared'))->toBeTrue();
});

it('can clear prepared compiled claim from session after reading', function () {
    session()->put('compiled_claim_prepared', [
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    $prepared = app(ReadPreparedCompiledClaim::class)->handle(forget: true);

    expect($prepared)->not->toBeNull()
        ->and(session()->has('compiled_claim_prepared'))->toBeFalse();
});

it('returns null when prepared compiled claim is missing', function () {
    session()->forget('compiled_claim_prepared');

    expect(app(ReadPreparedCompiledClaim::class)->handle())->toBeNull();
});

it('returns null when prepared compiled claim is malformed', function () {
    session()->put('compiled_claim_prepared', [
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => 'not-array',
    ]);

    expect(app(ReadPreparedCompiledClaim::class)->handle())->toBeNull();
});
