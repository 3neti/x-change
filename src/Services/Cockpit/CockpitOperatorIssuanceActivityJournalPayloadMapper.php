<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalPayloadData;

class CockpitOperatorIssuanceActivityJournalPayloadMapper
{
    private const EVENT_NAME = 'cockpit.operator_issuance_activity.recorded';

    private const IDEMPOTENCY_NAMESPACE = 'cockpit.operator_issuance_activity';

    /**
     * @var array<int, string>
     */
    private const SENSITIVE_METADATA_KEYS = [
        'raw_payload',
        'provider_payload',
        'wallet',
        'recipient_secret',
        'otp',
        'funding_source',
    ];

    public function map(CockpitOperatorIssuanceActivityItemData $activity): CockpitOperatorIssuanceActivityJournalPayloadData
    {
        return new CockpitOperatorIssuanceActivityJournalPayloadData(
            idempotency_key: $this->idempotencyKey($activity),
            actor: [
                'type' => 'operator',
                'id' => $activity->operator_id,
            ],
            subject: [
                'type' => 'pay_code',
                'reference' => $activity->code,
            ],
            references: [
                'activity_id' => $activity->id,
                'correlation_id' => $activity->correlation_id,
                'causation_id' => $activity->id,
            ],
            payload: [
                'activity_id' => $activity->id,
                'code' => $activity->code,
                'amount' => $activity->amount,
                'currency' => $activity->currency,
                'status' => $activity->status,
                'issued_at' => $activity->issued_at,
                'route' => $activity->route,
                'detail_href' => $activity->detail_href,
            ],
            metadata: [
                ...$this->safeMetadata($activity->metadata),
                'redactions' => [
                    'raw_payloads_exposed' => false,
                    'provider_payloads_exposed' => false,
                    'wallet_data_exposed' => false,
                    'recipient_secrets_exposed' => false,
                    'otp_exposed' => false,
                    'funding_source_exposed' => false,
                ],
            ],
        );
    }

    private function idempotencyKey(CockpitOperatorIssuanceActivityItemData $activity): string
    {
        return hash('sha256', implode('|', [
            self::IDEMPOTENCY_NAMESPACE,
            $activity->id,
            $activity->correlation_id ?? '',
            $activity->code,
            $activity->operator_id ?? '',
        ]));
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function safeMetadata(array $metadata): array
    {
        foreach (self::SENSITIVE_METADATA_KEYS as $key) {
            unset($metadata[$key]);
        }

        $metadata = [
            'source' => 'x-change.cockpit',
            ...$metadata,
        ];

        return $metadata;
    }
}
