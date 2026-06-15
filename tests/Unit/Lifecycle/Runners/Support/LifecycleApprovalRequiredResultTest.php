<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleApprovalRequiredResult;

it('normalizes approval required claim result for lifecycle output', function () {
    $claim = new ClaimApprovalInitiationResultData(
        voucher_code: 'TEST-OTP',
        status: 'approval_required',
        requirements: ['otp'],
        actions: ['otp'],
        meta: [
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => 'TEST-OTP-REQUEST',
        ],
        messages: [
            'Payout OTP approval required.',
        ],
    );

    $actual = app(LifecycleApprovalRequiredResult::class)->toActual($claim);

    expect(app(LifecycleApprovalRequiredResult::class)->isApprovalRequired($claim))->toBeTrue()
        ->and($actual)->toMatchArray([
            'status' => 'pending_approval',
            'message' => 'Approval required: paynamics OTP [TEST-OTP-REQUEST]',
            'approval' => [
                'provider' => 'paynamics',
                'authorization_type' => 'otp',
                'reference_id' => 'TEST-OTP-REQUEST',
            ],
            'disbursement_check' => null,
        ]);
});
