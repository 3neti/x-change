<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
use LBHurtado\XChange\Support\Claim\ClaimApprovalPendingOtpStore;

final class PaynamicsWithdrawalOtpApprovalService implements WithdrawalOtpApprovalServiceContract
{
    public function __construct(
        private readonly ClaimApprovalPendingOtpStore $store,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function request(string $mobile, string $reference, array $context = []): array
    {
        $pending = $this->store->pending($reference);

        if ($pending !== null) {
            return [
                'provider' => 'paynamics',
                'reference' => $reference,
                'requested' => true,
                'target' => $pending['target'] ?? $this->maskMobile($mobile),
                'message' => $pending['message'] ?? 'Paynamics payout OTP is pending.',
                'context' => [
                    'voucher_code' => data_get($context, 'voucher_code'),
                    'amount' => data_get($context, 'amount'),
                ],
                'approval_metadata' => $pending,
            ];
        }

        Log::info('[PaynamicsWithdrawalOtpApprovalService] Payout OTP request observed without pending metadata.', [
            'mobile' => $this->maskMobile($mobile),
            'reference' => $reference,
            'voucher_code' => data_get($context, 'voucher_code'),
            'amount' => data_get($context, 'amount'),
        ]);

        return [
            'provider' => 'paynamics',
            'reference' => $reference,
            'requested' => false,
            'target' => $this->maskMobile($mobile),
            'message' => 'Paynamics payout OTP has not been requested yet.',
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
        $reference = trim($reference);
        $code = trim($code);

        Log::info('[PaynamicsWithdrawalOtpApprovalService] Payout OTP submitted.', [
            'mobile' => $this->maskMobile($mobile),
            'reference' => $reference,
            'voucher_code' => data_get($context, 'voucher_code'),
        ]);

        if ($reference === '' || $code === '') {
            return false;
        }

        $this->store->putSubmittedOtp($reference, $code);

        return true;
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
