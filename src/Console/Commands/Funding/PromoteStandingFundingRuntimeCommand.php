<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Commands\Funding\Concerns\TransitionsStandingFundingRuntime;
use LBHurtado\XChange\Enums\StandingFundingRuntimeMode;

final class PromoteStandingFundingRuntimeCommand extends Command
{
    use TransitionsStandingFundingRuntime;

    protected $signature = 'xchange:funding:standing-runtime:promote {mode=bounded} {--provider=netbank} {--generation=} {--address=} {--reason=operator_promotion} {--actor=operator} {--apply}';

    protected $description = 'Preview or promote Standing Funding to bounded or scheduled mode';

    protected function targetMode(): StandingFundingRuntimeMode
    {
        $mode = StandingFundingRuntimeMode::tryFrom(strtolower(trim((string) $this->argument('mode'))));
        if (! in_array($mode, [StandingFundingRuntimeMode::Bounded, StandingFundingRuntimeMode::Scheduled], true)) {
            throw new \InvalidArgumentException('Promotion mode must be bounded or scheduled.');
        }

        return $mode;
    }
}
