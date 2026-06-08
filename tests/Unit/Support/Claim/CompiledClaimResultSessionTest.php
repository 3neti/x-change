<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Claim\CompiledClaimResultSession;

function compiledClaimSessionResult(array $attributes = []): object
{
    return (object) $attributes;
}

it('normalizes compiled claim result for session storage', function () {
    $result = compiledClaimSessionResult([
        'status' => 'success',
        'claim_type' => 'withdraw',
        'voucher_code' => 'TEST123',
        'claimed' => true,
        'requested_amount' => 1000,
        'disbursed_amount' => 1000,
        'currency' => 'PHP',
        'remaining_balance' => 0,
        'fully_claimed' => true,
        'messages' => ['Claim successful.'],
    ]);

    expect(app(CompiledClaimResultSession::class)->normalize($result))->toBe([
        'status' => 'success',
        'claim_type' => 'withdraw',
        'voucher_code' => 'TEST123',
        'claimed' => true,
        'requested_amount' => 1000,
        'disbursed_amount' => 1000,
        'currency' => 'PHP',
        'remaining_balance' => 0,
        'fully_claimed' => true,
        'messages' => ['Claim successful.'],
    ]);
});

it('defaults messages to an empty array', function () {
    $result = compiledClaimSessionResult([
        'status' => 'pending',
        'voucher_code' => 'TEST123',
    ]);

    expect(app(CompiledClaimResultSession::class)->normalize($result))
        ->toMatchArray([
            'status' => 'pending',
            'voucher_code' => 'TEST123',
            'messages' => [],
        ]);
});

it('stores normalized compiled claim result in session', function () {
    app(CompiledClaimResultSession::class)->put(compiledClaimSessionResult([
        'status' => 'success',
        'claim_type' => 'withdraw',
        'voucher_code' => 'TEST123',
        'claimed' => true,
        'messages' => ['Claim successful.'],
    ]));

    expect(session(CompiledClaimResultSession::KEY))->toMatchArray([
        'status' => 'success',
        'claim_type' => 'withdraw',
        'voucher_code' => 'TEST123',
        'claimed' => true,
        'messages' => ['Claim successful.'],
    ]);
});

it('pulls and removes compiled claim result from session', function () {
    session()->put(CompiledClaimResultSession::KEY, [
        'status' => 'success',
        'voucher_code' => 'TEST123',
        'messages' => [],
    ]);

    expect(app(CompiledClaimResultSession::class)->pull())->toBe([
        'status' => 'success',
        'voucher_code' => 'TEST123',
        'messages' => [],
    ]);

    expect(session()->has(CompiledClaimResultSession::KEY))->toBeFalse();
});

it('returns null when pulled session value is missing', function () {
    session()->forget(CompiledClaimResultSession::KEY);

    expect(app(CompiledClaimResultSession::class)->pull())->toBeNull();
});

it('forgets compiled claim result from session', function () {
    session()->put(CompiledClaimResultSession::KEY, [
        'status' => 'success',
    ]);

    app(CompiledClaimResultSession::class)->forget();

    expect(session()->has(CompiledClaimResultSession::KEY))->toBeFalse();
});
