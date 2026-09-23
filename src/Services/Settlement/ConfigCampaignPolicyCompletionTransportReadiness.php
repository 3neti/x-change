<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\XChange\Contracts\CampaignPolicyCompletionTransportReadinessContract;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionTransportReadinessData;
use LBHurtado\XChange\Enums\PolicyCompletionRequestStatus;
use LBHurtado\XChange\Models\PolicyCompletionRequest;

final class ConfigCampaignPolicyCompletionTransportReadiness implements CampaignPolicyCompletionTransportReadinessContract
{
    /** @var list<string> */
    private const REQUIRED_FIELDS = [
        'contract_id',
        'contract_version',
        'request_schema_version',
        'response_schema_version',
        'idempotency_mechanism',
        'connect_timeout_seconds',
        'response_timeout_seconds',
        'retry_policy',
        'ambiguous_outcome_policy',
        'reconciliation_mode',
        'credential_reference',
        'credentials_configured',
    ];

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

        $transports = config('x-change.settlement.policy_completion.transports', []);
        $disposition = is_array($transports)
            ? ($transports[$driverId.'@'.$driverVersion] ?? null)
            : null;

        if (! is_array($disposition)) {
            return $this->notReady(
                $driverId,
                $driverVersion,
                'not_configured',
                'No accepted transport disposition exists for the exact policy completion driver version.',
                self::REQUIRED_FIELDS,
            );
        }

        if (($disposition['enabled'] ?? false) !== true) {
            return $this->notReady(
                $driverId,
                $driverVersion,
                'disabled',
                'Policy completion transport is disabled.',
            );
        }

        $missing = $this->missingFields($disposition);
        if ($missing !== []) {
            return $this->notReady(
                $driverId,
                $driverVersion,
                'incomplete',
                'Policy completion transport disposition is incomplete.',
                $missing,
            );
        }

        $normalized = $this->normalizedDisposition($disposition);

        return new PolicyCompletionTransportReadinessData(
            ready: true,
            status: 'ready',
            reason: 'The exact policy completion transport disposition is accepted and enabled.',
            driverId: $driverId,
            driverVersion: $driverVersion,
            dispositionFingerprint: hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR)),
        );
    }

    /**
     * @param  array<string, mixed>  $disposition
     * @return list<string>
     */
    private function missingFields(array $disposition): array
    {
        $missing = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            $value = $disposition[$field] ?? null;
            $present = $field === 'credentials_configured'
                ? $value === true
                : ($field === 'connect_timeout_seconds' || $field === 'response_timeout_seconds'
                    ? is_int($value) && $value > 0
                    : is_string($value) && trim($value) !== '');

            if (! $present) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $disposition
     * @return array<string, bool|int|string>
     */
    private function normalizedDisposition(array $disposition): array
    {
        $normalized = ['enabled' => true];

        foreach (self::REQUIRED_FIELDS as $field) {
            $value = $disposition[$field];
            $normalized[$field] = is_string($value) ? trim($value) : $value;
        }

        ksort($normalized);

        return $normalized;
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
