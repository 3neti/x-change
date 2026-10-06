<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Commands\Funding\Concerns\RecoversStandingFundingAddress;

final class QuarantineStandingFundingAddressCommand extends Command
{
    use RecoversStandingFundingAddress;

    protected $signature = 'xchange:funding:standing-runtime:quarantine-address {address} {--generation=} {--reason=operator_quarantine} {--actor=operator} {--apply}';

    protected $description = 'Preview or quarantine one Standing Funding Address';

    protected function recoveryAction(): string
    {
        return 'quarantine';
    }
}
