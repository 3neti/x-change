<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Cockpit;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Services\Cockpit\CockpitOperatorIssuanceActivityRuntimeProfileInspector;

class ShowCockpitOperatorActivityRuntimeProfileCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:cockpit:operator-activity-runtime-profile
        {--json : Output JSON}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Inspect the Cockpit operator activity runtime configuration profile.';

    public function handle(CockpitOperatorIssuanceActivityRuntimeProfileInspector $inspector): int
    {
        $this->renderPayload(
            $inspector->inspect()->toArray(),
            'Cockpit operator activity runtime profile.',
        );

        return self::SUCCESS;
    }
}
