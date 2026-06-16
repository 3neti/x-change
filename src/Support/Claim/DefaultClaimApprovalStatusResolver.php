<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalStatusResolver;
use LBHurtado\XChange\Data\Claims\ApprovalStatusData;

final class DefaultClaimApprovalStatusResolver implements ClaimApprovalStatusResolver
{
    public function __construct(
        private readonly ClaimApprovalPendingOtpStore $pendingOtpStore,
    ) {}

    public function resolve(Voucher $voucher): ?ApprovalStatusData
    {
        foreach ($this->referenceCandidates($voucher) as $reference) {
            $pending = $this->pendingOtpStore->pending($reference);

            if (! is_array($pending)) {
                continue;
            }

            return new ApprovalStatusData(
                status: 'approval_required',
                voucher_code: (string) $voucher->code,
                messages: ['Payout OTP approval required.'],
                provider: data_get($pending, 'provider', 'paynamics'),
                authorization_type: data_get($pending, 'authorization_type', 'otp'),
                reference_id: data_get($pending, 'reference_id', $reference),
                otp_required: true,
                expires_at: data_get($pending, 'expires_at'),
                polling_required: false,
                manual_review: false,
                message: data_get($pending, 'message', 'Paynamics payout OTP is pending.'),
            );
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function referenceCandidates(Voucher $voucher): array
    {
        $code = (string) $voucher->code;
        $metadata = $voucher->exists
            ? $voucher->fresh()?->metadata
            : $voucher->metadata;

        $metadata = is_array($metadata) ? $metadata : [];
        $account = data_get($metadata, 'disbursement.recipient_identifier');

        return array_values(array_filter(array_unique([
            $code,
            data_get($voucher, 'provider_reference'),
            data_get($voucher, 'reference_id'),
            data_get($metadata, 'disbursement.reference_id'),
            data_get($metadata, 'disbursement.provider_reference'),
            data_get($metadata, 'disbursement.provider_tx'),
            data_get($metadata, 'disbursement.transaction_id'),
            data_get($metadata, 'disbursement.request_id'),
            is_string($account) && trim($account) !== ''
                ? $code.'-'.trim($account)
                : null,
        ]), fn ($value): bool => is_string($value) && trim($value) !== ''));
    }
}
