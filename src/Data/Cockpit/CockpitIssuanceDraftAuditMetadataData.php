<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitIssuanceDraftAuditMetadataData extends Data
{
    /**
     * @param  array<string, mixed>  $safe
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.issuance-draft-audit.v1',
        public readonly string $status = 'safe',
        public readonly array $safe = [],
        public readonly array $redactions = [],
    ) {}
}
