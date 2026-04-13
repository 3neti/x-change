<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Wallet;

use LBHurtado\XChange\Contracts\IssuerResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Wallet\GetWalletBalanceResultData;

class GetWalletBalance
{
    public function __construct(
        protected IssuerResolverContract $issuerResolver,
        protected WalletAccessContract $wallets,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): GetWalletBalanceResultData
    {
        $issuer = $this->issuerResolver->resolve($input);
        $wallet = $this->wallets->resolveForUser($issuer);
        $balance = $this->wallets->getBalance($wallet);

        return new GetWalletBalanceResultData(
            issuer_id: is_object($issuer) ? ($issuer->id ?? null) : data_get($issuer, 'id'),
            wallet_id: is_object($wallet) ? ($wallet->id ?? null) : data_get($wallet, 'id'),
            wallet_slug: is_object($wallet) ? ($wallet->slug ?? null) : data_get($wallet, 'slug'),
            wallet_name: is_object($wallet) ? ($wallet->name ?? null) : data_get($wallet, 'name'),
            balance: $balance,
            currency: (string) config('x-change.product.default_currency', 'PHP'),
        );
    }
}
