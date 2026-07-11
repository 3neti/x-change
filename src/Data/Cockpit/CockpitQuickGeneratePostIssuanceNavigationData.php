<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitQuickGeneratePostIssuanceNavigationData extends Data
{
    /**
     * @param  array<int, CockpitQuickGeneratePostIssuanceNavigationItemData>  $items
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.quick-generate-post-issuance-navigation.v1',
        public readonly string $status = 'not_wired',
        public readonly bool $auto_redirect = false,
        public readonly array $items = [],
        public readonly array $redactions = ['payloads' => 'not-loaded'],
    ) {}
}
