<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use Throwable;

final class ProvisionTreasuryCommand extends Command
{
    protected $signature = 'x-change:treasury:provision
        {--connection=* : Limit provisioning to explicit connection references}
        {--json : Emit a machine-readable result}';

    protected $description = 'Provision zero-balance system Treasury Positions after provider preflight';

    public function handle(TreasuryProvisioningService $provisioning): int
    {
        try {
            $result = $provisioning->provision(
                array_values((array) $this->option('connection')),
            );
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $positions = array_map(
            static fn ($position): array => [
                'reference' => $position->positionReference,
                'provider' => $position->provider,
                'currency' => $position->currency,
                'balance_minor' => $position->balanceMinor,
                'status' => $position->status,
            ],
            $result->positions,
        );

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'provisioned' => $positions,
                'skipped_connections' => $result->skippedConnections,
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Position', 'Provider', 'Currency', 'Opening balance', 'Status'],
                array_map(
                    static fn (array $position): array => [
                        $position['reference'],
                        $position['provider'],
                        $position['currency'],
                        $position['balance_minor'],
                        $position['status'],
                    ],
                    $positions,
                ),
            );

            foreach ($result->skippedConnections as $connection) {
                $this->components->warn("Skipped optional unavailable connection [{$connection}].");
            }
        }

        return self::SUCCESS;
    }
}
