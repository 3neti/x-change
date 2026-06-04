<?php

use LBHurtado\XChange\Actions\Claim\SubmitCompiledClaimCompletion;
use LBHurtado\XChange\Support\Claim\CompiledClaimSessionKeys;

it('builds compiled claim completion payload', function () {
    session()->put('compiled_claim_prepared', [
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    $payload = app(SubmitCompiledClaimCompletion::class)->handle();

    expect($payload)->toBe([
        'source' => 'compiled_form',
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);
});

it('returns null when prepared compiled claim is missing', function () {
    session()->forget('compiled_claim_prepared');

    expect(app(SubmitCompiledClaimCompletion::class)->handle())->toBeNull();
});

it('can forget prepared compiled claim after submitting completion payload', function () {
    session()->put('compiled_claim_prepared', [
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    $payload = app(SubmitCompiledClaimCompletion::class)->handle(forget: true);

    expect($payload)->not->toBeNull()
        ->and(session()->has('compiled_claim_prepared'))->toBeFalse();
});

it('submits the current compiled claim completion payload shape', function () {
    session()->put(CompiledClaimSessionKeys::PREPARED, [
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    $payload = app(SubmitCompiledClaimCompletion::class)->handle();

    expect($payload)->toBe([
        'source' => 'compiled_form',
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);
});
