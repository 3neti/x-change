<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Data\Funding\NetbankReusableFundingObservationData;
use LBHurtado\XChange\Data\Funding\NetbankReusableFundingReceiptHistoryData;
use LBHurtado\XChange\Models\StandingFundingAddress;

final class ReadNetbankReusableFundingReceiptHistory
{
    public function handle(
        Model $owner,
        string $addressReference,
    ): NetbankReusableFundingReceiptHistoryData {
        $address = StandingFundingAddress::query()
            ->whereMorphedTo('owner', $owner)
            ->where('reference', $addressReference)
            ->where('provider_code', 'netbank')
            ->where('purpose', FundingAddressPurpose::AccountFunding)
            ->first();

        if (! $address instanceof StandingFundingAddress) {
            return new NetbankReusableFundingReceiptHistoryData([], null);
        }

        return $this->forAddress($address);
    }

    public function forAddress(
        StandingFundingAddress $address,
    ): NetbankReusableFundingReceiptHistoryData {
        $observations = $address->receipts()
            ->select([
                'id',
                'reference',
                'standing_funding_address_id',
                'provider_funding_observation_id',
                'status',
                'gross_amount_minor',
                'fee_amount_minor',
                'net_amount_minor',
                'currency',
                'treasury_operation_reference',
                'wallet_transaction_id',
                'settled_at',
                'observed_at',
                'metadata',
            ])
            ->with([
                'providerFundingObservation:id,provider_status,occurred_at,settled_at',
            ])
            ->where('status', '!=', 'ignored')
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(static function ($receipt): NetbankReusableFundingObservationData {
                $applied = $receipt->status->value === 'settled'
                    && $receipt->wallet_transaction_id !== null
                    && $receipt->treasury_operation_reference !== null;

                return new NetbankReusableFundingObservationData(
                    reference: 'AF-'.strtoupper(substr($receipt->reference, -12)),
                    grossAmountMinor: $receipt->gross_amount_minor,
                    feeAmountMinor: $receipt->fee_amount_minor,
                    netAmountMinor: $receipt->net_amount_minor,
                    currency: $receipt->currency,
                    recognitionStatus: $receipt->status->value,
                    providerStatus: $receipt->providerFundingObservation?->provider_status
                        ?? 'unknown',
                    applied: $applied,
                    appliedAmountMinor: $applied ? $receipt->net_amount_minor : 0,
                    appliedAt: $applied ? $receipt->settled_at?->format(DATE_ATOM) : null,
                    provisional: $applied
                        && data_get($receipt->metadata, 'provisional_recognition') === true,
                    occurredAt: $receipt->providerFundingObservation
                        ?->occurredAtInstant()
                        ?->format(DATE_ATOM),
                    providerSettledAt: $receipt->providerFundingObservation
                        ?->settledAtInstant()
                        ?->format(DATE_ATOM),
                    canApprove: $receipt->status->value === 'awaiting_approval',
                    approvalReference: $receipt->status->value === 'awaiting_approval'
                        ? $receipt->reference
                        : null,
                );
            })
            ->all();

        return new NetbankReusableFundingReceiptHistoryData(
            observations: $observations,
            lastCheckedAt: $address->last_checked_at?->format(DATE_ATOM),
        );
    }
}
