<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;

class NullWithdrawalOtpApprovalService implements WithdrawalOtpApprovalServiceContract
{
    public function request(string $mobile, string $reference, array $context = []): array
    {
        return [
            'provider' => 'null',
            'reference' => $reference,
            'mobile' => $mobile,
            'status' => 'requested',
        ];
    }

    public function verify(string $mobile, string $reference, string $code, array $context = []): bool
    {
        return $code === '000000';
    }
}
