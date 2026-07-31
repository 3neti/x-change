<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Data\Funding\FundingLiquidityRefreshData;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use LBHurtado\XChange\Services\ProviderBalanceSnapshotStore;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use Throwable;

final readonly class RefreshFundingLiquidity
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private BuildBalanceOverview $balances,
        private AuditLoggerContract $audit,
        private TreasuryInventoryPositionReadModelContract $inventories,
        private ProviderBalanceSnapshotStore $snapshots,
    ) {}

    public function handle(Authenticatable $operator): FundingLiquidityRefreshData
    {
        $outcomes = [];

        foreach ($this->connections->active() as $connection) {
            $outcomes[] = [
                'provider' => $connection->provider,
                'status' => $this->refreshConnection($operator, $connection),
            ];
        }

        return new FundingLiquidityRefreshData(
            refreshed: $this->count($outcomes, 'refreshed'),
            failed: $this->count($outcomes, 'failed'),
            busy: $this->count($outcomes, 'busy'),
            unavailable: $this->count($outcomes, 'unavailable'),
            reviewRequired: $this->count($outcomes, 'review_required'),
            connections: $outcomes,
        );
    }

    private function refreshConnection(
        Authenticatable $operator,
        TreasuryProviderConnectionData $connection,
    ): string {
        $lock = Cache::lock(
            'x-change:funding-liquidity-refresh:'.hash('sha256', $connection->reference),
            max(1, (int) config('x-change.funding.liquidity_refresh.lock_seconds', 30)),
        );

        if (! $lock->get()) {
            $this->recordOutcome($operator, $connection, 'busy');

            return 'busy';
        }

        try {
            $overview = $this->balances->handle(
                $operator,
                $this->runtimeProvider($connection->provider),
                true,
                true,
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
            $status = $this->outcome($balance);

            if ($status === 'refreshed' && $this->providerLiquidityIsBelowInventory($connection, $balance)) {
                $this->snapshots->recordReviewRequired(
                    $this->runtimeProvider($connection->provider),
                    (string) ($balance['key'] ?? ''),
                    'Provider liquidity is below recognized Treasury Inventory.',
                );
                $status = 'review_required';
            }

            $this->recordOutcome($operator, $connection, $status);

            return $status;
        } catch (Throwable) {
            $this->recordOutcome($operator, $connection, 'failed');

            return 'failed';
        } finally {
            $lock->release();
        }
    }

    private function outcome(mixed $balance): string
    {
        if (! is_array($balance)) {
            return 'unavailable';
        }

        if (($balance['is_stale'] ?? true) === false) {
            return 'refreshed';
        }

        return in_array(
            $balance['sync_status'] ?? null,
            ['disabled', 'not_checked', 'unavailable'],
            true,
        ) ? 'unavailable' : 'failed';
    }

    /**
     * @param  array<string, mixed>|null  $balance
     */
    private function providerLiquidityIsBelowInventory(
        TreasuryProviderConnectionData $connection,
        ?array $balance,
    ): bool {
        if ($balance === null) {
            return false;
        }

        $providerLiquidityMinor = $balance['available_balance_minor']
            ?? $balance['balance_minor']
            ?? null;
        $inventory = $this->inventories->find($connection->inventoryReference);

        return is_numeric($providerLiquidityMinor)
            && $inventory !== null
            && (int) $providerLiquidityMinor < $inventory->balanceMinor;
    }

    private function runtimeProvider(string $provider): string
    {
        return match ($provider) {
            'paynamics_constellation' => 'paynamics',
            default => $provider,
        };
    }

    /**
     * @param  list<array{provider: string, status: string}>  $outcomes
     */
    private function count(array $outcomes, string $status): int
    {
        return count(array_filter(
            $outcomes,
            static fn (array $outcome): bool => $outcome['status'] === $status,
        ));
    }

    private function recordOutcome(
        Authenticatable $operator,
        TreasuryProviderConnectionData $connection,
        string $outcome,
    ): void {
        $this->audit->log('funding.liquidity.refresh_completed', [
            'operator_type' => $operator::class,
            'operator_id' => (string) $operator->getAuthIdentifier(),
            'provider' => $connection->provider,
            'connection_reference' => $connection->reference,
            'outcome' => $outcome,
            'financial_posting' => false,
        ]);
    }
}
