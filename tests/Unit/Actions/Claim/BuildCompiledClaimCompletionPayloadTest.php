<?php

use LBHurtado\XChange\Actions\Claim\BuildCompiledClaimCompletionPayload;

it('builds form-flow compatible completion payload from prepared compiled claim', function () {
    session()->put('compiled_claim_prepared', [
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
            'email' => 'lester@example.com',
        ],
    ]);

    $payload = app(BuildCompiledClaimCompletionPayload::class)->handle();

    expect($payload)->toBe([
        'source' => 'compiled_form',
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
            'email' => 'lester@example.com',
        ],
    ]);
});

it('returns null when prepared compiled claim is missing', function () {
    session()->forget('compiled_claim_prepared');

    expect(app(BuildCompiledClaimCompletionPayload::class)->handle())->toBeNull();
});

it('can forget prepared compiled claim after building completion payload', function () {
    session()->put('compiled_claim_prepared', [
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    $payload = app(BuildCompiledClaimCompletionPayload::class)->handle(forget: true);

    expect($payload)->not->toBeNull()
        ->and(session()->has('compiled_claim_prepared'))->toBeFalse();
});
