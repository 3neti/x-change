<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitQuickGenerateCampaignContextData extends Data
{
    /**
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.quick-generate-campaign-context.v1',
        public readonly string $status = 'not_wired',
        public readonly bool $authorized = false,
        public readonly bool $read_only = true,
        public readonly bool $mutates_campaign = false,
        public readonly ?string $planning_key = null,
        public readonly ?string $execution_id = null,
        public readonly ?string $campaign_id = null,
        public readonly ?string $audience_id = null,
        public readonly ?string $recipient_id = null,
        public readonly ?string $source = null,
        public readonly ?CockpitIssuanceDraftData $draft = null,
        public readonly array $redactions = ['payloads' => 'not-loaded'],
    ) {}
}
