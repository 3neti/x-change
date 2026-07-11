<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitIssuanceDraftData extends Data
{
    /**
     * @param  array<string, mixed>  $feedback
     * @param  array<string, mixed>  $rider
     * @param  array<string, mixed>  $validation
     * @param  array<int, string>  $input_fields
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.issuance-draft.v1',
        public readonly string $status = 'draft',
        public readonly ?string $template_key = null,
        public readonly int|float|string|null $amount = null,
        public readonly string $currency = 'PHP',
        public readonly int $count = 1,
        public readonly ?string $recipient_reference = null,
        public readonly ?string $purpose = null,
        public readonly ?string $idempotency_key = null,
        public readonly ?string $correlation_id = null,
        public readonly ?CockpitIssuanceCampaignContextData $campaign = null,
        public readonly array $feedback = [],
        public readonly array $rider = [],
        public readonly array $validation = [],
        public readonly array $input_fields = [],
        public readonly array $metadata = [],
        public readonly array $redactions = ['payloads' => 'draft-only'],
    ) {}

    public function hasCampaignContext(): bool
    {
        return $this->campaign instanceof CockpitIssuanceCampaignContextData
            && (
                filled($this->campaign->planning_key)
                || filled($this->campaign->execution_id)
                || filled($this->campaign->campaign_id)
            );
    }
}
