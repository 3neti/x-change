<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\Wallet\Treasury\Enums\TreasuryCustodyMode;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Enums\TreasuryConnectionMode;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;

final readonly class TreasuryProviderConnectionCatalog
{
    /**
     * @param  array<string, array<string, mixed>>  $connections
     */
    public function __construct(
        private array $connections,
    ) {}

    /**
     * @return list<TreasuryProviderConnectionData>
     */
    public function all(): array
    {
        $connections = [];

        foreach ($this->connections as $reference => $configuration) {
            $connections[] = $this->connection((string) $reference, $configuration);
        }

        usort(
            $connections,
            static fn (TreasuryProviderConnectionData $left, TreasuryProviderConnectionData $right): int => $left->reference <=> $right->reference,
        );

        return $connections;
    }

    /**
     * @param  list<string>  $references
     * @return list<TreasuryProviderConnectionData>
     */
    public function active(array $references = []): array
    {
        $selected = array_values(array_filter(
            $this->all(),
            static fn (TreasuryProviderConnectionData $connection): bool => $connection->isActive()
                && ($references === [] || in_array($connection->reference, $references, true)),
        ));

        if ($references !== []) {
            $resolved = array_map(
                static fn (TreasuryProviderConnectionData $connection): string => $connection->reference,
                $selected,
            );
            $unknown = array_values(array_diff(array_unique($references), $resolved));

            if ($unknown !== []) {
                throw new TreasuryConfigurationException(
                    'Unknown or disabled Treasury connections: '.implode(', ', $unknown).'.',
                );
            }
        }

        return $selected;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function connection(
        string $reference,
        array $configuration,
    ): TreasuryProviderConnectionData {
        $provider = mb_strtolower(trim((string) ($configuration['provider'] ?? '')));
        $currency = mb_strtoupper(trim((string) ($configuration['currency'] ?? '')));

        if (preg_match('/^[a-z][a-z0-9_-]*$/', $provider) !== 1) {
            throw new TreasuryConfigurationException(
                "Treasury connection [{$reference}] has an invalid provider code.",
            );
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new TreasuryConfigurationException(
                "Treasury connection [{$reference}] has an invalid currency.",
            );
        }

        $decimalPlaces = (int) ($configuration['decimal_places'] ?? 2);

        if ($decimalPlaces < 0 || $decimalPlaces > 6) {
            throw new TreasuryConfigurationException(
                "Treasury connection [{$reference}] has invalid decimal places.",
            );
        }

        $capabilities = array_map(
            static fn (mixed $capability): ProviderCapability => ProviderCapability::tryFrom(
                trim((string) $capability),
            ) ?? throw new TreasuryConfigurationException(
                "Treasury connection [{$reference}] declares an unknown capability.",
            ),
            array_values((array) ($configuration['required_capabilities'] ?? [])),
        );

        return new TreasuryProviderConnectionData(
            reference: $this->requiredReference($reference, 'connection reference'),
            provider: $provider,
            mode: TreasuryConnectionMode::tryFrom(
                mb_strtolower(trim((string) ($configuration['mode'] ?? 'disabled'))),
            ) ?? throw new TreasuryConfigurationException(
                "Treasury connection [{$reference}] has an invalid mode.",
            ),
            currency: $currency,
            decimalPlaces: $decimalPlaces,
            settlementResourceReference: $this->requiredReference(
                (string) ($configuration['settlement_resource_reference'] ?? ''),
                "connection [{$reference}] Settlement Resource",
            ),
            settlementResourceType: $this->requiredReference(
                (string) ($configuration['settlement_resource_type'] ?? ''),
                "connection [{$reference}] Settlement Resource type",
            ),
            custodyMode: TreasuryCustodyMode::tryFrom(
                mb_strtolower(trim((string) ($configuration['custody_mode'] ?? ''))),
            ) ?? throw new TreasuryConfigurationException(
                "Treasury connection [{$reference}] has an invalid custody mode.",
            ),
            requiredCapabilities: array_values(array_unique($capabilities, SORT_REGULAR)),
        );
    }

    private function requiredReference(string $value, string $name): string
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > 191) {
            throw new TreasuryConfigurationException("Treasury {$name} is invalid.");
        }

        return $value;
    }
}
