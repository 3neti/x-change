<?php

use LBHurtado\XChange\Actions\Claim\SubmitCompiledClaimCompletion;

it('stores compiled claim completion payload in session flash data', function () {
    session()->put('compiled_claim_prepared', [
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    $payload = app(SubmitCompiledClaimCompletion::class)->handle();

    expect($payload)->toBe([
        'source' => 'compiled_claim',
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
        ],
        'compiled_claim' => [
            'code' => 'TEST123',
            'voucher_id' => 123,
            'inputs' => [
                'first_name' => 'Lester',
            ],
        ],
    ])->and(session()->get('compiled_claim_completion_payload'))->toBe($payload);
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
