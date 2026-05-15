<?php

namespace LBHurtado\XChange\Support\Rider;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XRider\Enums\RiderOutcomeState;

class XChangeRiderOutcomeResolver
{
    public function forVoucher(Voucher $voucher): RiderOutcomeState
    {
        $status = $this->disbursementStatus($voucher);

        if (
            $this->isPending($status)
            && $this->shouldTreatPendingWithLocalDisbursementAsSuccess()
            && $this->hasLocalDisbursementMarker($voucher)
        ) {
            return RiderOutcomeState::AcceptedSuccess;
        }

        if ($this->isPending($status)) {
            return RiderOutcomeState::AcceptedPending;
        }

        return RiderOutcomeState::AcceptedSuccess;
    }

    protected function shouldTreatPendingWithLocalDisbursementAsSuccess(): bool
    {
        return (bool) config(
            'x-change.rider.outcomes.treat_pending_with_local_disbursement_as_success',
            true
        );
    }

    protected function hasLocalDisbursementMarker(Voucher $voucher): bool
    {
        $metadata = $voucher->metadata ?? [];

        return filled(data_get($metadata, 'disbursement.cash_withdrawal_uuid'))
            || filled(data_get($metadata, 'disbursement.disbursed_at'))
            || filled(data_get($metadata, 'cash_withdrawal_uuid'))
            || filled(data_get($metadata, 'disbursed_at'));
    }

    protected function disbursementStatus(Voucher $voucher): ?string
    {
        $metadata = $voucher->metadata ?? [];

        $status = data_get($metadata, 'disbursement.status')
            ?? data_get($metadata, 'payout.status')
            ?? data_get($metadata, 'redemption.disbursement_status')
            ?? data_get($metadata, 'redemption.disbursement.status')
            ?? data_get($metadata, 'cash.disbursement.status');

        return is_string($status) && $status !== ''
            ? strtolower($status)
            : null;
    }

    protected function isPending(?string $status): bool
    {
        return in_array($status, [
            'pending',
            'processing',
            'queued',
            'in_progress',
            'for_reconciliation',
            'reconciling',
        ], true);
    }
}
