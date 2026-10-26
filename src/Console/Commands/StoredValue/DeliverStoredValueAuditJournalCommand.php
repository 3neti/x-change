<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\StoredValue;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\StoredValue\StoredValueAuditJournal;

final class DeliverStoredValueAuditJournalCommand extends Command
{
    protected $signature = 'x-change:stored-value:deliver-audit-journal {--limit=100}';

    protected $description = 'Retry pending stored-value activation and collection audit events in x-journal';

    public function handle(StoredValueAuditJournal $journal): int
    {
        $count = $journal->deliverPending((int) $this->option('limit'));
        $this->components->info("Processed {$count} stored-value audit event(s).");

        return self::SUCCESS;
    }
}
