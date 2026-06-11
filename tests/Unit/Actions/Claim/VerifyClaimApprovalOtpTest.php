<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\VerifyClaimApprovalOtp;
use LBHurtado\XChange\Contracts\ClaimApprovalExecutionContract;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;

it('delegates claim approval OTP verification to the approval execution service', function () {
    $voucher = issueVoucher();

    $service = new class implements ClaimApprovalExecutionContract
    {
        public ?Voucher $voucher = null;

        public array $payload = [];

        public function approve(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData
        {
            throw new RuntimeException('Manual approval should not be called.');
        }

        public function verifyOtp(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData
        {
            $this->voucher = $voucher;
            $this->payload = $payload;

            return new SubmitPayCodeClaimResultData(
                status: 'succeeded',
                voucher_code: (string) $voucher->code,
                claim_type: 'redeem',
                claimed: true,
                requested_amount: null,
                disbursed_amount: 75.00,
                currency: 'PHP',
                remaining_balance: 0,
                fully_claimed: true,
                messages: [
                    'OTP verified. Claim resumed.',
                ],
            );
        }
    };

    $result = app(VerifyClaimApprovalOtp::class, [
        'approval' => $service,
    ])->handle($voucher, [
        'otp' => '123456',
        'reference_id' => 'PAYNAMICS-AUTH-123',
    ]);

    expect($service->voucher?->is($voucher))->toBeTrue()
        ->and($service->payload)->toMatchArray([
            'otp' => '123456',
            'reference_id' => 'PAYNAMICS-AUTH-123',
        ])
        ->and($result->status)->toBe('succeeded')
        ->and($result->voucher_code)->toBe($voucher->code)
        ->and($result->messages)->toBe([
            'OTP verified. Claim resumed.',
        ]);
});
