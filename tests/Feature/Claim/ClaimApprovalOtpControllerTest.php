<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer;
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
            'provider' => 'payanamics',
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
            'provider' => 'payanamics',
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
            'provider' => 'payanamics',
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

    expect(session(CompiledClaimResultSession::KEY))->toMatchArray([
        'status' => 'received',
        'voucher_code' => $voucher->code,
        'messages' => [
            'Approval OTP received.',
        ],
        'approval_metadata' => [
            'provider' => null,
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
            'provider' => 'payanamics',
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
            'provider' => 'payanamics',
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
        ->assertJsonPath('compiled_claim_result.approval_metadata.provider', 'payanamics')
        ->assertJsonPath('compiled_claim_result.approval_metadata.authorization_type', 'otp')
        ->assertJsonPath('compiled_claim_result.approval_metadata.reference_id', 'AUTH-123')
        ->assertJsonPath('compiled_claim_result.approval_metadata.otp_required', true)
        ->assertJsonPath('compiled_claim_result.approval_metadata.message', 'Approval OTP received.');
});
