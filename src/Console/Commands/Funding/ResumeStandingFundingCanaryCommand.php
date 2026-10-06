<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Commands\Funding\Concerns\TransitionsStandingFundingRuntime;
use LBHurtado\XChange\Enums\StandingFundingRuntimeMode;

final class ResumeStandingFundingCanaryCommand extends Command
{
    use TransitionsStandingFundingRuntime;

    protected $signature = 'xchange:funding:standing-runtime:resume-canary {--provider=netbank} {--generation=} {--address=} {--reason=operator_canary} {--actor=operator} {--apply}';

    protected $description = 'Preview or resume one Standing Funding canary';

    protected function targetMode(): StandingFundingRuntimeMode
    {
        return StandingFundingRuntimeMode::Canary;
    }
}
