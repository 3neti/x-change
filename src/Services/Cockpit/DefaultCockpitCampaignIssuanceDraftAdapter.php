<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitCampaignIssuanceDraftAdapterContract;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceCampaignContextData;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;

class DefaultCockpitCampaignIssuanceDraftAdapter implements CockpitCampaignIssuanceDraftAdapterContract
{
    public function fromCampaignContext(array $campaignContext): CockpitIssuanceDraftData
    {
        return new CockpitIssuanceDraftData(
            template_key: $this->string($campaignContext, 'template_key', 'ofw-remittance'),
            amount: data_get($campaignContext, 'amount'),
            currency: $this->string($campaignContext, 'currency', 'PHP') ?? 'PHP',
            count: max(1, (int) data_get($campaignContext, 'count', 1)),
            recipient_reference: $this->string($campaignContext, 'recipient_reference')
                ?? $this->string($campaignContext, 'recipient.mobile'),
            purpose: $this->string($campaignContext, 'purpose'),
            idempotency_key: $this->string($campaignContext, 'idempotency_key'),
            correlation_id: $this->string($campaignContext, 'correlation_id'),
            campaign: new CockpitIssuanceCampaignContextData(
                planning_key: $this->string($campaignContext, 'planning_key'),
                execution_id: $this->string($campaignContext, 'execution_id'),
                campaign_id: $this->string($campaignContext, 'campaign_id'),
                audience_id: $this->string($campaignContext, 'audience_id'),
                recipient_id: $this->string($campaignContext, 'recipient_id'),
                source: $this->string($campaignContext, 'source', 'x-campaign'),
                metadata: (array) data_get($campaignContext, 'metadata', []),
            ),
            feedback: [
                'mobile' => $this->string($campaignContext, 'feedback.mobile')
                    ?? $this->string($campaignContext, 'recipient.mobile'),
                'email' => $this->string($campaignContext, 'feedback.email')
                    ?? $this->string($campaignContext, 'recipient.email'),
                'webhook' => $this->string($campaignContext, 'feedback.webhook'),
            ],
            rider: [
                'message' => $this->string($campaignContext, 'rider.message')
                    ?? $this->string($campaignContext, 'message'),
            ],
            metadata: [
                'campaign' => [
                    'source' => $this->string($campaignContext, 'source', 'x-campaign'),
                ],
            ],
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
}
