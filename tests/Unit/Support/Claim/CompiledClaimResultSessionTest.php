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
        'approval_metadata' => [
            'provider' => null,
            'authorization_type' => null,
            'reference_id' => null,
            'expires_at' => null,
            'otp_required' => false,
            'polling_required' => false,
            'manual_review' => false,
            'message' => null,
        ],
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

it('stores normalized approval metadata with compiled claim result', function () {
    $result = compiledClaimSessionResult([
        'status' => 'pending',
        'voucher_code' => 'TEST123',
        'messages' => ['Approval required.'],
        'approval_metadata' => [
            'provider' => 'payanamics',
            'authorization_type' => 'otp',
            'reference_id' => 'AUTH-123',
            'otp_required' => true,
            'message' => 'Enter the OTP sent to your mobile number.',
        ],
    ]);

    expect(app(CompiledClaimResultSession::class)->normalize($result))
        ->toMatchArray([
            'status' => 'pending',
            'voucher_code' => 'TEST123',
            'messages' => ['Approval required.'],
            'approval_metadata' => [
                'provider' => 'payanamics',
                'authorization_type' => 'otp',
                'reference_id' => 'AUTH-123',
                'expires_at' => null,
                'otp_required' => true,
                'polling_required' => false,
                'manual_review' => false,
                'message' => 'Enter the OTP sent to your mobile number.',
            ],
        ]);
});
