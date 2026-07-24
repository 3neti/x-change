<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Contracts\VoucherLiabilitySummaryContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Treasury\TreasuryOpeningBalanceConnectionData;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;

final readonly class TreasuryLifecycleAccountingSnapshot
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryInventoryPositionReadModelContract $inventories,
        private TreasuryPositionReadModelContract $positions,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private AccountBalanceReadModelContract $accountBalances,
        private WalletAccessContract $wallets,
        private VoucherLiabilitySummaryContract $liabilities,
    ) {}

    /**
     * @param  list<TreasuryOpeningBalanceConnectionData>  $observations
     * @return array<string, mixed>
     */
    public function capture(Model $accountOwner, array $observations = []): array
    {
        $principalReference = $this->principalReferences->resolve($accountOwner);
        $systemPrincipalReference = trim((string) config(
            'x-change.treasury.principal_reference',
            'principal:system',
        ));
        $legacyAccount = $this->wallets->resolveForUser($accountOwner);
        $observationsByConnection = collect($observations)
            ->keyBy('connectionReference');

        return [
            'schema' => 'x-change.lifecycle.treasury-accounting-snapshot.v1',
            'captured_at' => now()->toIso8601String(),
            'account' => [
                'principal_reference' => $principalReference,
                'legacy_compatibility_balance_minor' => (int) $this->wallets
                    ->getBalance($legacyAccount),
                'internal_balance_minor' => $this->accountBalances
                    ->balanceMinor($accountOwner, 'PHP'),
                'liability' => $this->liabilities
                    ->forIssuer($accountOwner)
                    ->toArray(),
            ],
            'connections' => array_map(
                fn (TreasuryProviderConnectionData $connection): array => $this->connection(
                    $connection,
                    $principalReference,
                    $systemPrincipalReference,
                    $observationsByConnection->get($connection->reference),
                ),
                $this->connections->all(),
            ),
            'accounting_boundary' => [
                'provider_observation' => 'authoritative_external_fact_when_present',
                'inventory' => 'provider_resource_control_total',
                'system_positions' => 'treasury_clearing_and_unattributed_attribution',
                'account_positions' => 'provider-specific_client_funds',
                'legacy_compatibility_balance' => 'pay_code_escrow_and_fee_ledger',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function connection(
        TreasuryProviderConnectionData $connection,
        string $accountPrincipalReference,
        string $systemPrincipalReference,
        mixed $observation,
    ): array {
        $positions = array_values(array_filter(
            $this->positions->forConnection(
                $connection->provider,
                $connection->reference,
                $connection->currency,
            ),
            static fn (TreasuryPositionData $position): bool => $position->status === 'active',
        ));
        $accountPositions = array_values(array_filter(
            $positions,
            static fn (TreasuryPositionData $position): bool => $position->principalReference
                === $accountPrincipalReference,
        ));
        $systemPositions = array_values(array_filter(
            $positions,
            static fn (TreasuryPositionData $position): bool => $position->principalReference
                === $systemPrincipalReference,
        ));
        $inventory = $this->inventories->find($connection->inventoryReference);
        $positionTotalMinor = array_sum(array_map(
            static fn (TreasuryPositionData $position): int => $position->balanceMinor,
            $positions,
        ));

        return [
            'reference' => $connection->reference,
            'provider' => $connection->provider,
            'mode' => $connection->mode->value,
            'active' => $connection->isActive(),
            'currency' => $connection->currency,
            'provider_observation' => $observation instanceof TreasuryOpeningBalanceConnectionData
                ? [
                    'status' => $observation->status->value,
                    'balance_minor' => $observation->providerBalanceMinor,
                    'observed_at' => $observation->observedAt,
                    'evidence_reference' => $observation->evidenceReference,
                    'difference_minor' => $observation->differenceMinor,
                    'reason' => $observation->reason,
                ]
                : null,
            'inventory' => $inventory === null
                ? [
                    'status' => 'not_registered',
                    'balance_minor' => null,
                ]
                : [
                    'status' => $inventory->status,
                    'balance_minor' => $inventory->balanceMinor,
                    'has_treasury_facts' => $inventory->hasTreasuryFacts,
                ],
            'system_positions' => [
                'balance_minor' => $this->sum($systemPositions),
                'by_purpose' => $this->byPurpose($systemPositions),
            ],
            'account_positions' => [
                'status' => $accountPositions === [] ? 'not_provisioned' : 'provisioned',
                'balance_minor' => $accountPositions === []
                    ? null
                    : $this->sum($accountPositions),
                'by_purpose' => $this->byPurpose($accountPositions),
            ],
            'all_positions' => [
                'balance_minor' => $positionTotalMinor,
                'count' => count($positions),
            ],
            'control' => [
                'inventory_equals_positions' => $inventory === null
                    ? null
                    : $inventory->balanceMinor === $positionTotalMinor,
            ],
        ];
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     */
    private function sum(array $positions): int
    {
        return array_sum(array_map(
            static fn (TreasuryPositionData $position): int => $position->balanceMinor,
            $positions,
        ));
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     * @return array<string, int>
     */
    private function byPurpose(array $positions): array
    {
        return collect($positions)
            ->groupBy(
                static fn (TreasuryPositionData $position): string => $position->purpose->value,
            )
            ->map(
                static fn ($positions): int => $positions->sum(
                    static fn (TreasuryPositionData $position): int => $position->balanceMinor,
                ),
            )
            ->sortKeys()
            ->all();
    }
}
