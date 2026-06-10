<?php

declare(strict_types=1);

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
        ->assertRedirect()
        ->assertSessionHas('approval_otp_received', true)
        ->assertSessionHas('approval_otp', [
            'status' => 'received',
            'voucher_code' => $voucher->code,
            'reference_id' => 'AUTH-123',
            'provider' => 'payanamics',
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
        ->assertSessionHas('approval_otp', [
            'status' => 'received',
            'voucher_code' => $voucher->code,
            'reference_id' => null,
            'provider' => null,
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
