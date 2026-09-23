<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use DomainException;
use LBHurtado\XChange\Contracts\CampaignPolicyCompletionTransportReadinessContract;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionTransportDispositionData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionTransportReadinessData;
use LBHurtado\XChange\Enums\PolicyCompletionRequestStatus;
use LBHurtado\XChange\Models\PolicyCompletionRequest;

final class ConfigCampaignPolicyCompletionTransportReadiness implements CampaignPolicyCompletionTransportReadinessContract
{
    public function __construct(
        private readonly PolicyCompletionTransportDispositionCatalog $dispositions,
    ) {}

    public function for(PolicyCompletionRequest $request): PolicyCompletionTransportReadinessData
    {
        $driverId = trim((string) $request->driver_id);
        $driverVersion = trim((string) $request->driver_version);

        if ($request->status !== PolicyCompletionRequestStatus::Authorized) {
            return $this->notReady(
                $driverId,
                $driverVersion,
                'not_authorized',
                'Policy completion transport requires an authorized request.',
            );
        }

        try {
            $disposition = $this->dispositions->for($driverId, $driverVersion);
        } catch (DomainException) {
            return $this->notReady(
                $driverId,
                $driverVersion,
                'invalid',
                'Policy completion transport disposition failed validation.',
            );
        }

        if ($disposition === null) {
            return $this->notReady(
                $driverId,
                $driverVersion,
                'not_configured',
                'No accepted transport disposition exists for the exact policy completion driver version.',
                PolicyCompletionTransportDispositionData::REQUIRED_FIELDS,
            );
        }

        $credential = config($disposition->credentialReference());
        if (! is_string($credential) || trim($credential) === '') {
            return $this->notReady(
                $driverId,
                $driverVersion,
                'credentials_unavailable',
                'Policy completion transport credential reference is not configured.',
            );
        }

        return new PolicyCompletionTransportReadinessData(
            ready: true,
            status: 'ready',
            reason: 'The exact policy completion transport disposition is accepted and enabled.',
            driverId: $driverId,
            driverVersion: $driverVersion,
            dispositionFingerprint: $disposition->fingerprint(),
        );
    }

    /**
     * @param  list<string>  $missingFields
     */
    private function notReady(
        string $driverId,
        string $driverVersion,
        string $status,
        string $reason,
        array $missingFields = [],
    ): PolicyCompletionTransportReadinessData {
        return new PolicyCompletionTransportReadinessData(
            ready: false,
            status: $status,
            reason: $reason,
            driverId: $driverId,
            driverVersion: $driverVersion,
            missingFields: $missingFields,
        );
    }
}
