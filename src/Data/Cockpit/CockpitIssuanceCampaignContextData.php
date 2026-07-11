<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitIssuanceCampaignContextData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly ?string $planning_key = null,
        public readonly ?string $execution_id = null,
        public readonly ?string $campaign_id = null,
        public readonly ?string $audience_id = null,
        public readonly ?string $recipient_id = null,
        public readonly ?string $source = null,
        public readonly array $metadata = [],
    ) {}
}
