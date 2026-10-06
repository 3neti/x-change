<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Commands\Funding\Concerns\RecoversStandingFundingAddress;

final class ReleaseStandingFundingStaleLeaseCommand extends Command
{
    use RecoversStandingFundingAddress;

    protected $signature = 'xchange:funding:standing-runtime:release-stale-lease {address} {--generation=} {--reason=operator_stale_lease_release} {--actor=operator} {--apply}';

    protected $description = 'Preview or release one expired Standing Funding lease';

    protected function recoveryAction(): string
    {
        return 'release-stale-lease';
    }
}
