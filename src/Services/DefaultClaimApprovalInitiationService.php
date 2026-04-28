<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimApprovalInitiationContract;
use LBHurtado\XChange\Contracts\ClaimApprovalNotificationContract;
use LBHurtado\XChange\Contracts\ClaimApprovalWorkflowStoreContract;
use LBHurtado\XChange\Contracts\ClaimOtpChallengeContract;
use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;

class DefaultClaimApprovalInitiationService implements ClaimApprovalInitiationContract
{
    public function __construct(
        protected ClaimApprovalWorkflowStoreContract $store,
        protected ClaimApprovalNotificationContract $notifications,
        protected ClaimOtpChallengeContract $otp,
    ) {}

    public function initiate(Voucher $voucher, array $payload, array $approval): ClaimApprovalInitiationResultData
    {
        $requirements = (array) data_get($approval, 'requirements', []);
        $actions = (array) data_get($approval, 'actions', []);

        $workflow = [
            'status' => 'pending',
            'voucher_code' => (string) $voucher->code,
            'requirements' => $requirements,
            'actions' => $actions,
            'payload' => $payload,
            'approval' => $approval,
            'created_at' => now()->toIso8601String(),
        ];

        if (in_array('otp', $requirements, true)) {
            $workflow['otp'] = $this->otp->request($voucher, $workflow);
        }

        $this->store->put($voucher, $workflow);
        $this->notifications->notify($voucher, $workflow);

        return new ClaimApprovalInitiationResultData(
            voucher_code: (string) $voucher->code,
            status: 'pending_approval',
            requirements: $requirements,
            actions: $actions,
            meta: [
                'workflow' => [
                    'status' => 'pending',
                    'created_at' => $workflow['created_at'],
                ],
                'otp' => $workflow['otp'] ?? null,
            ],
            messages: [
                'Claim approval workflow initiated.',
            ],
        );
    }
}
