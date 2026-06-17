<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\ProviderAccountLinkRepositoryContract;
use LBHurtado\XChange\Contracts\ProviderReadinessGuardContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Data\ProviderReadinessData;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;

class DefaultProviderReadinessGuard implements ProviderReadinessGuardContract
{
    public function __construct(
        protected ProviderRuntimeSettingsResolverContract $settings,
        protected ProviderAccountLinkRepositoryContract $links,
    ) {}

    public function evaluateIssuer(mixed $owner, ?string $provider = null, array $context = []): ProviderReadinessData
    {
        $provider = $this->settings->provider($provider);
        $topology = $this->settings->topology($provider);

        if (! $this->settings->isEnabled($provider)) {
            return ProviderReadinessData::blocked(
                provider: $provider,
                topology: $topology,
                mode: null,
                reason: "Provider [{$provider}] is disabled.",
                missing: ['provider_enabled'],
            );
        }

        if ($topology !== 'provider_customer_wallet') {
            return ProviderReadinessData::ready(
                provider: $provider,
                topology: $topology,
                mode: $this->issuerMode($provider, $topology, $context),
                meta: ['source' => 'topology_does_not_require_customer_wallet'],
            );
        }

        $mode = $this->issuerMode($provider, $topology, $context);
        $link = $this->links->findReadyForOwner($owner, $provider, $mode);

        if ($link === null) {
            return ProviderReadinessData::blocked(
                provider: $provider,
                topology: $topology,
                mode: $mode,
                reason: 'Issuer provider customer wallet is not ready.',
                missing: ['provider_customer_wallet'],
            );
        }

        return ProviderReadinessData::ready(
            provider: $provider,
            topology: $topology,
            mode: $mode,
            linkId: (int) $link->getKey(),
            meta: [
                'provider_wallet_id' => $link->provider_wallet_id,
            ],
        );
    }

    public function evaluateClaimant(mixed $owner, ?string $provider = null, array $context = []): ProviderReadinessData
    {
        $provider = $this->settings->provider($provider);
        $topology = $this->settings->topology($provider);

        if (! $this->settings->isEnabled($provider)) {
            return ProviderReadinessData::blocked(
                provider: $provider,
                topology: $topology,
                mode: null,
                reason: "Provider [{$provider}] is disabled.",
                missing: ['provider_enabled'],
            );
        }

        $requiresBankAccount = (bool) data_get($context, 'requires_bank_account', false);

        if (! $requiresBankAccount) {
            return ProviderReadinessData::ready(
                provider: $provider,
                topology: $topology,
                mode: null,
                meta: ['source' => 'bank_account_not_required'],
            );
        }

        $mode = ProviderProvisioningMode::BankAccountLink->value;
        $link = $this->links->findReadyForOwner($owner, $provider, $mode);

        if ($link === null) {
            return ProviderReadinessData::blocked(
                provider: $provider,
                topology: $topology,
                mode: $mode,
                reason: 'Claimant bank-account readiness is required.',
                missing: ['bank_account_link'],
            );
        }

        return ProviderReadinessData::ready(
            provider: $provider,
            topology: $topology,
            mode: $mode,
            linkId: (int) $link->getKey(),
            meta: [
                'provider_bank_account_id' => $link->provider_bank_account_id,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function issuerMode(string $provider, string $topology, array $context): string
    {
        $mode = data_get($context, 'mode');

        if (is_string($mode) && $mode !== '') {
            return $mode;
        }

        return $topology === 'provider_customer_wallet' || $provider === 'paynamics'
            ? ProviderProvisioningMode::WalletCreate->value
            : ProviderProvisioningMode::LedgerWallet->value;
    }
}
