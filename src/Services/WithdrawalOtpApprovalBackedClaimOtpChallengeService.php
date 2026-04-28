<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimOtpChallengeContract;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;

class WithdrawalOtpApprovalBackedClaimOtpChallengeService implements ClaimOtpChallengeContract
{
    public function __construct(
        protected WithdrawalOtpApprovalServiceContract $otp,
    ) {}

    /**
     * @param  array<string, mixed>  $workflow
     * @return array<string, mixed>
     */
    public function request(Voucher $voucher, array $workflow): array
    {
        $target = (string) (
            data_get($workflow, 'payload.mobile')
            ?? data_get($workflow, 'payload.redeemer.mobile')
            ?? data_get($workflow, 'payload.owner_mobile')
            ?? ''
        );

        $reference = (string) ($voucher->code ?? data_get($workflow, 'voucher_code'));

        $amount = data_get($workflow, 'payload.amount');

        $result = $this->otp->request(
            mobile: $target,
            reference: $reference,
            context: [
                'amount' => $amount,
                'voucher_code' => (string) $voucher->code,
                'workflow' => $workflow,
            ],
        );

        $meta = is_array($result) ? $result : [];

        return [
            'driver' => 'withdrawal_otp',
            'requested' => true,
            'reference' => data_get($meta, 'reference'),
            'target' => $target,
            'meta' => $meta,
        ];
    }
}
