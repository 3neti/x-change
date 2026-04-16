<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Revenue;

use Illuminate\Console\Command;
use Illuminate\Support\Number;
use LBHurtado\XChange\Services\InstructionRevenueSnapshotService;

class ShowPendingRevenueCommand extends Command
{
    protected $signature = 'xchange:revenue:pending
        {--min= : Minimum balance in PHP}
        {--json : Output JSON}';

    protected $description = 'Show pending collectible revenue by instruction item.';

    public function handle(InstructionRevenueSnapshotService $snapshot): int
    {
        $min = $this->option('min');
        $minAmount = $min !== null && $min !== '' ? (float) $min : null;

        $payload = $snapshot->getPendingRevenue($minAmount, 'PHP');

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $pending = $payload['pending'];
        $items = $payload['items'];

        $this->info('Pending Revenue');
        $this->line('Collectible Items: '.$pending['count']);
        $this->line('Total Pending: '.Number::currency((float) $pending['total'], in: 'PHP'));
        $this->newLine();

        $this->table(
            ['Index', 'Name', 'Type', 'Balance', 'Destination', 'Tx Count'],
            array_map(
                fn (array $item) => [
                    $item['index'],
                    $item['name'],
                    $item['type'],
                    Number::currency((float) $item['balance'], in: 'PHP'),
                    $item['destination']['name'] ?? 'Unresolved',
                    $item['transaction_count'],
                ],
                $items
            )
        );

        return self::SUCCESS;
    }
}
