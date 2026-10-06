<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Commands\Funding\Concerns\TransitionsStandingFundingRuntime;
use LBHurtado\XChange\Enums\StandingFundingRuntimeMode;

final class DrainStandingFundingRuntimeCommand extends Command
{
    use TransitionsStandingFundingRuntime;

    protected $signature = 'xchange:funding:standing-runtime:drain {--provider=netbank} {--generation=} {--address=} {--reason=operator_drain} {--actor=operator} {--apply}';

    protected $description = 'Preview or drain Standing Funding synchronization';

    protected function targetMode(): StandingFundingRuntimeMode
    {
        return StandingFundingRuntimeMode::Draining;
    }
}
