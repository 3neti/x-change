<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDefinitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Data\Treasury\TreasuryProvisioningData;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Exceptions\TreasuryPreflightFailed;

final readonly class TreasuryProvisioningService
{
    public function __construct(
        private TreasuryPreflightService $preflight,
        private TreasuryConfigurationValidator $configuration,
        private SystemUserResolverContract $systemPrincipal,
        private TreasuryPositionProvisioningContract $positions,
    ) {}

    /**
     * @param  list<string>  $connectionReferences
     */
    public function provision(array $connectionReferences = []): TreasuryProvisioningData
    {
        $this->configuration->assertConfigured($connectionReferences);

        $preflight = $this->preflight->run($connectionReferences);

        if (! $preflight->passes()) {
            throw new TreasuryPreflightFailed(
                'Required Treasury provider connections did not pass preflight.',
            );
        }

        if ($preflight->connections === []) {
            return new TreasuryProvisioningData($preflight, [], []);
        }

        $principal = $this->systemPrincipal->resolve();

        if (! $principal instanceof Model) {
            throw new TreasuryConfigurationException(
                'The resolved system principal must be a persisted Eloquent model.',
            );
        }

        $principalReference = $this->requiredConfig('principal_reference');
        $legalEntityReference = $this->requiredConfig('legal_entity_reference');
        $mandateReference = $this->requiredConfig('system_mandate_reference');
        $legalProfile = $this->requiredConfig('legal_profile');
        $legalProfileVersion = $this->requiredConfig('legal_profile_version');
        $provisioned = [];
        $skipped = [];

        foreach ($preflight->connections as $result) {
            $connection = $result->connection;

            if (! $result->ready) {
                $skipped[] = $connection->reference;

                continue;
            }

            foreach ([
                [TreasuryPositionPurpose::TreasuryClearing, 'clearing'],
                [TreasuryPositionPurpose::LegacyUnattributed, 'unattributed'],
                [TreasuryPositionPurpose::CommercialClearing, 'commercial-clearing'],
                [TreasuryPositionPurpose::ProviderCostPayable, 'provider-cost-payable'],
                [TreasuryPositionPurpose::ProductRevenue, 'product-revenue'],
                [TreasuryPositionPurpose::PartnerCommissionPayable, 'partner-commission-payable'],
                [TreasuryPositionPurpose::RoyaltyPayable, 'royalty-payable'],
                [TreasuryPositionPurpose::TaxPayable, 'tax-payable'],
                [TreasuryPositionPurpose::CommercialRevenue, 'commercial-revenue'],
            ] as [$purpose, $suffix]) {
                $positionReference = implode(':', [
                    'position',
                    'system',
                    $connection->provider,
                    $connection->reference,
                    mb_strtolower($connection->currency),
                    $suffix,
                ]);

                $provisioned[] = $this->positions->provision(
                    $principal,
                    new TreasuryPositionDefinitionData(
                        positionReference: $positionReference,
                        principalReference: $principalReference,
                        mandateReference: $mandateReference,
                        settlementResourceReference: $connection->settlementResourceReference,
                        settlementResourceType: $connection->settlementResourceType,
                        provider: $connection->provider,
                        connectionReference: $connection->reference,
                        currency: $connection->currency,
                        decimalPlaces: $connection->decimalPlaces,
                        purpose: $purpose,
                        custodyMode: $connection->custodyMode,
                        legalProfile: $legalProfile,
                        legalProfileVersion: $legalProfileVersion,
                        idempotencyKey: "position-registration:{$positionReference}",
                        reconciliationReference: "reconciliation:{$connection->provider}:{$connection->reference}",
                        metadata: [
                            'provisioned_by' => 'x-change:treasury:provision',
                            'legal_entity_reference' => $legalEntityReference,
                            'opening_balance_minor' => 0,
                        ],
                    ),
                );
            }
        }

        return new TreasuryProvisioningData($preflight, $provisioned, $skipped);
    }

    private function requiredConfig(string $key): string
    {
        $value = trim((string) config("x-change.treasury.{$key}"));

        if ($value === '') {
            throw new TreasuryConfigurationException(
                "Treasury configuration [{$key}] is required.",
            );
        }

        return $value;
    }
}
