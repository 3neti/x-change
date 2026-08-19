<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Slices;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Slices\VoucherSliceExecutionJournal;

final class DeliverVoucherSliceExecutionJournalCommand extends Command
{
    protected $signature = 'x-change:slices:deliver-journal {--limit=100 : Maximum pending events to deliver}';

    protected $description = 'Deliver pending durable slice execution evidence to x-journal.';

    public function handle(VoucherSliceExecutionJournal $journal): int
    {
        $count = $journal->deliverPending(max(1, (int) $this->option('limit')));

        $this->components->info("Processed {$count} pending slice journal event(s).");

        return self::SUCCESS;
    }
}
