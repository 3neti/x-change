<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Claim;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Settlement\V2EvidenceEventJournal;

final class DeliverV2EvidenceJournalCommand extends Command
{
    protected $signature = 'x-change:v2:deliver-evidence {--limit=100}';

    protected $description = 'Deliver pending v2 evidence events to the journal.';

    public function handle(V2EvidenceEventJournal $journal): int
    {
        $count = $journal->deliverPending((int) $this->option('limit'));
        $this->components->info("Processed {$count} pending v2 evidence event(s).");

        return self::SUCCESS;
    }
}
