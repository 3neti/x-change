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
        $template = $this->template($campaignContext);

        return new CockpitIssuanceDraftData(
            template_key: $template['key'],
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
                    'template_intent' => $template['intent'],
                    'template_key' => $template['key'],
                    'template_mapping_source' => $template['source'],
                ],
            ],
        );
    }

    /**
     * @return array{key: string, intent: array<string, mixed>, source: string}
     */
    private function template(array $campaignContext): array
    {
        $explicit = $this->string($campaignContext, 'template_key');

        if ($explicit !== null) {
            return [
                'key' => $explicit,
                'intent' => $this->templateIntent($campaignContext),
                'source' => 'explicit-template-key',
            ];
        }

        return [
            'key' => $this->templateKeyFromIntent($campaignContext) ?? 'ofw-remittance',
            'intent' => $this->templateIntent($campaignContext),
            'source' => 'campaign-template-intent',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templateIntent(array $campaignContext): array
    {
        return array_filter([
            'template_intent' => data_get($campaignContext, 'template_intent'),
            'product_key' => data_get($campaignContext, 'product_key'),
            'product' => data_get($campaignContext, 'product'),
            'template' => data_get($campaignContext, 'template'),
            'program' => data_get($campaignContext, 'program'),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function templateKeyFromIntent(array $campaignContext): ?string
    {
        $candidates = [
            $this->string($campaignContext, 'template_intent'),
            $this->string($campaignContext, 'product_key'),
            $this->string($campaignContext, 'product.key'),
            $this->string($campaignContext, 'product.slug'),
            $this->string($campaignContext, 'template.intent'),
            $this->string($campaignContext, 'template.key'),
            $this->string($campaignContext, 'template.profile'),
            $this->string($campaignContext, 'program.type'),
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeIntent($candidate);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeIntent(?string $intent): ?string
    {
        if ($intent === null) {
            return null;
        }

        return match (str($intent)->lower()->replace(['_', ' '], '-')->toString()) {
            'money-changer',
            'cash',
            'cash-out',
            'branch-cash',
            'branch-cash-out',
            'branch-counter-cash-out' => 'money-changer',
            'ofw-remittance',
            'remittance',
            'payout',
            'campaign-payout' => 'ofw-remittance',
            'settlement-envelope',
            'settlement' => 'settlement-envelope',
            default => null,
        };
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
