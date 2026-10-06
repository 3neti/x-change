<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Commands\Funding\Concerns\RecoversStandingFundingAddress;

final class RetryStandingFundingAddressCommand extends Command
{
    use RecoversStandingFundingAddress;

    protected $signature = 'xchange:funding:standing-runtime:retry {address} {--generation=} {--reason=operator_retry} {--actor=operator} {--apply}';

    protected $description = 'Preview or make one recovered Standing Funding Address eligible';

    protected function recoveryAction(): string
    {
        return 'retry';
    }
}
