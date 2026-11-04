<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Provisioning;

use LBHurtado\EmiCore\Contracts\ProviderProvisioningGateway;
use LBHurtado\XChange\Contracts\ProviderProvisioningGatewayContract;
use RuntimeException;

class PaynamicsProviderProvisioningGateway implements ProviderProvisioningGatewayContract
{
    public function supports(string $provider, string $mode): bool
    {
        if (strtolower($provider) !== 'paynamics') {
            return false;
        }

        return $this->gateway()->supports($provider, $mode);
    }

    public function provision(mixed $owner, array $payload): array
    {
        return $this->gateway()->provision($owner, $payload);
    }

    public function refresh(mixed $link): array
    {
        return $this->gateway()->refresh($link);
    }

    private function gateway(): ProviderProvisioningGateway
    {
        $gateways = collect(app()->tagged('emi.provider-provisioning-gateways'))
            ->filter(fn (mixed $gateway): bool => $gateway instanceof ProviderProvisioningGateway
                && $gateway->providerCode() === 'paynamics');

        if ($gateways->count() !== 1) {
            throw new RuntimeException('A unique provider provisioning gateway is required.');
        }

        return $gateways->first();
    }
}
