<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Claim\CompiledClaimResultSession;

it('renders approval payload for a pending compiled claim', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    session()->put(CompiledClaimResultSession::KEY, [
        'status' => 'pending',
        'claim_type' => 'withdraw',
        'voucher_code' => $voucher->code,
        'claimed' => false,
        'requested_amount' => null,
        'disbursed_amount' => null,
        'currency' => null,
        'remaining_balance' => null,
        'fully_claimed' => false,
        'messages' => ['Approval required.'],
    ]);

    $this
        ->getJson(route('x-change.claim.approval', [
            'code' => $voucher->code,
        ]))
        ->assertOk()
        ->assertJsonPath('voucher.code', $voucher->code)
        ->assertJsonPath('compiled_claim_result.status', 'pending')
        ->assertJsonPath('compiled_claim_result.messages.0', 'Approval required.')
        ->assertJsonPath('message', 'Your claim has been submitted and is awaiting approval.');
});

it('pulls pending compiled claim result after rendering approval payload', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    session()->put(CompiledClaimResultSession::KEY, [
        'status' => 'pending',
        'voucher_code' => $voucher->code,
        'messages' => [],
    ]);

    $result = $this
        ->getJson(route('x-change.claim.approval', [
            'code' => $voucher->code,
        ]))
        ->assertOk()
        ->assertJsonPath('compiled_claim_result.status', 'pending');


    expect(session()->has(CompiledClaimResultSession::KEY))->toBeTrue();
});

it('404s approval page for missing voucher', function () {
    $this->withoutMiddleware();

    $this
        ->getJson(route('x-change.claim.approval', [
            'code' => 'MISSING-CODE',
        ]))
        ->assertNotFound();
});

it('renders approval metadata from compiled claim result', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    session()->put(CompiledClaimResultSession::KEY, [
        'status' => 'approval_required',
        'voucher_code' => $voucher->code,
        'messages' => [
            'Payout OTP approval required.',
        ],
        'approval_metadata' => [
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => $voucher->code.'-09173011987',
            'otp_required' => true,
            'expires_at' => null,
            'polling_required' => false,
            'manual_review' => false,
            'message' => 'Paynamics payout OTP is pending.',
        ],
    ]);

    $this
        ->getJson(route('x-change.claim.approval', [
            'code' => $voucher->code,
        ]))
        ->assertOk()
        ->assertJsonPath('compiled_claim_result.status', 'approval_required')
        ->assertJsonPath('compiled_claim_result.voucher_code', $voucher->code)
        ->assertJsonPath('compiled_claim_result.messages.0', 'Payout OTP approval required.')
        ->assertJsonPath('compiled_claim_result.approval_metadata.provider', 'paynamics')
        ->assertJsonPath('compiled_claim_result.approval_metadata.authorization_type', 'otp')
        ->assertJsonPath('compiled_claim_result.approval_metadata.reference_id', $voucher->code.'-09173011987')
        ->assertJsonPath('compiled_claim_result.approval_metadata.otp_required', true)
        ->assertJsonPath('approval.required', true)
        ->assertJsonPath('approval.provider', 'paynamics')
        ->assertJsonPath('approval.authorization_type', 'otp')
        ->assertJsonPath('approval.reference_id', $voucher->code.'-09173011987')
        ->assertJsonPath('approval.otp_required', true)
        ->assertJsonPath('approval.message', 'Paynamics payout OTP is pending.');
    ;

    expect(session()->has(CompiledClaimResultSession::KEY))->toBeTrue();
});

