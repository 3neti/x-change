<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\SettlementRailCapabilityRegistryContract;
use RuntimeException;

final class ConfigSettlementRailCapabilityRegistry implements SettlementRailCapabilityRegistryContract
{
    public function __construct(
        private readonly ProviderRuntimeSettingsResolverContract $settings,
    ) {}

    public function sanitized(): array
    {
        $provider = $this->provider();
        $bindingProvider = $this->payoutProviderCode();
        $bindingCoherent = $bindingProvider === null || $bindingProvider === $provider;

        return [
            'schema' => 'x-change.cockpit.settlement-rail-capabilities.v1',
            'provider' => [
                'code' => $provider,
                'label' => $this->providerLabel($provider),
                'enabled' => $this->settings->isEnabled($provider),
                'binding_provider' => $bindingProvider,
                'binding_coherent' => $bindingCoherent,
            ],
            'connection_reference' => $this->connectionReference($provider),
            'default_mode' => 'automatic',
            'automatic_policy' => [
                'instapay_below_amount_minor' => $this->automaticThresholdMinor(),
                'resolved_per_payout' => true,
            ],
            'rails' => array_map(
                fn (SettlementRail $rail): array => $this->railData($provider, $rail),
                SettlementRail::cases(),
            ),
            'source' => 'configured-provider-capabilities',
            'live_provider_call' => false,
        ];
    }

    public function rail(SettlementRail $rail): ?array
    {
        return $this->railData($this->provider(), $rail);
    }

    public function assertSupports(PayoutRequestData $request): void
    {
        $rail = SettlementRail::tryFrom((string) $request->settlement_rail);

        if ($rail === null) {
            throw new RuntimeException('The payout settlement rail is not supported.');
        }

        $capability = $this->rail($rail);

        if (($capability['enabled'] ?? false) !== true) {
            throw new RuntimeException((string) ($capability['availability_reason'] ?? 'The payout settlement rail is unavailable.'));
        }

        $amountMinor = (int) round(((float) $request->amount) * 100);
        $minimumMinor = $capability['minimum_amount_minor'] ?? null;
        $maximumMinor = $capability['maximum_amount_minor'] ?? null;

        if (is_int($minimumMinor) && $amountMinor < $minimumMinor) {
            throw new RuntimeException(sprintf(
                '%s requires at least PHP %.2f.',
                $capability['label'],
                $minimumMinor / 100,
            ));
        }

        if (is_int($maximumMinor) && $amountMinor > $maximumMinor) {
            throw new RuntimeException(sprintf(
                '%s permits at most PHP %.2f.',
                $capability['label'],
                $maximumMinor / 100,
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function railData(string $provider, SettlementRail $rail): array
    {
        $providerEnabled = $this->settings->isEnabled($provider);
        $bindingProvider = $this->payoutProviderCode();
        $bindingCoherent = $bindingProvider === null || $bindingProvider === $provider;
        $configuration = $this->railConfiguration($provider, $rail);
        $configured = $configuration !== [];
        $railEnabled = $configured && (bool) ($configuration['enabled'] ?? false);
        $enabled = $providerEnabled && $bindingCoherent && $railEnabled;

        return [
            'code' => $rail->value,
            'label' => match ($rail) {
                SettlementRail::INSTAPAY => 'InstaPay',
                SettlementRail::PESONET => 'PESONet',
            },
            'enabled' => $enabled,
            'currency' => 'PHP',
            'minimum_amount_minor' => $this->nullableInteger($configuration['min_amount'] ?? null),
            'maximum_amount_minor' => $this->nullableInteger($configuration['max_amount'] ?? null),
            'provider_fee_minor' => $this->nullableInteger($configuration['fee'] ?? null),
            'availability_reason' => match (true) {
                ! $providerEnabled => sprintf('%s payouts are disabled.', $this->providerLabel($provider)),
                ! $bindingCoherent => sprintf(
                    'Payout readiness resolves to %s but the payout adapter resolves to %s.',
                    $this->providerLabel($provider),
                    $this->providerLabel((string) $bindingProvider),
                ),
                ! $configured => sprintf('%s does not publish %s capability metadata.', $this->providerLabel($provider), $rail->value),
                ! $railEnabled => sprintf('%s is disabled for %s.', $this->providerLabel($provider), $rail->value),
                default => null,
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function railConfiguration(string $provider, SettlementRail $rail): array
    {
        return match ($provider) {
            'netbank' => (array) config("omnipay.gateways.netbank.options.rails.{$rail->value}", []),
            default => [],
        };
    }

    private function provider(): string
    {
        $provider = strtolower($this->settings->provider());

        return match ($provider) {
            'paynamics_constellation' => 'paynamics',
            default => $provider,
        };
    }

    private function providerLabel(string $provider): string
    {
        return match ($provider) {
            'netbank' => 'NetBank',
            'paynamics' => 'Paynamics',
            'manual' => 'Manual',
            default => ucfirst($provider),
        };
    }

    private function payoutProviderCode(): ?string
    {
        $provider = config('x-change.payout.provider');

        if (! is_string($provider) || trim($provider) === '') {
            return null;
        }

        $provider = strtolower($provider);

        return match (true) {
            str_contains($provider, 'netbank') => 'netbank',
            str_contains($provider, 'paynamics'), str_contains($provider, 'constellation') => 'paynamics',
            default => null,
        };
    }

    private function connectionReference(string $provider): ?string
    {
        foreach ((array) config('x-change.treasury.connections', []) as $reference => $connection) {
            if (! is_array($connection) || ($connection['mode'] ?? 'disabled') === 'disabled') {
                continue;
            }

            $connectionProvider = strtolower((string) ($connection['provider'] ?? ''));

            if ($connectionProvider === $provider || str_starts_with($connectionProvider, $provider)) {
                return (string) $reference;
            }
        }

        return null;
    }

    private function automaticThresholdMinor(): int
    {
        return max(1, (int) config('x-change.payout.automatic_rail_threshold_minor', 5_000_000));
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
