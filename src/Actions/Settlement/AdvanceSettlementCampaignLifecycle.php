<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use Illuminate\Contracts\Auth\Authenticatable;
use LBHurtado\XChange\Data\Settlement\CompletionPayCodeInstructionsData;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;
use LBHurtado\XChange\Models\ProvisionalCoverage;
use LBHurtado\XChange\Services\Settlement\AuiPersonalAccidentCampaignCoverageDriver;

final readonly class AdvanceSettlementCampaignLifecycle
{
    public function __construct(
        private OrchestrateProvisionalCoverage $coverage,
        private IssueCompletionPayCode $completionPayCode,
    ) {}

    public function handle(CampaignPaymentRecognition $recognition): ?ProvisionalCoverage
    {
        $driverId = (string) data_get(
            $recognition->campaignRecord()->settings,
            'scenario_run.envelope_driver_id',
        );
        $driverVersion = (string) data_get(
            $recognition->campaignRecord()->settings,
            'scenario_run.envelope_driver_version',
        );

        if ($driverId !== AuiPersonalAccidentCampaignCoverageDriver::DRIVER_ID
            || $driverVersion !== AuiPersonalAccidentCampaignCoverageDriver::DRIVER_VERSION) {
            return null;
        }

        $orchestration = $this->coverage->handle($recognition, $driverId, $driverVersion);

        if ($orchestration->binding === null) {
            return null;
        }

        $owner = $recognition->ownerRecord();

        if (! $owner instanceof Authenticatable) {
            throw new \LogicException('Campaign owner must be authenticatable to issue the completion Pay Code.');
        }

        $this->completionPayCode->handle(
            $orchestration->binding->coverage,
            $owner,
            new CompletionPayCodeInstructionsData(
                applicantFields: ['name', 'mobile', 'email', 'address', 'birth_date'],
                requiresOtp: true,
                prefix: 'POLI',
                message: 'Complete your personal details to prepare your policy.',
            ),
        );

        return $orchestration->binding->coverage;
    }
}
