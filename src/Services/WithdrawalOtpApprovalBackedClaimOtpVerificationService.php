<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimOtpVerificationContract;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;

class WithdrawalOtpApprovalBackedClaimOtpVerificationService implements ClaimOtpVerificationContract
{
    public function __construct(
        protected WithdrawalOtpApprovalServiceContract $otp,
    ) {}

    public function verify(Voucher $voucher, string $code, array $workflow): bool
    {
        $mobile = (string) (
            data_get($workflow, 'payload.mobile')
            ?? data_get($workflow, 'payload.redeemer.mobile')
            ?? data_get($workflow, 'payload.owner_mobile')
            ?? ''
        );

        $reference = (string) (
            data_get($workflow, 'voucher_code')
            ?? $voucher->code
        );

        return $this->otp->verify(
            mobile: $mobile,
            reference: $reference,
            code: $code,
            context: [
                'voucher_code' => (string) $voucher->code,
                'workflow' => $workflow,
            ],
        );
    }
}
