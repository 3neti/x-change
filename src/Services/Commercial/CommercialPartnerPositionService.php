<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDefinitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;

final readonly class CommercialPartnerPositionService
{
    public function __construct(
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private TreasuryPositionReadModelContract $positions,
        private TreasuryPositionProvisioningContract $provisioning,
    ) {}

    public function provision(
        Model $partner,
        TreasuryProviderConnectionData $connection,
    ): TreasuryPositionData {
        $principalReference = $this->principalReferences->resolve($partner);
        $existing = array_values(array_filter(
            $this->positions->forPrincipal($principalReference),
            static fn (TreasuryPositionData $position): bool => $position->status === 'active'
                && $position->provider === $connection->provider
                && $position->connectionReference === $connection->reference
                && $position->currency === $connection->currency
                && $position->purpose === TreasuryPositionPurpose::PartnerCommissionPayable,
        ));

        if (count($existing) > 1) {
            throw new CommercialSaleConflict(
                'Partner commission Treasury Position is ambiguous.',
            );
        }

        if ($existing !== []) {
            return $existing[0];
        }

        $scope = substr(hash('sha256', implode('|', [
            $principalReference,
            $connection->provider,
            $connection->reference,
            $connection->currency,
            TreasuryPositionPurpose::PartnerCommissionPayable->value,
        ])), 0, 40);
        $positionReference = 'position:partner-commission:'.$scope;

        return $this->provisioning->provision(
            $partner,
            new TreasuryPositionDefinitionData(
                positionReference: $positionReference,
                principalReference: $principalReference,
                mandateReference: 'mandate:partner-commission:'.$scope,
                settlementResourceReference: $connection->settlementResourceReference,
                settlementResourceType: $connection->settlementResourceType,
                provider: $connection->provider,
                connectionReference: $connection->reference,
                currency: $connection->currency,
                decimalPlaces: $connection->decimalPlaces,
                purpose: TreasuryPositionPurpose::PartnerCommissionPayable,
                custodyMode: $connection->custodyMode,
                legalProfile: $this->requiredConfig('legal_profile'),
                legalProfileVersion: $this->requiredConfig('legal_profile_version'),
                idempotencyKey: 'position-registration:'.$positionReference,
                reconciliationReference: 'reconciliation:'.$connection->provider.':'.$connection->reference,
                metadata: [
                    'provisioned_by' => 'x-change:commercial-partner-position',
                    'opening_balance_minor' => 0,
                ],
            ),
        );
    }

    private function requiredConfig(string $key): string
    {
        $value = trim((string) config('x-change.treasury.'.$key));

        if ($value === '') {
            throw new CommercialSaleConflict(
                "Commercial partner Treasury configuration [{$key}] is required.",
            );
        }

        return $value;
    }
}
