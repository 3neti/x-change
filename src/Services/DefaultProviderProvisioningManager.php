<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\ProviderAccountLinkRepositoryContract;
use LBHurtado\XChange\Contracts\ProviderProvisioningGatewayContract;
use LBHurtado\XChange\Contracts\ProviderProvisioningManagerContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;
use RuntimeException;

class DefaultProviderProvisioningManager implements ProviderProvisioningManagerContract
{
    public function __construct(
        protected ProviderRuntimeSettingsResolverContract $settings,
        protected ProviderProvisioningGatewayContract $gateway,
        protected ProviderAccountLinkRepositoryContract $links,
    ) {}

    public function startOrResume(mixed $owner, array $payload): array
    {
        $provider = $this->settings->provider(data_get($payload, 'provider'));

        if (! $this->settings->isEnabled($provider)) {
            throw new RuntimeException("Provider [{$provider}] is disabled.");
        }

        $mode = (string) data_get($payload, 'mode', $this->defaultMode($provider));
        $topology = $this->settings->topology($provider);

        if (! $this->gateway->supports($provider, $mode)) {
            throw new RuntimeException("Provider [{$provider}] does not support provisioning mode [{$mode}].");
        }

        $result = $this->gateway->provision($owner, [
            ...$payload,
            'provider' => $provider,
            'mode' => $mode,
            'topology' => $topology,
        ]);

        $link = $this->links->storeFromProvisioningResult($owner, $result);

        return [
            'provider' => $provider,
            'topology' => $topology,
            'mode' => $mode,
            'status' => $link->status,
            'link_id' => $link->getKey(),
            'ready' => $link->isReady(),
            'link' => $link->toArray(),
        ];
    }

    protected function defaultMode(string $provider): string
    {
        return match ($provider) {
            'paynamics' => ProviderProvisioningMode::WalletCreate->value,
            'netbank' => ProviderProvisioningMode::BankAccountLink->value,
            default => ProviderProvisioningMode::LedgerWallet->value,
        };
    }
}
