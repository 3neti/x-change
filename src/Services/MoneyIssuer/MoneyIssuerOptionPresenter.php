<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\MoneyIssuer;

use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\MoneyIssuer\Contracts\MoneyIssuerDirectoryContract;
use LBHurtado\MoneyIssuer\Data\MoneyIssuerData;

final readonly class MoneyIssuerOptionPresenter
{
    public function __construct(
        private MoneyIssuerDirectoryContract $institutions,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forRail(string $rail): array
    {
        $settlementRail = SettlementRail::tryFrom(strtoupper(trim($rail)))
            ?? SettlementRail::INSTAPAY;

        return $this->institutions
            ->directory($settlementRail)
            ->map(fn (MoneyIssuerData $institution): array => [
                'key' => $institution->key,
                'value' => $institution->routingCodes[$settlementRail->value],
                'name' => $institution->name,
                'short_name' => $institution->shortName,
                'category' => $institution->category,
                'account_label' => $institution->accountLabel,
                'identifier_scheme' => $institution->identifierScheme,
                'aliases' => $institution->aliases,
                'commonly_used' => $institution->commonlyUsed,
            ])
            ->values()
            ->all();
    }
}
