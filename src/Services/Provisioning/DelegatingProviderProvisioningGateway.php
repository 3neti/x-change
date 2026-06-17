<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Provisioning;

use Illuminate\Contracts\Foundation\Application;
use LBHurtado\XChange\Contracts\ProviderProvisioningGatewayContract;
use RuntimeException;

class DelegatingProviderProvisioningGateway implements ProviderProvisioningGatewayContract
{
    /**
     * @var array<string, ProviderProvisioningGatewayContract>
     */
    protected array $delegates = [];

    public function __construct(
        protected Application $app,
    ) {}

    public function supports(string $provider, string $mode): bool
    {
        return $this->delegateFor($provider)->supports($provider, $mode);
    }

    public function provision(mixed $owner, array $payload): array
    {
        $provider = strtolower((string) data_get($payload, 'provider', 'manual'));

        return $this->delegateFor($provider)->provision($owner, $payload);
    }

    public function refresh(mixed $link): array
    {
        $provider = strtolower((string) data_get($link, 'provider', 'manual'));

        return $this->delegateFor($provider)->refresh($link);
    }

    protected function delegateFor(string $provider): ProviderProvisioningGatewayContract
    {
        $provider = strtolower($provider);

        if (array_key_exists($provider, $this->delegates)) {
            return $this->delegates[$provider];
        }

        $class = config(
            "x-change.provider_runtime.providers.{$provider}.provisioning_gateway",
            config('x-change.provider_runtime.default_provisioning_gateway', FakeProviderProvisioningGateway::class),
        );

        $delegate = $this->app->make($class);

        if (! $delegate instanceof ProviderProvisioningGatewayContract) {
            throw new RuntimeException(sprintf(
                'Configured provisioning gateway [%s] for provider [%s] must implement %s.',
                is_object($delegate) ? $delegate::class : get_debug_type($delegate),
                $provider,
                ProviderProvisioningGatewayContract::class,
            ));
        }

        return $this->delegates[$provider] = $delegate;
    }
}
