<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocation;
use LBHurtado\XChange\Contracts\Execution\StoredValueDestinationAuthorityContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Execution\StoredValueDestinationAuthorityData;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\StoredValueCollection;

final readonly class CollectionStoredValueDestinationAuthority implements StoredValueDestinationAuthorityContract
{
    public function __construct(
        private PartnerApiStoredValueDestinationAuthority $partner,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryPrincipalReferenceResolverContract $principals,
    ) {}

    public function isReady(): bool
    {
        return $this->partner->isReady();
    }

    public function authorize(ExecutionContextData $context, int $amountMinor): StoredValueDestinationAuthorityData
    {
        $reference = data_get($context->meta, 'collection_reference');

        if (! is_string($reference) || $reference === '') {
            return $this->partner->authorize($context, $amountMinor);
        }

        $collection = StoredValueCollection::query()
            ->with(['binding', 'collector'])
            ->where('reference', $reference)
            ->whereIn('status', ['verified', 'ready'])
            ->first();
        $collector = $collection?->collector;
        if (! $collection instanceof StoredValueCollection
            || ! $collector instanceof Model
            || $collection->binding?->voucher_id !== $context->voucher?->getKey()
            || $collection->amount_minor !== $amountMinor
            || ! in_array($collection->authorization_method, ['holder_otp', 'collector'], true)
            || ($collection->authorization_method === 'holder_otp'
                && ($collection->status !== 'verified' || $collection->verified_at === null))
            || ($collection->authorization_method === 'collector' && $collection->status !== 'ready')
            || $collection->expires_at->isPast()) {
            throw new StoredValueSpendRejectedException('The collection has no valid destination authority.');
        }

        $principal = $collector instanceof PartnerApiClient ? $collector->issuer : $collector;
        if (! $principal instanceof Model || ! $principal->exists) {
            throw new StoredValueSpendRejectedException('The collecting Account cannot be resolved.');
        }

        $allocation = TreasuryAllocation::query()
            ->with('reservePosition')
            ->where('allocation_reference', $collection->binding->allocation_reference)
            ->first();
        $reserve = $allocation?->reservePosition;
        if ($reserve === null || $collection->currency !== strtoupper((string) $reserve->currency)) {
            throw new StoredValueSpendRejectedException('The collecting Account has no compatible Treasury connection.');
        }

        $principalReference = $this->principals->resolve($principal);
        $portfolio = $this->portfolios->provision($principal, [(string) $reserve->connection_reference]);
        $positions = array_values(array_filter(
            $portfolio->positions,
            static fn ($position): bool => $position->purpose === TreasuryPositionPurpose::ClientFunds
                && $position->currency === $reserve->currency
                && $position->connectionReference === $reserve->connection_reference
                && $position->principalReference === $principalReference,
        ));
        if (count($positions) !== 1) {
            throw new StoredValueSpendRejectedException('The collecting Account has no unique Client Funds destination.');
        }

        return new StoredValueDestinationAuthorityData(
            counterpartyPositionReference: $positions[0]->positionReference,
            authorityReference: 'stored-value-collection:'.$collection->reference,
            principalReference: $principalReference,
        );
    }
}
