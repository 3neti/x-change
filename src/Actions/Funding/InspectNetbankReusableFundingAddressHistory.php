<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Data\Funding\NetbankReusableFundingHistoryData;
use LBHurtado\XChange\Data\Funding\NetbankReusableFundingObservationData;
use LBHurtado\XChange\Models\StandingFundingAddress;

final class InspectNetbankReusableFundingAddressHistory
{
    public function __construct(
        private readonly SyncStandingFundingAddress $sync,
    ) {}

    public function handle(Model $owner): NetbankReusableFundingHistoryData
    {
        $this->assertEnabled();
        $address = StandingFundingAddress::query()
            ->where('owner_type', $owner::class)
            ->where('owner_id', $owner->getKey())
            ->where('provider_code', 'netbank')
            ->where('purpose', FundingAddressPurpose::AccountFunding)
            ->first();

        if (! $address instanceof StandingFundingAddress) {
            throw ValidationException::withMessages([
                'standing_funding_address' => 'Create the Account Funding Address before checking NetBank.',
            ]);
        }

        $sync = $this->sync->handle($address, 'operator');

        $observations = $address->receipts()
            ->latest('observed_at')
            ->limit(50)
            ->get()
            ->map(static fn ($receipt): NetbankReusableFundingObservationData => new NetbankReusableFundingObservationData(
                reference: 'AF-'.strtoupper(substr($receipt->reference, -12)),
                grossAmountMinor: $receipt->gross_amount_minor,
                feeAmountMinor: $receipt->fee_amount_minor,
                netAmountMinor: $receipt->net_amount_minor,
                currency: $receipt->currency,
                providerStatus: $receipt->status->value,
                occurredAt: $receipt->observed_at?->format(DATE_ATOM),
                settledAt: $receipt->settled_at?->format(DATE_ATOM),
                canApprove: $receipt->status->value === 'awaiting_approval',
            ))
            ->all();

        return new NetbankReusableFundingHistoryData($observations, $sync);
    }

    private function assertEnabled(): void
    {
        if (! (bool) config('x-change.funding.standing_addresses.enabled', false)) {
            throw ValidationException::withMessages([
                'standing_funding_address' => 'Standing Funding Addresses are disabled.',
            ]);
        }
    }
}
