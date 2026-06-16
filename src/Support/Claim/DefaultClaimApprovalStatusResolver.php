<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalStatusResolver;

final class DefaultClaimApprovalStatusResolver implements ClaimApprovalStatusResolver
{
    public function __construct(
        private readonly ClaimApprovalPendingOtpStore $pendingOtpStore,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(Voucher $voucher): ?array
    {
        foreach ($this->referenceCandidates($voucher) as $reference) {
            $pending = $this->pendingOtpStore->pending($reference);

            if (! is_array($pending)) {
                continue;
            }

            return [
                'status' => 'approval_required',
                'voucher_code' => (string) $voucher->code,
                'messages' => [
                    'Payout OTP approval required.',
                ],
                'approval_metadata' => [
                    'provider' => data_get($pending, 'provider', 'paynamics'),
                    'authorization_type' => data_get($pending, 'authorization_type', 'otp'),
                    'reference_id' => data_get($pending, 'reference_id', $reference),
                    'otp_required' => true,
                    'expires_at' => data_get($pending, 'expires_at'),
                    'polling_required' => false,
                    'manual_review' => false,
                    'message' => data_get($pending, 'message', 'Paynamics payout OTP is pending.'),
                ],
            ];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function referenceCandidates(Voucher $voucher): array
    {
        $code = (string) $voucher->code;

        return array_values(array_filter(array_unique([
            $code,
            data_get($voucher, 'provider_reference'),
            data_get($voucher, 'reference_id'),
        ]), fn ($value): bool => is_string($value) && trim($value) !== ''));
    }
}
