<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Treasury;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use Throwable;

final class PreflightTreasuryCommand extends Command
{
    protected $signature = 'x-change:treasury:preflight
        {--connection=* : Limit the check to explicit connection references}
        {--json : Emit a machine-readable result}';

    protected $description = 'Check explicitly configured Treasury provider connections without moving money';

    public function handle(TreasuryPreflightService $preflight): int
    {
        try {
            $result = $preflight->run(array_values((array) $this->option('connection')));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $rows = array_map(
            static fn ($connection): array => [
                $connection->connection->reference,
                $connection->connection->provider,
                $connection->connection->mode->value,
                $connection->ready ? 'ready' : 'unavailable',
                implode(', ', $connection->issues),
            ],
            $result->connections,
        );

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'ready' => $result->passes(),
                'connections' => array_map(
                    static fn ($connection): array => [
                        'reference' => $connection->connection->reference,
                        'provider' => $connection->connection->provider,
                        'mode' => $connection->connection->mode->value,
                        'ready' => $connection->ready,
                        'issues' => $connection->issues,
                    ],
                    $result->connections,
                ),
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Connection', 'Provider', 'Mode', 'Status', 'Issues'],
                $rows,
            );
        }

        return $result->passes() ? self::SUCCESS : self::FAILURE;
    }
}
