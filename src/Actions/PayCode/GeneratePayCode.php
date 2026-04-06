<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PayCode;

use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\UserResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Exceptions\PayCodeIssuerNotResolved;

class GeneratePayCode
{
    public function __construct(
        protected UserResolverContract $users,
        protected WalletAccessContract $wallets,
        protected EstimatePayCodeCost $estimatePayCodeCost,
        protected PayCodeIssuanceContract $issuance,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input): array
    {
        $issuer = $this->users->resolve($input);

        if (! $issuer) {
            throw new PayCodeIssuerNotResolved('Unable to resolve Pay Code issuer.');
        }

        $wallet = $this->wallets->resolveForUser($issuer);

        $estimate = $this->estimatePayCodeCost->handle($input);

        $balanceBefore = $this->wallets->getBalance($wallet);

        $this->wallets->assertCanAfford($wallet, $estimate['total']);

        $debit = $this->wallets->debit($wallet, $estimate['total'], [
            'reason' => 'pay_code_issuance',
            'cost' => $estimate,
        ]);

        $issued = $this->issuance->issue($issuer, $input);

        $balanceAfter = $this->wallets->getBalance($wallet);

        return array_merge($issued, [
            'cost' => $estimate,
            'wallet' => [
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ],
            'debit' => $debit,
        ]);
    }
}
