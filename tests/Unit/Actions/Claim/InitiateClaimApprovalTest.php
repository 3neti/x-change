<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\InitiateClaimApproval;
use LBHurtado\XChange\Contracts\ClaimApprovalInitiationContract;
use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;

it('delegates claim approval initiation to the approval initiation service', function () {
    $voucher = issueVoucher();

    $payload = [
        'mobile' => '639171234567',
        'bank_code' => 'GXI',
        'account_number' => '09173011987',
        'inputs' => [
            'name' => 'Juan Dela Cruz',
        ],
    ];

    $approval = [
        'requirements' => ['otp'],
        'provider' => 'paynamics',
        'reason' => 'Payout OTP required.',
    ];

    $service = new class implements ClaimApprovalInitiationContract
    {
        public ?Voucher $voucher = null;

        public array $payload = [];

        public array $approval = [];

        public function initiate(Voucher $voucher, array $payload, array $approval): ClaimApprovalInitiationResultData
        {
            $this->voucher = $voucher;
            $this->payload = $payload;
            $this->approval = $approval;

            return new ClaimApprovalInitiationResultData(
                voucher_code: $voucher->code,
                status: 'approval_required',
                requirements: ['otp'],
                actions: [
                    [
                        'type' => 'otp',
                        'label' => 'Enter Payout OTP',
                        'endpoint' => '/x/claim/'.$voucher->code.'/approval/otp',
                    ],
                ],
                meta: [
                    'provider' => 'paynamics',
                    'reference_id' => 'APPROVAL-123',
                ],
                messages: [
                    'Payout OTP approval required.',
                ],
            );
        }
    };

    $result = app(InitiateClaimApproval::class, [
        'approval' => $service,
    ])->handle($voucher, $payload, $approval);

    expect($service->voucher?->is($voucher))->toBeTrue()
        ->and($service->payload)->toBe($payload)
        ->and($service->approval)->toBe($approval)
        ->and($result->status)->toBe('approval_required')
        ->and($result->requirements)->toBe(['otp'])
        ->and($result->meta)->toMatchArray([
            'provider' => 'paynamics',
            'reference_id' => 'APPROVAL-123',
        ])
        ->and($result->messages)->toBe([
            'Payout OTP approval required.',
        ]);
});
