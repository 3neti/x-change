<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Onboarding;

use LBHurtado\XChange\Contracts\IssuerResolverContract;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\Onboarding\OpenIssuerWalletResultData;
use LBHurtado\XChange\Data\WalletData;
use RuntimeException;

class OpenIssuerWallet
{
    public function __construct(
        protected IssuerResolverContract $issuerResolver,
        protected WalletProvisioningContract $walletProvisioning,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): OpenIssuerWalletResultData
    {
        $issuer = $this->issuerResolver->resolve($input);

        if (! $issuer) {
            throw new RuntimeException('Issuer could not be resolved.');
        }

        $wallet = $this->walletProvisioning->open($issuer, $input);

        return new OpenIssuerWalletResultData(
            issuer: new IssuerData(
                id: is_object($issuer) ? ($issuer->id ?? null) : data_get($issuer, 'id'),
            ),
            wallet: new WalletData(
                id: is_object($wallet) ? ($wallet->id ?? null) : data_get($wallet, 'id'),
                slug: is_object($wallet) ? ($wallet->slug ?? null) : data_get($wallet, 'slug'),
                name: is_object($wallet) ? ($wallet->name ?? null) : data_get($wallet, 'name'),
                balance: is_object($wallet) ? ($wallet->balance ?? null) : data_get($wallet, 'balance'),
            ),
        );
    }
}
