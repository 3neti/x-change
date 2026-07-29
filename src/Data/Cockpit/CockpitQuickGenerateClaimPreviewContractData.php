<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitQuickGenerateClaimPreviewContractData extends Data
{
    /**
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.quick-generate-claim-preview.v1',
        public readonly string $status = 'unavailable',
        public readonly ?string $route = null,
        public readonly ?string $route_url = null,
        public readonly string $source = 'VoucherInstructionsData',
        public readonly string $artifact_contract = 'x-change.claim-experience-preview.result.v1',
        public readonly bool $preview_cache = true,
        public readonly bool $money_movement = false,
        public readonly array $redactions = ['payloads' => 'not-loaded'],
    ) {}
}
