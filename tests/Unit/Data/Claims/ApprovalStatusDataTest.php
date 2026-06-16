<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Claims\ApprovalStatusData;

it('converts approval status to compiled claim result payload', function () {
    $payload = (new ApprovalStatusData(
        status: 'approval_required',
        voucher_code: 'TEST-OTP',
        messages: ['Payout OTP approval required.'],
        provider: 'paynamics',
        authorization_type: 'otp',
        reference_id: 'TEST-OTP-09173011987',
        otp_required: true,
        expires_at: null,
        polling_required: false,
        manual_review: false,
        message: 'Paynamics payout OTP is pending.',
    ))->toCompiledClaimResult();

    expect($payload)->toMatchArray([
        'status' => 'approval_required',
        'voucher_code' => 'TEST-OTP',
        'messages' => ['Payout OTP approval required.'],
    ])
        ->and($payload['approval_metadata'])->toMatchArray([
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => 'TEST-OTP-09173011987',
            'otp_required' => true,
            'polling_required' => false,
            'manual_review' => false,
            'message' => 'Paynamics payout OTP is pending.',
        ]);

});
