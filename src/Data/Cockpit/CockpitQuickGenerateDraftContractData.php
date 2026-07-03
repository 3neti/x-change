<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitQuickGenerateDraftContractData extends Data
{
    /**
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.quick-generate-draft.v1',
        public readonly string $status = 'not_wired',
        public readonly ?string $template_key = null,
        public readonly int|float|string|null $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $recipient_reference = null,
        public readonly ?string $purpose = null,
        public readonly ?string $idempotency_key = null,
        public readonly array $redactions = ['payloads' => 'not-loaded'],
    ) {}
}
