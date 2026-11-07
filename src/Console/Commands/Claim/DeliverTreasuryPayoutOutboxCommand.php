<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Claim;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Execution\DurableTreasuryPayout;

final class DeliverTreasuryPayoutOutboxCommand extends Command
{
    protected $signature = 'x-change:claim:deliver-treasury-payouts {--limit=100}';

    protected $description = 'Recover committed treasury payout requests awaiting submission';

    public function handle(DurableTreasuryPayout $outbox): int
    {
        $count = $outbox->deliverPending((int) $this->option('limit'));
        $this->components->info("Processed {$count} treasury payout request(s).");

        return self::SUCCESS;
    }
}
