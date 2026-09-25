<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use Illuminate\Contracts\Auth\Authenticatable;
use LBHurtado\XChange\Data\Settlement\CompletionPayCodeInstructionsData;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;
use LBHurtado\XChange\Models\ProvisionalCoverage;
use LBHurtado\XChange\Services\Settlement\AuiPersonalAccidentCampaignCoverageDriver;
use LBHurtado\XChange\Services\Settlement\CampaignWalletPayerMobile;
use LBHurtado\XChange\Services\Settlement\CampaignWorkflowPublicationResolver;

final readonly class AdvanceSettlementCampaignLifecycle
{
    public function __construct(
        private OrchestrateProvisionalCoverage $coverage,
        private IssueCompletionPayCode $completionPayCode,
        private CampaignWorkflowPublicationResolver $publications,
    ) {}

    public function handle(CampaignPaymentRecognition $recognition): ?ProvisionalCoverage
    {
        $publication = $this->publications->forCampaignRevision($recognition->campaignRecord(), (string) $recognition->campaign_revision_id);
        $driverId = $publication['workflow_id'] ?? (string) data_get(
            $recognition->campaignRecord()->settings,
            'scenario_run.envelope_driver_id',
        );
        $driverVersion = $publication['workflow_version'] ?? (string) data_get(
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

        $existing = $orchestration->binding->coverage->completionPayCodeIssuance()->first();
        $requiresOtp = $existing !== null
            ? (bool) data_get($existing->requirements_snapshot, 'requires_otp', true)
            : ((bool) data_get($publication, 'completion.requires_otp', false) || (new CampaignWalletPayerMobile)->resolve($recognition) === null);

        $this->completionPayCode->handle(
            $orchestration->binding->coverage,
            $owner,
            new CompletionPayCodeInstructionsData(
                applicantFields: $publication['completion']['applicant_fields'] ?? ['name', 'mobile', 'email', 'address', 'birth_date'],
                requiresOtp: $requiresOtp,
                prefix: $publication['completion']['prefix'] ?? 'POLI',
                mask: $publication['completion']['mask'] ?? '****',
                message: $publication !== null ? $publication['completion']['message'] : 'Complete your personal details to prepare your policy.',
            ),
        );

        return $orchestration->binding->coverage;
    }
}
