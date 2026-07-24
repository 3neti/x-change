<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\Wallet\Treasury\Enums\TreasuryCustodyMode;
use LBHurtado\XChange\Enums\TreasuryConnectionMode;

final readonly class TreasuryProviderConnectionData
{
    /**
     * @param  list<ProviderCapability>  $requiredCapabilities
     */
    public function __construct(
        public string $reference,
        public string $provider,
        public TreasuryConnectionMode $mode,
        public string $currency,
        public int $decimalPlaces,
        public string $settlementResourceReference,
        public string $settlementResourceType,
        public TreasuryCustodyMode $custodyMode,
        public array $requiredCapabilities,
    ) {}

    public function isActive(): bool
    {
        return $this->mode !== TreasuryConnectionMode::Disabled;
    }

    public function isRequired(): bool
    {
        return $this->mode === TreasuryConnectionMode::Required;
    }
}
