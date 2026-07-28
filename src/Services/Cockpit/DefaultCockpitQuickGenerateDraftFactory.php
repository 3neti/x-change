<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitQuickGenerateDraftFactoryContract;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceCampaignContextData;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;

class DefaultCockpitQuickGenerateDraftFactory implements CockpitQuickGenerateDraftFactoryContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function fromPayload(
        array $payload,
        ?string $idempotencyKey = null,
        ?string $correlationId = null,
    ): CockpitIssuanceDraftData {
        return new CockpitIssuanceDraftData(
            template_key: $this->string($payload, 'metadata.custom.cockpit.template_key', 'money-changer') ?? 'money-changer',
            amount: data_get($payload, 'cash.amount'),
            currency: $this->string($payload, 'cash.currency', 'PHP') ?? 'PHP',
            count: max(1, (int) data_get($payload, 'count', 1)),
            recipient_reference: $this->string($payload, 'feedback.mobile')
                ?? $this->string($payload, 'cash.validation.mobile'),
            purpose: $this->string($payload, 'rider.message')
                ?? $this->string($payload, 'metadata.custom.cockpit.purpose'),
            idempotency_key: $idempotencyKey ?? $this->string($payload, '_meta.idempotency_key'),
            correlation_id: $correlationId ?? $this->string($payload, '_meta.correlation_id'),
            campaign: $this->campaign($payload),
            feedback: [
                'email' => $this->string($payload, 'feedback.email'),
                'mobile' => $this->string($payload, 'feedback.mobile'),
                'webhook' => $this->string($payload, 'feedback.webhook'),
            ],
            rider: $this->rider($payload),
            validation: (array) data_get($payload, 'cash.validation', []),
            input_fields: array_values((array) data_get($payload, 'inputs.fields', [])),
            metadata: $this->metadata($payload),
        );
    }

    private function string(array $source, string $key, ?string $default = null): ?string
    {
        $value = data_get($source, $key, $default);

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function rider(array $payload): array
    {
        $rider = (array) data_get($payload, 'rider', []);
        $allowed = [
            'message',
            'message_format',
            'url',
            'redirect_timeout',
            'splash',
            'splash_format',
            'splash_timeout',
            'splash_meta',
            'og_source',
            'stamp',
        ];

        return array_intersect_key($rider, array_flip($allowed));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metadata(array $payload): array
    {
        return array_replace_recursive((array) data_get($payload, 'metadata', []), [
            'custom' => [
                'cockpit' => [
                    'source' => $this->string($payload, 'metadata.custom.cockpit.source', 'cockpit.quick-generate')
                        ?? 'cockpit.quick-generate',
                ],
            ],
        ]);
    }

    private function campaign(array $payload): ?CockpitIssuanceCampaignContextData
    {
        $campaign = (array) data_get($payload, 'metadata.campaign', []);

        $hasContext = collect([
            'planning_key',
            'execution_id',
            'campaign_id',
            'audience_id',
            'recipient_id',
        ])->contains(fn (string $key): bool => $this->string($campaign, $key) !== null);

        if (! $hasContext) {
            return null;
        }

        return new CockpitIssuanceCampaignContextData(
            planning_key: $this->string($campaign, 'planning_key'),
            execution_id: $this->string($campaign, 'execution_id'),
            campaign_id: $this->string($campaign, 'campaign_id'),
            audience_id: $this->string($campaign, 'audience_id'),
            recipient_id: $this->string($campaign, 'recipient_id'),
            source: $this->string($campaign, 'source', 'x-campaign'),
            metadata: (array) data_get($campaign, 'metadata', []),
        );
    }
}
