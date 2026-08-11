<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherOperationalStatusResolverContract;
use LBHurtado\XChange\Data\PayCode\PayCodeOperationalStatusData;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;

class DefaultVoucherOperationalStatusResolver implements VoucherOperationalStatusResolverContract
{
    public function resolve(
        Voucher $voucher,
        bool $claimed,
        bool $fullyClaimed,
        bool $approvalRequired = false,
    ): PayCodeOperationalStatusData {
        $claim = VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->latest('id')
            ->first();
        $reconciliation = DisbursementReconciliation::query()
            ->where('voucher_id', $voucher->getKey())
            ->latest('id')
            ->first();
        $claimStatus = $this->normalize($claim?->status);
        $payoutStatus = $this->normalize($reconciliation?->status);
        $internalStatus = $this->normalize($reconciliation?->internal_status);
        $voucherState = $this->normalize($voucher->state->value) ?? 'active';
        $requiresRecovery = data_get($voucher->metadata, 'disbursement.requires_recovery') === true;

        if (
            $requiresRecovery
            || $claimStatus === 'payout_rejected'
            || in_array($payoutStatus, ['failed', 'rejected'], true)
            || $internalStatus === 'recovery_opened'
        ) {
            return $this->status(
                key: 'payout_rejected',
                label: 'Payout Rejected',
                tone: 'critical',
                availabilityKey: 'closed',
                availabilityLabel: 'Closed',
                settlementOutcome: 'rejected',
                terminal: true,
                canClaim: false,
                canRetryPayout: $requiresRecovery
                    && data_get($voucher->metadata, 'treasury.pay_code_reservation.status') === 'recovery_pending',
            );
        }

        if (
            in_array($payoutStatus, ['pending', 'processing', 'queued', 'accepted', 'submitted'], true)
            || in_array($internalStatus, ['pending', 'processing', 'submitted', 'provider_pending'], true)
            || in_array($claimStatus, ['pending', 'processing', 'queued'], true)
        ) {
            return $this->status(
                key: 'payout_pending',
                label: 'Payout Pending',
                tone: 'warning',
                availabilityKey: 'closed',
                availabilityLabel: 'Claim Recorded',
                settlementOutcome: 'pending',
                terminal: false,
                canClaim: false,
            );
        }

        if (
            in_array($payoutStatus, ['succeeded', 'completed', 'paid'], true)
            || $internalStatus === 'finalized'
        ) {
            return $this->status(
                key: 'paid',
                label: 'Paid',
                tone: 'positive',
                availabilityKey: 'closed',
                availabilityLabel: 'Closed',
                settlementOutcome: 'succeeded',
                terminal: true,
                canClaim: false,
            );
        }

        if ($voucherState === 'cancelled') {
            return $this->status(
                key: 'cancelled',
                label: 'Cancelled',
                tone: 'neutral',
                availabilityKey: 'cancelled',
                availabilityLabel: 'Cancelled',
                settlementOutcome: 'not_applicable',
                terminal: true,
                canClaim: false,
            );
        }

        if ($fullyClaimed) {
            return $this->status(
                key: 'redeemed',
                label: 'Redeemed',
                tone: 'positive',
                availabilityKey: 'closed',
                availabilityLabel: 'Closed',
                settlementOutcome: 'not_applicable',
                terminal: true,
                canClaim: false,
            );
        }

        if ($claimed) {
            $claimWindowClosed = $voucher->isClosed()
                || $voucherState === 'expired'
                || $voucher->isExpired();

            return $this->status(
                key: 'partially_claimed',
                label: 'Partially Claimed',
                tone: $claimWindowClosed ? 'neutral' : 'informative',
                availabilityKey: match (true) {
                    $voucher->isClosed() => 'closed',
                    $voucherState === 'expired' || $voucher->isExpired() => 'expired',
                    default => 'claimable',
                },
                availabilityLabel: match (true) {
                    $voucher->isClosed() => 'Closed',
                    $voucherState === 'expired' || $voucher->isExpired() => 'Expired',
                    default => 'Claimable',
                },
                settlementOutcome: 'not_applicable',
                terminal: $claimWindowClosed,
                canClaim: ! $claimWindowClosed,
            );
        }

        if ($voucher->isClosed()) {
            return $this->status(
                key: 'closed',
                label: 'Closed',
                tone: 'neutral',
                availabilityKey: 'closed',
                availabilityLabel: 'Closed',
                settlementOutcome: 'not_applicable',
                terminal: true,
                canClaim: false,
            );
        }

        if ($voucherState === 'expired' || $voucher->isExpired()) {
            return $this->status(
                key: 'expired',
                label: 'Expired',
                tone: 'neutral',
                availabilityKey: 'expired',
                availabilityLabel: 'Expired',
                settlementOutcome: 'not_applicable',
                terminal: true,
                canClaim: false,
            );
        }

        if ($approvalRequired) {
            return $this->status(
                key: 'awaiting_approval',
                label: 'Awaiting Approval',
                tone: 'warning',
                availabilityKey: 'locked',
                availabilityLabel: 'Approval Required',
                settlementOutcome: 'pending',
                terminal: false,
                canClaim: false,
            );
        }

        if ($voucher->starts_at?->isFuture() === true) {
            return $this->status(
                key: 'scheduled',
                label: 'Scheduled',
                tone: 'informative',
                availabilityKey: 'scheduled',
                availabilityLabel: 'Starts Later',
                settlementOutcome: 'not_applicable',
                terminal: false,
                canClaim: false,
            );
        }

        if ($voucherState === 'locked') {
            return $this->status(
                key: 'locked',
                label: 'Locked',
                tone: 'warning',
                availabilityKey: 'locked',
                availabilityLabel: 'Locked',
                settlementOutcome: 'not_applicable',
                terminal: false,
                canClaim: false,
            );
        }

        return $this->status(
            key: 'active',
            label: 'Active',
            tone: 'positive',
            availabilityKey: 'claimable',
            availabilityLabel: 'Claimable',
            settlementOutcome: 'not_applicable',
            terminal: false,
            canClaim: true,
        );
    }

    private function status(
        string $key,
        string $label,
        string $tone,
        string $availabilityKey,
        string $availabilityLabel,
        string $settlementOutcome,
        bool $terminal,
        bool $canClaim,
        bool $canRetryPayout = false,
    ): PayCodeOperationalStatusData {
        return new PayCodeOperationalStatusData(
            key: $key,
            label: $label,
            tone: $tone,
            availability_key: $availabilityKey,
            availability_label: $availabilityLabel,
            settlement_outcome: $settlementOutcome,
            is_terminal: $terminal,
            can_claim: $canClaim,
            can_retry_payout: $canRetryPayout,
        );
    }

    private function normalize(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return $normalized !== '' ? $normalized : null;
    }
}
