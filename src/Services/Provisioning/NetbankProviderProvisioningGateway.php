<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Provisioning;

use LBHurtado\EmiCore\Contracts\ProviderProvisioningGatewayFactory;
use LBHurtado\EmiCore\Contracts\ProviderSourceAccountReadiness;
use LBHurtado\XChange\Contracts\ProviderProvisioningGatewayContract;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use RuntimeException;

class NetbankProviderProvisioningGateway implements ProviderProvisioningGatewayContract
{
    public function __construct(
        protected WalletProvisioningContract $walletProvisioning,
        protected ?ProviderSourceAccountReadiness $source = null,
    ) {}

    public function supports(string $provider, string $mode): bool
    {
        return $this->factory()->make($this->source)->supports($provider, $mode);
    }

    public function provision(mixed $owner, array $payload): array
    {
        $factory = $this->factory();
        $mode = (string) data_get($payload, 'mode', 'bank_account_link');
        unset($payload['_local_wallet']);
        if ($factory->requiresLocalWallet($mode)) {
            $payload['_local_wallet'] = $this->walletProvisioning->open($owner, [
                'wallet' => [
                    'slug' => data_get($payload, 'wallet.slug', config('x-change.onboarding.default_wallet_slug', 'platform')),
                    'name' => data_get($payload, 'wallet.name', config('x-change.onboarding.default_wallet_name', 'Platform Wallet')),
                ],
            ]);
        }

        return $factory->make($this->source)->provision($owner, $payload);
    }

    public function refresh(mixed $link): array
    {
        return $this->factory()->make($this->source)->refresh($link);
    }

    private function factory(): ProviderProvisioningGatewayFactory
    {
        $factories = collect(app()->tagged('emi.provider-provisioning-gateway-factories'))
            ->filter(fn (mixed $candidate): bool => $candidate instanceof ProviderProvisioningGatewayFactory
                && $candidate->providerCode() === 'netbank')
            ->uniqueStrict();
        if ($factories->count() !== 1) {
            throw new RuntimeException('A unique provider provisioning gateway factory is required.');
        }

        return $factories->first();
    }
}
