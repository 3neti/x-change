<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitCampaignReadModelData extends Data
{
    /**
     * @param  array<int, array{key: string, status: string, enabled: bool, read_only: bool, reason: string}>  $surfaces
     * @param  array<string, mixed>  $facts
     * @param  array{enabled: bool, status: string, reason: string}  $mutation
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.campaign-adoption.v1',
        public readonly string $status = 'not_wired',
        public readonly bool $authorized = false,
        public readonly string $source = 'null-campaign-cockpit-read-model-provider',
        public readonly array $surfaces = [
            [
                'key' => 'campaign_dashboard',
                'status' => 'not_wired',
                'enabled' => false,
                'read_only' => true,
                'reason' => 'x-campaign-adapter-not-configured',
            ],
            [
                'key' => 'campaign_explorer',
                'status' => 'not_wired',
                'enabled' => false,
                'read_only' => true,
                'reason' => 'x-campaign-adapter-not-configured',
            ],
            [
                'key' => 'audience_import_workspace',
                'status' => 'not_wired',
                'enabled' => false,
                'read_only' => true,
                'reason' => 'x-campaign-adapter-not-configured',
            ],
            [
                'key' => 'attachment_operator_workspace',
                'status' => 'not_wired',
                'enabled' => false,
                'read_only' => true,
                'reason' => 'x-campaign-adapter-not-configured',
            ],
            [
                'key' => 'campaign_api_descriptors',
                'status' => 'not_wired',
                'enabled' => false,
                'read_only' => true,
                'reason' => 'x-campaign-adapter-not-configured',
            ],
        ],
        public readonly array $facts = [],
        public readonly array $mutation = [
            'enabled' => false,
            'status' => 'blocked',
            'reason' => 'campaign-mutations-not-authorized',
        ],
        public readonly array $redactions = ['payloads' => 'not-loaded'],
    ) {}
}
