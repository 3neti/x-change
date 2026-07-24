<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Contracts\TreasuryVocabularyReadModelContract;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use Throwable;

final readonly class FundingTreasuryPortfolioReadModel
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private TreasuryInventoryPositionReadModelContract $inventories,
        private TreasuryPositionReadModelContract $positions,
        private BuildBalanceOverview $balances,
        private TreasuryVocabularyReadModelContract $vocabulary,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forOperator(Authenticatable $operator): array
    {
        if (! $operator instanceof Model) {
            return $this->unavailable();
        }

        $principalPositions = $this->positions->forPrincipal(
            $this->principalReferences->resolve($operator),
        );
        $connections = array_map(
            fn (TreasuryProviderConnectionData $connection): array => $this->connection(
                $operator,
                $connection,
                $principalPositions,
            ),
            $this->connections->all(),
        );
        $active = array_values(array_filter(
            $connections,
            static fn (array $connection): bool => $connection['mode'] !== 'disabled',
        ));
        $currency = $this->portfolioCurrency($connections);
        $clientFundsMinor = $this->sum($active, 'client_funds_minor');
        $reserveMinor = $this->sum($active, 'pay_code_reserve_minor');
        $accountPositionMinor = $this->sum($active, 'account_position_minor');
        $providerInventoryMinor = $this->sumNullable(
            $active,
            'provider_inventory_minor',
        );
        $issuanceCapacityMinor = $this->sumNullable(
            $active,
            'issuance_capacity_minor',
            requireEveryValue: true,
        );

        return [
            'schema' => 'x-change.cockpit.funding-treasury-portfolio.v1',
            'status' => $active === [] ? 'not_configured' : 'available',
            'read_only' => true,
            'provider_calls' => false,
            'currency' => $currency,
            'vocabulary' => $this->vocabulary->terms([
                'internal_balance',
                'issuance_capacity',
                'provider_liquidity',
                'account_funding',
            ]),
            'totals' => [
                'client_funds_minor' => $clientFundsMinor,
                'client_funds' => $this->money($clientFundsMinor, $currency),
                'pay_code_reserve_minor' => $reserveMinor,
                'pay_code_reserve' => $this->money($reserveMinor, $currency),
                'account_position_minor' => $accountPositionMinor,
                'account_position' => $this->money($accountPositionMinor, $currency),
                'provider_inventory_minor' => $providerInventoryMinor,
                'provider_inventory' => $this->nullableMoney(
                    $providerInventoryMinor,
                    $currency,
                ),
                'issuance_capacity_minor' => $issuanceCapacityMinor,
                'issuance_capacity' => $this->nullableMoney(
                    $issuanceCapacityMinor,
                    $currency,
                ),
            ],
            'connections' => $connections,
            'accounting_boundary' => [
                'provider_outflow' => 'provider_principal_only',
                'sender_system_charge' => 'deferred_accounting_wave',
                'provider_liquidity_source' => 'cached_projection_only',
            ],
            'redactions' => [
                'principal_references_exposed' => false,
                'connection_references_exposed' => false,
                'inventory_references_exposed' => false,
                'settlement_resource_references_exposed' => false,
                'provider_transaction_ids_exposed' => false,
                'operation_references_exposed' => false,
                'raw_evidence_exposed' => false,
            ],
        ];
    }

    /**
     * @param  list<TreasuryPositionData>  $principalPositions
     * @return array<string, mixed>
     */
    private function connection(
        Model $operator,
        TreasuryProviderConnectionData $connection,
        array $principalPositions,
    ): array {
        $accountPositions = array_values(array_filter(
            $principalPositions,
            static fn (TreasuryPositionData $position): bool => $position->status === 'active'
                && $position->provider === $connection->provider
                && $position->connectionReference === $connection->reference
                && $position->currency === $connection->currency,
        ));
        $clientFundsMinor = $this->positionBalance(
            $accountPositions,
            TreasuryPositionPurpose::ClientFunds,
        );
        $reserveMinor = $this->positionBalance(
            $accountPositions,
            TreasuryPositionPurpose::PayCodeReserve,
        );
        $accountPositionMinor = $clientFundsMinor + $reserveMinor;
        $inventory = $this->inventories->find($connection->inventoryReference);
        $allPositionBalanceMinor = array_sum(array_map(
            static fn (TreasuryPositionData $position): int => $position->balanceMinor,
            array_values(array_filter(
                $this->positions->forConnection(
                    $connection->provider,
                    $connection->reference,
                    $connection->currency,
                ),
                static fn (TreasuryPositionData $position): bool => $position->status === 'active',
            )),
        ));
        $inventoryMatchesPositions = $inventory === null
            ? null
            : $inventory->balanceMinor === $allPositionBalanceMinor;
        $controlStatus = match ($inventoryMatchesPositions) {
            true => 'reconciled',
            false => 'review_required',
            null => 'not_registered',
        };
        $liquidity = $connection->isActive()
            ? $this->liquidity($operator, $connection)
            : $this->disabledLiquidity();
        $issuanceCapacityMinor = $this->issuanceCapacity(
            $connection,
            $accountPositionMinor,
            $reserveMinor,
            $liquidity,
        );
        $status = match (true) {
            ! $connection->isActive() => 'disabled',
            $accountPositions === [] => 'not_provisioned',
            $inventoryMatchesPositions !== true => 'review_required',
            default => 'active',
        };

        return [
            'provider' => $connection->provider,
            'provider_label' => $this->providerLabel($connection->provider),
            'mode' => $connection->mode->value,
            'currency' => $connection->currency,
            'status' => $status,
            'client_funds_minor' => $clientFundsMinor,
            'client_funds' => $this->money(
                $clientFundsMinor,
                $connection->currency,
            ),
            'pay_code_reserve_minor' => $reserveMinor,
            'pay_code_reserve' => $this->money(
                $reserveMinor,
                $connection->currency,
            ),
            'account_position_minor' => $accountPositionMinor,
            'account_position' => $this->money(
                $accountPositionMinor,
                $connection->currency,
            ),
            'provider_inventory_minor' => $inventory?->balanceMinor,
            'provider_inventory' => $this->nullableMoney(
                $inventory?->balanceMinor,
                $connection->currency,
            ),
            'provider_liquidity_minor' => $liquidity['amount_minor'],
            'provider_liquidity' => $this->nullableMoney(
                $liquidity['amount_minor'],
                $connection->currency,
            ),
            'provider_liquidity_status' => $liquidity['status'],
            'provider_liquidity_is_stale' => $liquidity['is_stale'],
            'provider_liquidity_checked_at' => $liquidity['checked_at'],
            'issuance_capacity_minor' => $issuanceCapacityMinor,
            'issuance_capacity' => $this->nullableMoney(
                $issuanceCapacityMinor,
                $connection->currency,
            ),
            'inventory_matches_positions' => $inventoryMatchesPositions,
            'control_status' => $controlStatus,
            'provider_calls' => false,
        ];
    }

    /**
     * @return array{
     *     amount_minor: ?int,
     *     status: string,
     *     is_stale: bool,
     *     checked_at: ?string
     * }
     */
    private function liquidity(
        Model $operator,
        TreasuryProviderConnectionData $connection,
    ): array {
        try {
            $overview = $this->balances->handle(
                $operator,
                $this->runtimeProvider($connection->provider),
                false,
            );
            $balance = collect((array) ($overview['balances'] ?? []))
                ->first(
                    static fn (mixed $candidate): bool => is_array($candidate)
                        && (
                            ($candidate['is_liquidity_guard'] ?? false) === true
                            || (
                                ($candidate['is_authoritative'] ?? false) === true
                                && ($candidate['key'] ?? null) !== 'local_ledger'
                            )
                        ),
                );

            if (! is_array($balance)) {
                return $this->unavailableLiquidity();
            }

            $amountMinor = $balance['available_balance_minor']
                ?? $balance['balance_minor']
                ?? null;
            $isStale = (bool) ($balance['is_stale'] ?? true);

            return [
                'amount_minor' => is_numeric($amountMinor)
                    ? (int) $amountMinor
                    : null,
                'status' => $isStale
                    ? 'stale'
                    : trim((string) ($balance['sync_status'] ?? 'cached')),
                'is_stale' => $isStale,
                'checked_at' => is_string($balance['checked_at'] ?? null)
                    ? $balance['checked_at']
                    : null,
            ];
        } catch (Throwable) {
            return $this->unavailableLiquidity();
        }
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     */
    private function positionBalance(
        array $positions,
        TreasuryPositionPurpose $purpose,
    ): int {
        return array_sum(array_map(
            static fn (TreasuryPositionData $position): int => $position->balanceMinor,
            array_values(array_filter(
                $positions,
                static fn (TreasuryPositionData $position): bool => $position->purpose
                    === $purpose,
            )),
        ));
    }

    /**
     * @param  array<string, mixed>  $liquidity
     */
    private function issuanceCapacity(
        TreasuryProviderConnectionData $connection,
        int $accountPositionMinor,
        int $reserveMinor,
        array $liquidity,
    ): ?int {
        if (
            ! $connection->isActive()
            || $liquidity['amount_minor'] === null
            || $liquidity['is_stale'] === true
        ) {
            return null;
        }

        return max(
            0,
            min($accountPositionMinor, $liquidity['amount_minor'])
                - $reserveMinor,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $connections
     */
    private function portfolioCurrency(array $connections): string
    {
        $currencies = array_values(array_unique(array_map(
            static fn (array $connection): string => $connection['currency'],
            $connections,
        )));

        return count($currencies) === 1
            ? $currencies[0]
            : mb_strtoupper((string) config('x-change.pricing.currency', 'PHP'));
    }

    /**
     * @param  list<array<string, mixed>>  $connections
     */
    private function sum(array $connections, string $key): int
    {
        return array_sum(array_map(
            static fn (array $connection): int => (int) $connection[$key],
            $connections,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $connections
     */
    private function sumNullable(
        array $connections,
        string $key,
        bool $requireEveryValue = false,
    ): ?int {
        if ($connections === []) {
            return null;
        }

        $values = array_column($connections, $key);

        if (
            ($requireEveryValue && in_array(null, $values, true))
            || count(array_filter($values, is_numeric(...))) === 0
        ) {
            return null;
        }

        return array_sum(array_map(
            static fn (mixed $value): int => is_numeric($value)
                ? (int) $value
                : 0,
            $values,
        ));
    }

    private function runtimeProvider(string $provider): string
    {
        return match ($provider) {
            'paynamics_constellation' => 'paynamics',
            default => $provider,
        };
    }

    private function providerLabel(string $provider): string
    {
        return match ($provider) {
            'netbank' => 'NetBank',
            'paynamics', 'paynamics_constellation' => 'Paynamics',
            default => str($provider)->headline()->toString(),
        };
    }

    private function money(int $amountMinor, string $currency): string
    {
        return Number::currency(
            $amountMinor / 100,
            in: mb_strtoupper($currency),
        );
    }

    private function nullableMoney(
        ?int $amountMinor,
        string $currency,
    ): ?string {
        return $amountMinor === null
            ? null
            : $this->money($amountMinor, $currency);
    }

    /**
     * @return array{
     *     amount_minor: null,
     *     status: string,
     *     is_stale: bool,
     *     checked_at: null
     * }
     */
    private function disabledLiquidity(): array
    {
        return [
            'amount_minor' => null,
            'status' => 'disabled',
            'is_stale' => true,
            'checked_at' => null,
        ];
    }

    /**
     * @return array{
     *     amount_minor: null,
     *     status: string,
     *     is_stale: bool,
     *     checked_at: null
     * }
     */
    private function unavailableLiquidity(): array
    {
        return [
            'amount_minor' => null,
            'status' => 'unavailable',
            'is_stale' => true,
            'checked_at' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(): array
    {
        return [
            'schema' => 'x-change.cockpit.funding-treasury-portfolio.v1',
            'status' => 'unavailable',
            'read_only' => true,
            'provider_calls' => false,
            'currency' => mb_strtoupper((string) config(
                'x-change.pricing.currency',
                'PHP',
            )),
            'vocabulary' => [],
            'totals' => [],
            'connections' => [],
            'accounting_boundary' => [
                'provider_outflow' => 'provider_principal_only',
                'sender_system_charge' => 'deferred_accounting_wave',
                'provider_liquidity_source' => 'cached_projection_only',
            ],
            'redactions' => [
                'raw_evidence_exposed' => false,
            ],
        ];
    }
}
