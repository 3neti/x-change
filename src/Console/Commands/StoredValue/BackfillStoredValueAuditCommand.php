<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\StoredValue;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\StoredValue\StoredValueAuditBackfill;

final class BackfillStoredValueAuditCommand extends Command
{
    protected $signature = 'x-change:stored-value:backfill-audit {--limit=100} {--commit} {--json}';

    protected $description = 'Preview or restore missing stored-value audit outbox rows from verified Treasury facts';

    public function handle(StoredValueAuditBackfill $backfill): int
    {
        $result = $backfill->run((int) $this->option('limit'), (bool) $this->option('commit'));
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $mode = $this->option('commit') ? 'Committed' : 'Previewed';
            $this->components->info("{$mode} {$result['verified']} verified audit fact(s); {$result['unverified']} could not be verified.");
        }

        return $result['unverified'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
