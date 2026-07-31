<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Configuration\CommissioningManifestRecorder;
use LBHurtado\XChange\Services\Configuration\PreInstallReadinessInspector;
use LBHurtado\XChange\Services\Treasury\SystemPrincipalProvisioningService;
use LBHurtado\XChange\Services\Treasury\TreasuryInitializationStateService;
use Throwable;

final class AdoptCommissioningManifestCommand extends Command
{
    protected $signature = 'x-change:commissioning:adopt
        {--confirm-existing-installation : Confirm the existing installation is controlled and complete}';

    protected $description = 'Adopt a verified existing X-Change installation into Commissioning Mode.';

    public function handle(
        PreInstallReadinessInspector $readiness,
        SystemPrincipalProvisioningService $principal,
        TreasuryInitializationStateService $treasury,
        CommissioningManifestRecorder $manifests,
    ): int {
        if (! (bool) $this->option('confirm-existing-installation')) {
            $this->components->error('Adoption requires [--confirm-existing-installation].');

            return self::FAILURE;
        }

        try {
            $inspection = $readiness->inspect();
            $principalState = $principal->inspect();
            $treasuryState = $treasury->inspect();

            if (
                ! $inspection['ready']
                || $principalState->status !== 'existing'
                || $treasuryState->uninitialized !== []
                || $treasuryState->incomplete !== []
                || $treasuryState->initialized === []
            ) {
                $this->components->error(
                    'Existing installation failed strict identity or Treasury adoption checks.',
                );

                return self::FAILURE;
            }

            $manifests->record();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Existing X-Change installation commissioned.');

        return self::SUCCESS;
    }
}
