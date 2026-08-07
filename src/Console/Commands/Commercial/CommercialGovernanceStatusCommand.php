<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Commercial\CommercialGovernanceInspector;

final class CommercialGovernanceStatusCommand extends Command
{
    protected $signature = 'x-change:commercial:governance-status {--json : Output JSON}';

    protected $description = 'Show Commercial Offering activation and maker-checker readiness.';

    public function handle(CommercialGovernanceInspector $governance): int
    {
        $status = $governance->inspect();

        if ((bool) $this->option('json')) {
            $this->line(json_encode($status, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $status['operational'] ? self::SUCCESS : self::FAILURE;
        }

        $this->components->info('Commercial governance: '.str((string) $status['state'])->replace('_', ' ')->title());
        $this->line((string) $status['message']);
        $this->line('Mode: '.$status['mode']);
        $this->line('Maker authorities: '.$status['roles']['maker_count']);
        $this->line('Checker authorities: '.$status['roles']['checker_count']);

        return $status['operational'] ? self::SUCCESS : self::FAILURE;
    }
}
