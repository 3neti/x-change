<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Claim\ClaimApprovalResumePayload;

it('builds claim replay payload after OTP approval', function () {
    $voucher = issueVoucher();

    $payload = app(ClaimApprovalResumePayload::class)->build($voucher, [
        'otp' => '441498',
        'reference_id' => 'TEST-Z3EL-09173011987-S1',
        'provider' => 'paynamics',
    ]);

    expect($payload)->toMatchArray([
        'voucher_code' => $voucher->code,
        'approval' => [
            'resume' => true,
            'provider' => 'paynamics',
            'reference_id' => 'TEST-Z3EL-09173011987-S1',
            'authorization_type' => 'otp',
        ],
        'otp' => [
            'verified' => true,
            'code' => '441498',
        ],
    ]);
});
