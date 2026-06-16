<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitWebPayCodeClaim;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Support\Claim\ClaimApprovalResumePayloadSession;
use LBHurtado\XChange\Support\Claim\CompiledClaimResultSession;

it('accepts approval OTP payload for an existing voucher', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    $this
        ->post(route('x-change.claim.approval.otp', [
            'code' => $voucher->code,
        ]), [
            'otp' => '123456',
            'reference_id' => 'AUTH-123',
            'provider' => 'paynamics',
        ])
        ->assertRedirect(route('x-change.claim.approval', [
            'code' => $voucher->code,
        ]));

    expect(session(CompiledClaimResultSession::KEY))->toMatchArray([
        'status' => 'received',
        'voucher_code' => $voucher->code,
        'messages' => [
            'Approval OTP received.',
        ],
        'approval_metadata' => [
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => 'AUTH-123',
            'expires_at' => null,
            'otp_required' => true,
            'polling_required' => false,
            'manual_review' => false,
            'message' => 'Approval OTP received.',
        ],
    ]);
});

it('requires OTP for approval OTP payload', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    $this
        ->post(route('x-change.claim.approval.otp', [
            'code' => $voucher->code,
        ]), [
            'reference_id' => 'AUTH-123',
            'provider' => 'paynamics',
        ])
        ->assertSessionHasErrors('otp');
});

it('allows optional approval OTP metadata', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    $this
        ->post(route('x-change.claim.approval.otp', [
            'code' => $voucher->code,
        ]), [
            'otp' => '123456',
        ])
        ->assertRedirect()
        ->assertRedirect(route('x-change.claim.approval', [
            'code' => $voucher->code,
        ]));

    $provider = strtolower(\LBHurtado\XChange\Tests\Fakes\FakePayoutProvider::class);

    expect(session(CompiledClaimResultSession::KEY))->toMatchArray([
        'status' => 'received',
        'voucher_code' => $voucher->code,
        'messages' => [
            'Approval OTP received.',
        ],
        'approval_metadata' => [
            'provider' => $provider,
            'authorization_type' => 'otp',
            'reference_id' => null,
            'expires_at' => null,
            'otp_required' => true,
            'polling_required' => false,
            'manual_review' => false,
            'message' => 'Approval OTP received.',
        ],
    ]);
});

it('404s approval OTP payload for missing voucher', function () {
    $this->withoutMiddleware();

    $this
        ->post(route('x-change.claim.approval.otp', [
            'code' => 'MISSING-CODE',
        ]), [
            'otp' => '123456',
        ])
        ->assertNotFound();
});

it('redirects completed approval OTP result to success page', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    $this->app->bind(
        ClaimApprovalOtpAuthorizer::class,
        fn () => new class implements ClaimApprovalOtpAuthorizer
        {
            public function authorize(Voucher $voucher, array $payload): array
            {
                return [
                    'status' => 'completed',
                    'voucher_code' => (string) $voucher->code,
                    'reference_id' => $payload['reference_id'] ?? null,
                    'provider' => $payload['provider'] ?? null,
                    'messages' => ['OTP verified.'],
                ];
            }
        }
    );

    $this
        ->post(route('x-change.claim.approval.otp', [
            'code' => $voucher->code,
        ]), [
            'otp' => '123456',
            'reference_id' => 'AUTH-123',
            'provider' => 'paynamics',
        ])
        ->assertRedirect(route('x-change.claim.success', [
            'code' => $voucher->code,
        ]));

    expect(session(CompiledClaimResultSession::KEY))->toMatchArray([
        'status' => 'completed',
        'voucher_code' => $voucher->code,
        'messages' => ['OTP verified.'],
    ]);
});

it('rehydrates approval page with OTP metadata after received OTP result', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    $this
        ->post(route('x-change.claim.approval.otp', [
            'code' => $voucher->code,
        ]), [
            'otp' => '123456',
            'reference_id' => 'AUTH-123',
            'provider' => 'paynamics',
        ])
        ->assertRedirect(route('x-change.claim.approval', [
            'code' => $voucher->code,
        ]));

    $this
        ->getJson(route('x-change.claim.approval', [
            'code' => $voucher->code,
        ]))
        ->assertOk()
        ->assertJsonPath('compiled_claim_result.status', 'received')
        ->assertJsonPath('compiled_claim_result.approval_metadata.provider', 'paynamics')
        ->assertJsonPath('compiled_claim_result.approval_metadata.authorization_type', 'otp')
        ->assertJsonPath('compiled_claim_result.approval_metadata.reference_id', 'AUTH-123')
        ->assertJsonPath('compiled_claim_result.approval_metadata.otp_required', true)
        ->assertJsonPath('compiled_claim_result.approval_metadata.message', 'Approval OTP received.');
});

it('hydrates success page after completed approval OTP result', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    $this->app->bind(
        ClaimApprovalOtpAuthorizer::class,
        fn () => new class implements ClaimApprovalOtpAuthorizer
        {
            public function authorize(Voucher $voucher, array $payload): array
            {
                return [
                    'status' => 'completed',
                    'voucher_code' => (string) $voucher->code,
                    'claim_type' => 'withdraw',
                    'claimed' => true,
                    'requested_amount' => null,
                    'disbursed_amount' => 1000,
                    'currency' => 'PHP',
                    'remaining_balance' => 0,
                    'fully_claimed' => true,
                    'reference_id' => $payload['reference_id'] ?? null,
                    'provider' => $payload['provider'] ?? null,
                    'messages' => ['OTP verified. Claim completed.'],
                    'approval_metadata' => [
                        'provider' => $payload['provider'] ?? null,
                        'authorization_type' => 'otp',
                        'reference_id' => $payload['reference_id'] ?? null,
                        'expires_at' => null,
                        'otp_required' => false,
                        'polling_required' => false,
                        'manual_review' => false,
                        'message' => 'OTP verified. Claim completed.',
                    ],
                ];
            }
        }
    );

    $this
        ->post(route('x-change.claim.approval.otp', [
            'code' => $voucher->code,
        ]), [
            'otp' => '123456',
            'reference_id' => 'AUTH-123',
            'provider' => 'paynamics',
        ])
        ->assertRedirect(route('x-change.claim.success', [
            'code' => $voucher->code,
        ]));

    $this
        ->getJson(route('x-change.claim.success', [
            'code' => $voucher->code,
        ]))
        ->assertOk()
        ->assertJsonPath('compiled_claim_result.status', 'completed')
        ->assertJsonPath('compiled_claim_result.voucher_code', $voucher->code)
        ->assertJsonPath('compiled_claim_result.claim_type', 'withdraw')
        ->assertJsonPath('compiled_claim_result.claimed', true)
        ->assertJsonPath('compiled_claim_result.disbursed_amount', 1000)
        ->assertJsonPath('compiled_claim_result.currency', 'PHP')
        ->assertJsonPath('compiled_claim_result.messages.0', 'OTP verified. Claim completed.')
        ->assertJsonPath('compiled_claim_result.approval_metadata.provider', 'paynamics')
        ->assertJsonPath('compiled_claim_result.approval_metadata.reference_id', 'AUTH-123');
});

it('replays claim after completed approval OTP submission', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    app(ClaimApprovalResumePayloadSession::class)->put($voucher, [
        'mobile' => '639171234567',
        'bank_code' => 'GXI',
        'account_number' => '09173011987',
    ]);

    $submitWebClaim = Mockery::mock(SubmitWebPayCodeClaim::class);

    $submitWebClaim->shouldReceive('handle')
        ->once()
        ->withArgs(function ($givenVoucher, array $payload) use ($voucher): bool {
            return $givenVoucher->is($voucher)
                && data_get($payload, 'approval.resume') === true
                && data_get($payload, 'approval.reference_id') === 'TEST-Z3EL-09173011987-S1'
                && data_get($payload, 'otp.verified') === true
                && data_get($payload, 'otp.code') === '441498'
                && $payload['mobile'] === '639171234567';
        })
        ->andReturn(new SubmitPayCodeClaimResultData(
            voucher_code: (string) $voucher->code,
            claim_type: 'withdraw',
            claimed: true,
            status: 'withdrawn',
            requested_amount: 10.00,
            disbursed_amount: 10.00,
            currency: 'PHP',
            remaining_balance: 0,
            fully_claimed: true,
            disbursement: [
                'status' => 'requested',
            ],
            messages: [
                'Voucher withdrawal successful.',
            ],
        ));

    app()->instance(SubmitWebPayCodeClaim::class, $submitWebClaim);

    $this->app->bind(
        \LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer::class,
        fn () => new class implements \LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer
        {
            public function authorize(\LBHurtado\Voucher\Models\Voucher $voucher, array $payload): array
            {
                return [
                    'status' => 'completed',
                    'voucher_code' => (string) $voucher->code,
                    'reference_id' => $payload['reference_id'],
                    'provider' => $payload['provider'],
                    'messages' => ['Approval OTP verified.'],
                    'approval_metadata' => [
                        'provider' => $payload['provider'],
                        'authorization_type' => 'otp',
                        'reference_id' => $payload['reference_id'],
                        'otp_required' => false,
                        'message' => 'Approval OTP verified.',
                    ],
                ];
            }
        }
    );

    $this->post(route('x-change.claim.approval.otp', [
        'code' => $voucher->code,
    ]), [
        'otp' => '441498',
        'reference_id' => 'TEST-Z3EL-09173011987-S1',
        'provider' => 'paynamics',
    ])->assertRedirect(route('x-change.claim.success', [
        'code' => $voucher->code,
    ]));

    expect(app(ClaimApprovalResumePayloadSession::class)->get($voucher))->toBeNull();
});

it('keeps approval metadata available after failed OTP verification', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    app(CompiledClaimResultSession::class)->put((object) [
        'status' => 'approval_required',
        'voucher_code' => (string) $voucher->code,
        'messages' => ['Payout OTP approval required.'],
        'meta' => [
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => 'AUTH-123',
            'otp_required' => true,
            'message' => 'Paynamics payout OTP is pending.',
        ],
    ]);

    $this->app->bind(
        \LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer::class,
        fn () => new class implements \LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer {
            public function authorize(\LBHurtado\Voucher\Models\Voucher $voucher, array $payload): array
            {
                return [
                    'status' => 'failed',
                    'voucher_code' => (string) $voucher->code,
                    'messages' => ['Invalid OTP.'],
                    'approval_metadata' => [
                        'provider' => 'paynamics',
                        'authorization_type' => 'otp',
                        'reference_id' => $payload['reference_id'],
                        'otp_required' => true,
                        'message' => 'Paynamics payout OTP is pending.',
                    ],
                ];
            }
        }
    );

    $response = $this->from(route('x-change.claim.approval', [
        'code' => $voucher->code,
    ]))->post(route('x-change.claim.approval.otp', [
        'code' => $voucher->code,
    ]), [
        'otp' => '000000',
        'reference_id' => 'AUTH-123',
        'provider' => 'paynamics',
    ]);

    $response
        ->assertRedirect(route('x-change.claim.approval', [
            'code' => $voucher->code,
        ]))
        ->assertSessionHasErrors(['otp']);

    $compiled = session(CompiledClaimResultSession::KEY);

    expect($compiled)->toMatchArray([
        'status' => 'failed',
        'voucher_code' => (string)$voucher->code,
    ])
        ->and($compiled['approval_metadata'])->toMatchArray([
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => 'AUTH-123',
            'otp_required' => true,
        ]);
});

