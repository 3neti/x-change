<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

final readonly class SharedFundingTreasuryConnection
{
    public function __construct(private TreasuryProviderConnectionCatalog $connections) {}

    /** @param array<string, mixed> $snapshot */
    public function resolve(string $provider, string $currency, array $snapshot): TreasuryProviderConnectionData
    {
        $number = preg_replace('/\D+/', '', (string) ($snapshot['bankAccountNumber'] ?? ''));
        $configured = preg_replace('/\D+/', '', (string) config('payment-gateway.netbank.funding.corporate_account_number'));
        $treasury = config('x-change.funding.providers.netbank.treasury');
        if ($provider !== 'netbank' || $currency !== 'PHP' || ($snapshot['provider'] ?? null) !== $provider
            || ($snapshot['mode'] ?? null) !== 'shared' || $number === '' || $configured === ''
            || ! hash_equals($configured, $number) || ! is_array($treasury)) {
            throw FundingSettlementDenied::because('the shared funding destination is unavailable');
        }
        $matches = array_values(array_filter($this->connections->active(), fn (TreasuryProviderConnectionData $connection): bool => $connection->provider === $provider && $connection->currency === $currency
            && $connection->inventoryReference === ($treasury['inventory_reference'] ?? null)
            && $connection->settlementResourceReference === ($treasury['settlement_resource_reference'] ?? null)
            && $connection->settlementResourceType === ($treasury['resource_type'] ?? null)));
        if (count($matches) !== 1) {
            throw FundingSettlementDenied::because('the shared funding Treasury connection is unavailable');
        }

        return $matches[0];
    }
}
