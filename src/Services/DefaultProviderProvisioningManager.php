<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Contracts\ProviderDefaultProvisioningMode;
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

        $mode = array_key_exists('mode', $payload)
            ? (string) $payload['mode']
            : $this->defaultMode($provider);
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
        $matches = [];
        foreach (['emi.provider-provisioning-profiles', 'emi.provider-provisioning-gateways'] as $tag) {
            foreach (app()->tagged($tag) as $candidate) {
                if ($candidate instanceof ProviderDefaultProvisioningMode && $candidate->providerCode() === $provider) {
                    $matches[] = $candidate;
                }
            }
        }
        if (count($matches) > 1) {
            throw new RuntimeException("Multiple default provisioning modes are registered for provider [{$provider}].");
        }

        return $matches === []
            ? (string) config("x-change.provider_runtime.providers.{$provider}.default_provisioning_mode", ProviderProvisioningMode::LedgerWallet->value)
            : $matches[0]->defaultProvisioningMode();
    }
}
