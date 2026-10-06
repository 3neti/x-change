<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Commands\Funding\Concerns\RecoversStandingFundingAddress;

final class ReconcileAmbiguousStandingFundingCommand extends Command
{
    use RecoversStandingFundingAddress;

    protected $signature = 'xchange:funding:standing-runtime:reconcile-ambiguous {address} {--generation=} {--reason=provider_truth_reconciled} {--actor=operator} {--apply}';

    protected $description = 'Preview or mark an ambiguous Standing Funding outcome reconciled';

    protected function recoveryAction(): string
    {
        return 'reconcile-ambiguous';
    }
}
