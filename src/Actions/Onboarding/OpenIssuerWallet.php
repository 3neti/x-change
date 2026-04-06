<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Onboarding;

use LBHurtado\XChange\Contracts\IssuerResolverContract;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use RuntimeException;

class OpenIssuerWallet
{
    public function __construct(
        protected IssuerResolverContract $issuerResolver,
        protected WalletProvisioningContract $walletProvisioning,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input): array
    {
        $issuer = $this->issuerResolver->resolve($input);

        if (! $issuer) {
            throw new RuntimeException('Issuer could not be resolved.');
        }

        $wallet = $this->walletProvisioning->open($issuer, $input);

        return $this->transform($issuer, $wallet);
    }

    protected function transform($issuer, $wallet): array
    {
        return [
            'issuer' => [
                'id' => $issuer->id,
            ],

            'wallet' => [
                'id' => $wallet->id,
                'slug' => $wallet->slug,
                'name' => $wallet->name,
                'balance' => (float) $wallet->balance,
            ],
        ];
    }
}
