<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitDistributionWorkspaceReadModelData extends Data
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  array<int, CockpitDistributionWorkspaceItemData>  $share_assets
     * @param  array<int, CockpitDistributionWorkspaceItemData>  $channels
     * @param  array<int, CockpitDistributionWorkspaceItemData>  $print_templates
     * @param  array<int, CockpitDistributionWorkspaceItemData>  $analytics
     * @param  array<int, CockpitDistributionWorkspaceItemData>  $actions
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.distribution-workspace.v1',
        public readonly string $status = 'not_wired',
        public readonly bool $authorized = false,
        public readonly ?string $code = null,
        public readonly array $summary = [],
        public readonly array $share_assets = [],
        public readonly array $channels = [],
        public readonly array $print_templates = [],
        public readonly array $analytics = [],
        public readonly array $actions = [],
        public readonly array $redactions = ['payloads' => 'not-loaded'],
    ) {}
}
