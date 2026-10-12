<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Configuration\CommissioningReleaseRefresher;
use Throwable;

final class RefreshCommissioningReleaseCommand extends Command
{
    protected $signature = 'x-change:commissioning:refresh-release';

    protected $description = 'Refresh release metadata after verifying the commissioned identity.';

    public function handle(CommissioningReleaseRefresher $refresher): int
    {
        try {
            $disposition = $refresher->refresh();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Commissioning release refresh: {$disposition}.");

        return self::SUCCESS;
    }
}
