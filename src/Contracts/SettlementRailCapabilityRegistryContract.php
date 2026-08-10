<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\SettlementRail;

interface SettlementRailCapabilityRegistryContract
{
    /**
     * @return array<string, mixed>
     */
    public function sanitized(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function rail(SettlementRail $rail): ?array;

    public function assertSupports(PayoutRequestData $request): void;
}
