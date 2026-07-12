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
        $recipient = $this->recipient($campaignContext);

        return new CockpitIssuanceDraftData(
            template_key: $template['key'],
            amount: $recipient['amount'],
            currency: $recipient['currency'],
            count: max(1, (int) data_get($campaignContext, 'count', 1)),
            recipient_reference: $recipient['reference'],
            purpose: $recipient['purpose'],
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
                'mobile' => $recipient['mobile'],
                'email' => $recipient['email'],
                'webhook' => $this->string($campaignContext, 'feedback.webhook'),
            ],
            rider: [
                'message' => $recipient['message'],
            ],
            validation: array_filter([
                'mobile' => $recipient['mobile'],
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            input_fields: array_values(array_filter([
                $recipient['mobile'] !== null ? 'mobile' : null,
            ])),
            metadata: [
                'campaign' => [
                    'source' => $this->string($campaignContext, 'source', 'x-campaign'),
                    'template_intent' => $template['intent'],
                    'template_key' => $template['key'],
                    'template_mapping_source' => $template['source'],
                    'recipient_context' => $recipient['context'],
                    'recipient_mapping_source' => $recipient['source'],
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

    /**
     * @return array{
     *     amount: mixed,
     *     currency: string,
     *     reference: ?string,
     *     purpose: ?string,
     *     mobile: ?string,
     *     email: ?string,
     *     message: ?string,
     *     context: array<string, mixed>,
     *     source: string
     * }
     */
    private function recipient(array $campaignContext): array
    {
        $explicit = $this->hasExplicitRecipientDraftFields($campaignContext);
        $amount = data_get($campaignContext, 'amount') ?? data_get($campaignContext, 'payout.amount') ?? data_get($campaignContext, 'allocation.amount') ?? data_get($campaignContext, 'recipient.amount');
        $currency = $this->string($campaignContext, 'currency')
            ?? $this->string($campaignContext, 'payout.currency')
            ?? $this->string($campaignContext, 'allocation.currency')
            ?? 'PHP';
        $reference = $this->string($campaignContext, 'recipient_reference')
            ?? $this->string($campaignContext, 'recipient.reference')
            ?? $this->string($campaignContext, 'recipient.id')
            ?? $this->string($campaignContext, 'recipient.code')
            ?? $this->string($campaignContext, 'recipient.mobile')
            ?? $this->string($campaignContext, 'recipient.mobile_number')
            ?? $this->string($campaignContext, 'recipient.msisdn');
        $mobile = $this->string($campaignContext, 'feedback.mobile')
            ?? $this->string($campaignContext, 'recipient.mobile')
            ?? $this->string($campaignContext, 'recipient.mobile_number')
            ?? $this->string($campaignContext, 'recipient.phone')
            ?? $this->string($campaignContext, 'recipient.msisdn');
        $email = $this->string($campaignContext, 'feedback.email')
            ?? $this->string($campaignContext, 'recipient.email')
            ?? $this->string($campaignContext, 'recipient.email_address');
        $purpose = $this->string($campaignContext, 'purpose')
            ?? $this->string($campaignContext, 'payout.purpose')
            ?? $this->string($campaignContext, 'allocation.purpose')
            ?? $this->string($campaignContext, 'recipient.purpose');
        $message = $this->string($campaignContext, 'rider.message')
            ?? $this->string($campaignContext, 'message')
            ?? $this->string($campaignContext, 'payout.message')
            ?? $this->string($campaignContext, 'recipient.message');

        return [
            'amount' => $amount,
            'currency' => $currency,
            'reference' => $reference,
            'purpose' => $purpose,
            'mobile' => $mobile,
            'email' => $email,
            'message' => $message,
            'context' => array_filter([
                'recipient_reference' => $reference,
                'mobile' => $mobile,
                'email' => $email,
                'amount' => $amount,
                'currency' => $currency,
                'purpose' => $purpose,
                'message' => $message,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            'source' => $explicit ? 'explicit-draft-fields' : 'campaign-recipient-context',
        ];
    }

    private function hasExplicitRecipientDraftFields(array $campaignContext): bool
    {
        return data_get($campaignContext, 'amount') !== null
            || $this->string($campaignContext, 'recipient_reference') !== null
            || $this->string($campaignContext, 'purpose') !== null
            || $this->string($campaignContext, 'feedback.mobile') !== null
            || $this->string($campaignContext, 'feedback.email') !== null
            || $this->string($campaignContext, 'rider.message') !== null;
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
