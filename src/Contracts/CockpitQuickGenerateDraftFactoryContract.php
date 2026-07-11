<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;

interface CockpitQuickGenerateDraftFactoryContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function fromPayload(
        array $payload,
        ?string $idempotencyKey = null,
        ?string $correlationId = null,
    ): CockpitIssuanceDraftData;
}
