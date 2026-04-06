<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Onboarding;

use LBHurtado\XChange\Contracts\UserResolverContract;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;

class OpenIssuerWallet
{
    public function __construct(
        protected UserResolverContract $users,
        protected WalletProvisioningContract $wallets,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input): array
    {
        $issuer = $this->users->resolve($input);
        $wallet = $this->wallets->open($issuer, $input);

        return [
            'issuer' => [
                'id' => is_object($issuer) ? ($issuer->id ?? null) : data_get($issuer, 'id'),
            ],
            'wallet' => [
                'id' => is_object($wallet) ? ($wallet->id ?? null) : data_get($wallet, 'id'),
                'slug' => is_object($wallet) ? ($wallet->slug ?? null) : data_get($wallet, 'slug'),
                'name' => is_object($wallet) ? ($wallet->name ?? null) : data_get($wallet, 'name'),
                'balance' => is_object($wallet) ? ($wallet->balance ?? null) : data_get($wallet, 'balance'),
            ],
        ];
    }
}
