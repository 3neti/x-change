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
     * @param  array<string, mixed>  $quick_generate_link
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
        public readonly array $quick_generate_link = [
            'schema' => 'x-change.cockpit.campaign-quick-generate-link.v1',
            'status' => 'not_available',
            'enabled' => false,
            'label' => 'Open Quick Generate',
            'href' => null,
            'route' => 'x-change.cockpit.quick-generate',
            'read_only' => true,
            'mutates_campaign' => false,
            'planning_key' => null,
            'execution_id' => null,
            'campaign_id' => null,
            'audience_id' => null,
            'recipient_id' => null,
            'source' => null,
            'draft' => null,
            'redactions' => ['payloads' => 'not-loaded'],
        ],
        public readonly array $redactions = ['payloads' => 'not-loaded'],
    ) {}
}
