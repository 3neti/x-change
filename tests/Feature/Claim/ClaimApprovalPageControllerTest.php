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

    $this
        ->getJson(route('x-change.claim.approval', [
            'code' => $voucher->code,
        ]))
        ->assertOk()
        ->assertJsonPath('compiled_claim_result.status', 'pending');

    expect(session()->has(CompiledClaimResultSession::KEY))->toBeFalse();
});

it('404s approval page for missing voucher', function () {
    $this->withoutMiddleware();

    $this
        ->getJson(route('x-change.claim.approval', [
            'code' => 'MISSING-CODE',
        ]))
        ->assertNotFound();
});
