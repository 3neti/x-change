<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Treasury\TreasuryOpeningBalanceReconciliationService;
use Throwable;

final class ReconcileOpeningTreasuryBalanceCommand extends Command
{
    protected $signature = 'x-change:treasury:reconcile-opening
        {--connection=* : Limit reconciliation to explicit connection references}
        {--json : Emit a machine-readable result}';

    protected $description = 'Reconcile authoritative provider balances into opening Treasury inventory';

    public function handle(
        TreasuryOpeningBalanceReconciliationService $reconciliation,
    ): int {
        try {
            $result = $reconciliation->reconcile(
                array_values((array) $this->option('connection')),
            );
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $rows = array_map(
            static fn ($connection): array => [
                'connection' => $connection->connectionReference,
                'provider' => $connection->provider,
                'currency' => $connection->currency,
                'provider_balance_minor' => $connection->providerBalanceMinor,
                'inventory_balance_minor' => $connection->inventoryBalanceMinor,
                'position_balance_minor' => $connection->positionBalanceMinor,
                'difference_minor' => $connection->differenceMinor,
                'status' => $connection->status->value,
                'reason' => $connection->reason,
                'observed_at' => $connection->observedAt,
            ],
            $result->connections,
        );

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'ready' => $result->passes(),
                'connections' => $rows,
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                [
                    'Connection',
                    'Provider',
                    'Currency',
                    'Provider',
                    'Inventory',
                    'Positions',
                    'Difference',
                    'Status',
                    'Reason',
                ],
                array_map(
                    static fn (array $row): array => [
                        $row['connection'],
                        $row['provider'],
                        $row['currency'],
                        $row['provider_balance_minor'],
                        $row['inventory_balance_minor'],
                        $row['position_balance_minor'],
                        $row['difference_minor'],
                        $row['status'],
                        $row['reason'],
                    ],
                    $rows,
                ),
            );
        }

        return $result->passes() ? self::SUCCESS : self::FAILURE;
    }
}
