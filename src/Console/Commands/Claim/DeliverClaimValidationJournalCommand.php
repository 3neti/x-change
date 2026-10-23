<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Claim;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Claim\ClaimValidationDecisionJournal;

final class DeliverClaimValidationJournalCommand extends Command
{
    protected $signature = 'x-change:claim:deliver-validation-journal {--limit=100}';

    protected $description = 'Retry pending claim validation decisions in x-journal';

    public function handle(ClaimValidationDecisionJournal $journal): int
    {
        $count = $journal->deliverPending((int) $this->option('limit'));
        $this->components->info("Processed {$count} claim validation decision(s).");

        return self::SUCCESS;
    }
}
