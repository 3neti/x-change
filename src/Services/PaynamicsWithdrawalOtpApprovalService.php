<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;

final class PaynamicsWithdrawalOtpApprovalService implements WithdrawalOtpApprovalServiceContract
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function request(string $mobile, string $reference, array $context = []): array
    {
        Log::info('[PaynamicsWithdrawalOtpApprovalService] Payout OTP request delegated.', [
            'mobile' => $this->maskMobile($mobile),
            'reference' => $reference,
            'voucher_code' => data_get($context, 'voucher_code'),
            'amount' => data_get($context, 'amount'),
        ]);

        return [
            'provider' => 'paynamics',
            'reference' => $reference,
            'requested' => true,
            'target' => $this->maskMobile($mobile),
            'message' => 'Paynamics payout OTP requested.',
            'context' => [
                'voucher_code' => data_get($context, 'voucher_code'),
                'amount' => data_get($context, 'amount'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function verify(string $mobile, string $reference, string $code, array $context = []): bool
    {
        Log::info('[PaynamicsWithdrawalOtpApprovalService] Payout OTP verification delegated.', [
            'mobile' => $this->maskMobile($mobile),
            'reference' => $reference,
            'voucher_code' => data_get($context, 'voucher_code'),
        ]);

        /*
         * Conservative first slice:
         *
         * This confirms the x-change driver slot without pretending that
         * Paynamics verification has succeeded.
         *
         * The next slice should replace this false return with a call to the
         * concrete emi-paynamics OTP/cash-out adapter.
         */
        return false;
    }

    private function maskMobile(string $mobile): string
    {
        $length = strlen($mobile);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max(0, $length - 4)).substr($mobile, -4);
    }
}
