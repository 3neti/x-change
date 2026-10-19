<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Claim;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Claim\RiderRedirectJournal;

final class DeliverRiderRedirectJournalCommand extends Command
{
    protected $signature = 'x-change:rider:deliver-journal {--limit=100}';

    protected $description = 'Deliver pending durable rider redirect evidence to x-journal.';

    public function handle(RiderRedirectJournal $journal): int
    {
        $count = $journal->deliver(max(1, min(1000, (int) $this->option('limit'))));
        $this->components->info("Delivered {$count} rider redirect event(s).");

        return self::SUCCESS;
    }
}
