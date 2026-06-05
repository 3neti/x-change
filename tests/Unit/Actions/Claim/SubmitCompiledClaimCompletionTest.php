<?php

use LBHurtado\XChange\Actions\Claim\SubmitCompiledClaimCompletion;
use LBHurtado\XChange\Support\Claim\CompiledClaimSessionKeys;

it('builds legacy compiled form handoff payload', function () {
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

it('returns null when legacy prepared compiled claim is missing', function () {
    session()->forget('compiled_claim_prepared');

    expect(app(SubmitCompiledClaimCompletion::class)->handle())->toBeNull();
});

it('can forget legacy prepared compiled claim after building handoff payload', function () {
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

it('keeps the legacy compiled form handoff payload flat', function () {
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

it('does not include prepared timestamp until there is an operational consumer', function () {
    session()->put(CompiledClaimSessionKeys::PREPARED, [
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    $payload = app(SubmitCompiledClaimCompletion::class)->handle();

    expect($payload)->not->toHaveKey('prepared_at');
});
