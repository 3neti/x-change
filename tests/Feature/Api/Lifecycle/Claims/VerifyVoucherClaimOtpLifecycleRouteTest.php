<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimApprovalWorkflowStoreContract;
use LBHurtado\XChange\Contracts\ClaimOtpVerificationContract;

beforeEach(function () {
    $this->app->bind(
        ClaimOtpVerificationContract::class,
        fn () => new class implements ClaimOtpVerificationContract
        {
            public function verify(
                Voucher $voucher,
                string $code,
                array $workflow
            ): bool {
                return $code === '123456';
            }
        }
    );
});

it('verifies otp approval workflow through the lifecycle route surface', function () {
    $voucher = issueVoucher(validVoucherInstructions());

    $store = app(ClaimApprovalWorkflowStoreContract::class);

    $store->put($voucher, [
        'status' => 'pending',
        'requirements' => ['otp'],
        'payload' => [
            'mobile' => '639171234567',
        ],
    ]);

    $response = $this->postJson(route('api.x.v1.vouchers.claim.otp.verify', [
        'code' => $voucher->code,
    ]), [
        'otp' => '123456',
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
});

it('returns not found when verifying otp for an unknown voucher code', function () {
    $response = $this->postJson(route('api.x.v1.vouchers.claim.otp.verify', [
        'code' => 'MISSING',
    ]), [
        'otp' => '123456',
    ]);

    $response->assertNotFound();
});
