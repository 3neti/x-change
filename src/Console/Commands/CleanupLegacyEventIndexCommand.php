<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class CleanupLegacyEventIndexCommand extends Command
{
    protected $signature = 'x-change:events:cleanup-legacy-index';

    protected $description = 'Delete the legacy unbounded event index without loading its contents';

    public function handle(): int
    {
        Cache::forget('xchange:events:index');

        $this->components->info('Legacy event index deleted or already absent.');

        return self::SUCCESS;
    }
}
