<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Funding\StandingFundingRuntimeOutboxProcessor;

final class ProcessStandingFundingRuntimeOutboxCommand extends Command
{
    protected $signature = 'xchange:funding:standing-runtime:process-outbox {--limit=100}';

    protected $description = 'Project Standing Funding runtime evidence to journal and realtime channels';

    public function handle(StandingFundingRuntimeOutboxProcessor $processor): int
    {
        $count = $processor->process((int) $this->option('limit'));
        $this->components->info("Processed {$count} Standing Funding runtime event(s).");

        return self::SUCCESS;
    }
}
