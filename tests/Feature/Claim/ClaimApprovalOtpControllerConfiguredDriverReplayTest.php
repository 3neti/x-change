<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitWebPayCodeClaim;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Services\PaynamicsWithdrawalOtpApprovalService;
use LBHurtado\XChange\Support\Claim\ClaimApprovalPendingOtpStore;
use LBHurtado\XChange\Support\Claim\ClaimApprovalResumePayloadSession;

it('replays claim after approval OTP through configured withdrawal OTP driver', function () {
    $this->withoutMiddleware();

    config()->set('x-change.claim_approval.otp.driver', 'withdrawal_otp');

    app()->bind(
        WithdrawalOtpApprovalServiceContract::class,
        PaynamicsWithdrawalOtpApprovalService::class,
    );

    $voucher = issueVoucher();

    app(ClaimApprovalResumePayloadSession::class)->put($voucher, [
        'mobile' => '639171234567',
        'bank_code' => 'GXI',
        'account_number' => '09173011987',
    ]);

    $submitWebClaim = Mockery::mock(SubmitWebPayCodeClaim::class);

    $submitWebClaim->shouldReceive('handle')
        ->once()
        ->withArgs(function (Voucher $givenVoucher, array $payload) use ($voucher): bool {
            return $givenVoucher->is($voucher)
                && $payload['mobile'] === '639171234567'
                && data_get($payload, 'approval.resume') === true
                && data_get($payload, 'approval.provider') === 'paynamics'
                && data_get($payload, 'approval.reference_id') === 'TEST-Z3EL-09173011987-S1'
                && data_get($payload, 'approval.authorization_type') === 'otp'
                && data_get($payload, 'otp.verified') === true
                && data_get($payload, 'otp.code') === '441498';
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
