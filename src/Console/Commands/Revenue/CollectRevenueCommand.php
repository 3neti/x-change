<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Revenue;

use Illuminate\Console\Command;
use Illuminate\Support\Number;
use LBHurtado\XChange\Services\InstructionRevenueCollectionService;
use RuntimeException;

class CollectRevenueCommand extends Command
{
    protected $signature = 'xchange:revenue:collect
        {--index= : Collect revenue for a single instruction item index}
        {--all : Collect revenue for all pending instruction items}
        {--min= : Minimum balance in PHP when using --all}
        {--notes= : Optional notes}
        {--json : Output JSON}';

    protected $description = 'Collect revenue from instruction item wallets.';

    public function handle(InstructionRevenueCollectionService $collector): int
    {
        $index = $this->option('index');
        $all = (bool) $this->option('all');
        $notes = $this->option('notes');
        $min = $this->option('min');
        $minAmount = $min !== null && $min !== '' ? (float) $min : null;

        if (! $index && ! $all) {
            throw new RuntimeException('Provide either --index=... or --all.');
        }

        if ($index && $all) {
            throw new RuntimeException('Use either --index=... or --all, not both.');
        }

        if ($index) {
            $result = $collector->collectByIndex((string) $index, null, is_string($notes) ? $notes : null);

            if ($this->option('json')) {
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }

            if (($result['skipped'] ?? false) === true) {
                $this->warn($result['message'] ?? 'Nothing to collect.');

                return self::SUCCESS;
            }

            $this->info('Revenue collected successfully.');
            $this->line('Instruction Item: '.($result['index'] ?? 'n/a'));
            $this->line('Amount: '.Number::currency((float) ($result['amount'] ?? 0), in: 'PHP'));
            $this->line('Destination: '.data_get($result, 'destination.name', 'n/a'));
            $this->line('Transfer UUID: '.($result['transfer_uuid'] ?? 'n/a'));

            return self::SUCCESS;
        }

        $result = $collector->collectAll($minAmount, null);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Revenue collection run completed.');
        $this->line('Collected: '.count($result['collected'] ?? []));
        $this->line('Errors: '.count($result['errors'] ?? []));
        $this->newLine();

        if (! empty($result['collected'])) {
            $this->table(
                ['Index', 'Name', 'Amount', 'Destination', 'Transfer UUID'],
                array_map(
                    fn (array $row) => [
                        $row['index'] ?? 'n/a',
                        $row['name'] ?? 'n/a',
                        Number::currency((float) ($row['amount'] ?? 0), in: 'PHP'),
                        data_get($row, 'destination.name', 'n/a'),
                        $row['transfer_uuid'] ?? 'n/a',
                    ],
                    $result['collected']
                )
            );
        }

        if (! empty($result['errors'])) {
            $this->newLine();
            $this->warn('Errors:');
            $this->table(
                ['Index', 'Error'],
                array_map(
                    fn (array $row) => [
                        $row['index'] ?? 'n/a',
                        $row['error'] ?? 'Unknown error',
                    ],
                    $result['errors']
                )
            );
        }

        return self::SUCCESS;
    }
}
