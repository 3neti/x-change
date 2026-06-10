<?php

declare(strict_types=1);

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
        \LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer::class,
        fn () => new class implements \LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer
        {
            public function authorize(\LBHurtado\Voucher\Models\Voucher $voucher, array $payload): array
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
