<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Claim\CompiledClaimResultSession;
use LBHurtado\XChange\Support\Claim\CompiledClaimSuccessPayload;

it('pulls compiled claim result payload from session', function () {
    session()->put(CompiledClaimResultSession::KEY, [
        'status' => 'success',
        'claim_type' => 'withdraw',
        'voucher_code' => 'TEST123',
        'claimed' => true,
        'requested_amount' => null,
        'disbursed_amount' => null,
        'currency' => null,
        'remaining_balance' => null,
        'fully_claimed' => true,
        'messages' => [],
    ]);

    expect(app(CompiledClaimSuccessPayload::class)->pull())->toBe([
        'status' => 'success',
        'claim_type' => 'withdraw',
        'voucher_code' => 'TEST123',
        'claimed' => true,
        'requested_amount' => null,
        'disbursed_amount' => null,
        'currency' => null,
        'remaining_balance' => null,
        'fully_claimed' => true,
        'messages' => [],
    ]);
});

it('clears compiled claim result after pulling', function () {
    session()->put(CompiledClaimResultSession::KEY, [
        'status' => 'success',
        'voucher_code' => 'TEST123',
        'messages' => [],
    ]);

    app(CompiledClaimSuccessPayload::class)->pull();

    expect(session()->has(CompiledClaimResultSession::KEY))->toBeFalse();
});

it('returns null when no compiled claim result exists', function () {
    session()->forget(CompiledClaimResultSession::KEY);

    expect(app(CompiledClaimSuccessPayload::class)->pull())->toBeNull();
});
