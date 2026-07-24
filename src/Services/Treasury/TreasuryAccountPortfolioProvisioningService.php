<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionDefinitionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Treasury\TreasuryAccountPortfolioData;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Exceptions\TreasuryPreflightFailed;

final readonly class TreasuryAccountPortfolioProvisioningService implements TreasuryAccountPortfolioProvisioningContract
{
    public function __construct(
        private TreasuryPreflightService $preflight,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private TreasuryPositionProvisioningContract $positions,
    ) {}

    public function provision(
        Model $accountOwner,
        array $connectionReferences = [],
    ): TreasuryAccountPortfolioData {
        $preflight = $this->preflight->run($connectionReferences);

        if (! $preflight->passes()) {
            throw new TreasuryPreflightFailed(
                'Required Treasury provider connections did not pass Account portfolio preflight.',
            );
        }

        $principalReference = $this->principalReferences->resolve($accountOwner);
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

            $scope = implode('|', [
                $principalReference,
                $connection->provider,
                $connection->reference,
                $connection->currency,
                TreasuryPositionPurpose::ClientFunds->value,
            ]);
            $scopeReference = substr(hash('sha256', $scope), 0, 40);
            $positionReference = "position:client:{$scopeReference}";

            $provisioned[] = $this->positions->provision(
                $accountOwner,
                new TreasuryPositionDefinitionData(
                    positionReference: $positionReference,
                    principalReference: $principalReference,
                    mandateReference: "mandate:client-funds:{$scopeReference}",
                    settlementResourceReference: $connection->settlementResourceReference,
                    settlementResourceType: $connection->settlementResourceType,
                    provider: $connection->provider,
                    connectionReference: $connection->reference,
                    currency: $connection->currency,
                    decimalPlaces: $connection->decimalPlaces,
                    purpose: TreasuryPositionPurpose::ClientFunds,
                    custodyMode: $connection->custodyMode,
                    legalProfile: $legalProfile,
                    legalProfileVersion: $legalProfileVersion,
                    idempotencyKey: "position-registration:{$positionReference}",
                    reconciliationReference: "reconciliation:{$connection->provider}:{$connection->reference}",
                    metadata: [
                        'provisioned_by' => 'x-change:onboarding:account-portfolio',
                        'opening_balance_minor' => 0,
                    ],
                ),
            );
        }

        return new TreasuryAccountPortfolioData(
            principalReference: $principalReference,
            positions: $provisioned,
            skippedConnections: $skipped,
        );
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
