<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LBHurtado\XChange\Services\Treasury\TreasuryOpeningBalanceReconciliationService;
use Throwable;

final class SimulateTreasuryProviderDepositCommand extends Command
{
    protected $signature = 'x-change:treasury:simulate-deposit
        {connection : Explicit Treasury provider connection}
        {amount : Positive deposit amount in minor units}
        {--reference= : Stable simulation idempotency reference}
        {--commit : Execute the local simulation}
        {--json : Emit a machine-readable result}';

    protected $description = 'Simulate an authoritative provider deposit in local or testing environments';

    public function handle(
        TreasuryOpeningBalanceReconciliationService $reconciliation,
    ): int {
        if (! (bool) $this->option('commit')) {
            $this->components->error(
                'Treasury deposit simulation is guarded. Re-run with --commit in an allowed environment.',
            );

            return self::FAILURE;
        }

        $amount = filter_var($this->argument('amount'), FILTER_VALIDATE_INT);

        if ($amount === false || $amount <= 0) {
            $this->components->error('The simulation amount must be positive minor units.');

            return self::FAILURE;
        }

        $reference = trim((string) $this->option('reference'));
        $reference = $reference !== ''
            ? $reference
            : 'simulated-deposit:'.Str::uuid();

        try {
            $result = $reconciliation->simulateDeposit(
                (string) $this->argument('connection'),
                $amount,
                $reference,
            );
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $payload = [
            'connection' => $result->connectionReference,
            'provider' => $result->provider,
            'currency' => $result->currency,
            'amount_minor' => $amount,
            'status' => $result->status->value,
            'inventory_balance_minor' => $result->inventoryBalanceMinor,
            'position_balance_minor' => $result->positionBalanceMinor,
            'simulation_reference' => $reference,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Connection', 'Provider', 'Currency', 'Deposit', 'Inventory', 'Positions', 'Status'],
                [[
                    $payload['connection'],
                    $payload['provider'],
                    $payload['currency'],
                    $payload['amount_minor'],
                    $payload['inventory_balance_minor'],
                    $payload['position_balance_minor'],
                    $payload['status'],
                ]],
            );
        }

        return self::SUCCESS;
    }
}
