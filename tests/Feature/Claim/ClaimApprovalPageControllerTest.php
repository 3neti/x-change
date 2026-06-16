<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalStatusResolver;
use LBHurtado\XChange\Contracts\ClaimApprovalWorkflowStoreContract;
use LBHurtado\XChange\Data\Claims\ApprovalStatusData;
use LBHurtado\XChange\Models\DisbursementReconciliation;
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
        ->assertJsonPath('approval_entry_mode', 'redeemer_waiting')
        ->assertJsonPath('message', 'Your claim has been submitted and is awaiting approval.');
});

it('renders issuer approval page in OTP entry mode', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    session()->put(CompiledClaimResultSession::KEY, [
        'status' => 'approval_required',
        'voucher_code' => $voucher->code,
        'messages' => ['Payout OTP approval required.'],
        'approval_metadata' => [
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => $voucher->code.'-09173011987',
            'otp_required' => true,
            'message' => 'Paynamics payout OTP is pending.',
        ],
    ]);

    $this
        ->getJson(route('x-change.pay-codes.approval', [
            'code' => $voucher->code,
        ]))
        ->assertOk()
        ->assertJsonPath('approval_entry_mode', 'issuer_otp_entry')
        ->assertJsonPath('approval.reference_id', $voucher->code.'-09173011987')
        ->assertJsonPath('approval.otp_required', true);
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

    expect(session()->has(CompiledClaimResultSession::KEY))->toBeTrue();
});

it('hydrates approval page from approval status resolver when session result is missing', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    session()->forget(CompiledClaimResultSession::KEY);

    $this->app->bind(
        ClaimApprovalStatusResolver::class,
        fn () => new class implements ClaimApprovalStatusResolver
        {
            public function resolve(Voucher $voucher): ?ApprovalStatusData
            {
                return new ApprovalStatusData(
                    status: 'approval_required',
                    voucher_code: (string) $voucher->code,
                    messages: ['Payout OTP approval required.'],
                    provider: 'paynamics',
                    authorization_type: 'otp',
                    reference_id: $voucher->code.'-09173011987',
                    otp_required: true,
                    message: 'Paynamics payout OTP is pending.',
                );
            }
        }
    );

    $this
        ->getJson(route('x-change.claim.approval', [
            'code' => $voucher->code,
        ]))
        ->assertOk()
        ->assertJsonPath('compiled_claim_result.status', 'approval_required')
        ->assertJsonPath('compiled_claim_result.voucher_code', $voucher->code)
        ->assertJsonPath('compiled_claim_result.approval_metadata.provider', 'paynamics')
        ->assertJsonPath('compiled_claim_result.approval_metadata.reference_id', $voucher->code.'-09173011987')
        ->assertJsonPath('approval.required', true)
        ->assertJsonPath('approval.provider', 'paynamics')
        ->assertJsonPath('approval.reference_id', $voucher->code.'-09173011987');
});

it('hydrates issuer approval page from cached approval workflow when session result is missing', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    session()->forget(CompiledClaimResultSession::KEY);

    app(ClaimApprovalWorkflowStoreContract::class)->put($voucher, [
        'status' => 'pending',
        'voucher_code' => (string) $voucher->code,
        'payload' => [
            'amount' => 50.00,
            'bank_account' => [
                'bank_code' => 'GXCHPHM2XXX',
                'account_number' => '09173011987',
            ],
        ],
        'approval' => [
            'status' => 'approval_required',
            'voucher_code' => (string) $voucher->code,
            'messages' => ['Payout OTP approval required.'],
            'meta' => [
                'provider' => 'paynamics',
                'authorization_type' => 'otp',
                'reference_id' => $voucher->code.'-09173011987',
                'otp_required' => true,
                'message' => 'Paynamics payout OTP is pending.',
            ],
        ],
    ]);

    $this
        ->getJson(route('x-change.pay-codes.approval', [
            'code' => $voucher->code,
        ]))
        ->assertOk()
        ->assertJsonPath('approval_entry_mode', 'issuer_otp_entry')
        ->assertJsonPath('compiled_claim_result.status', 'approval_required')
        ->assertJsonPath('compiled_claim_result.approval_metadata.provider', 'paynamics')
        ->assertJsonPath('compiled_claim_result.approval_metadata.reference_id', $voucher->code.'-09173011987')
        ->assertJsonPath('approval.required', true)
        ->assertJsonPath('approval.reference_id', $voucher->code.'-09173011987');
});

it('redirects stale redeemer approval page to success after Paynamics payout is submitted', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();
    $reference = $voucher->code.'-09173011987';
    $voucher->metadata = [
        'disbursement' => [
            'transaction_id' => $reference,
            'recipient_identifier' => '09173011987',
        ],
    ];
    $voucher->save();

    session()->put(CompiledClaimResultSession::KEY, [
        'status' => 'approval_required',
        'voucher_code' => $voucher->code,
        'messages' => ['Payout OTP approval required.'],
        'approval_metadata' => [
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => $reference,
            'otp_required' => true,
            'message' => 'Paynamics payout OTP is pending.',
        ],
    ]);

    DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->getKey(),
        'voucher_code' => (string) $voucher->code,
        'claim_type' => 'redeem',
        'provider' => 'paynamics',
        'provider_reference' => $reference,
        'provider_transaction_id' => $reference,
        'status' => 'pending',
        'internal_status' => 'recorded',
        'amount' => 47.00,
        'currency' => 'PHP',
    ]);

    $this
        ->get(route('x-change.claim.approval', [
            'code' => $voucher->code,
        ]))
        ->assertRedirect(route('x-change.claim.success', [
            'code' => $voucher->code,
        ]));

    expect(session(CompiledClaimResultSession::KEY))
        ->toMatchArray([
            'status' => 'redeemed',
            'claim_type' => 'redeem',
            'voucher_code' => $voucher->code,
            'claimed' => true,
            'requested_amount' => 47.00,
            'disbursed_amount' => 47.00,
            'currency' => 'PHP',
            'fully_claimed' => true,
        ]);
});
