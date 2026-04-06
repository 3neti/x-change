<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PayCode;

use Illuminate\Support\Facades\DB;
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
    ) {}

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

        return DB::transaction(function () use ($issuer, $wallet, $input, $estimate): array {
            $balanceBefore = $this->wallets->getBalance($wallet);

            $this->wallets->assertCanAfford($wallet, $estimate['total']);

            $debit = $this->wallets->debit(
                $wallet,
                $estimate['total'],
                $this->buildDebitMetadata($input, $estimate),
            );

            $issued = $this->issuance->issue($issuer, $input);

            $balanceAfter = $this->wallets->getBalance($wallet);

            return array_merge($issued, [
                'issuer' => [
                    'id' => is_object($issuer) ? ($issuer->id ?? null) : null,
                ],
                'cost' => $estimate,
                'wallet' => [
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                ],
                'debit' => $this->normalizeDebit($debit),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $estimate
     * @return array<string, mixed>
     */
    protected function buildDebitMetadata(array $input, array $estimate): array
    {
        return [
            'reason' => 'pay_code_issuance',
            'requested_amount' => data_get($input, 'cash.amount'),
            'requested_currency' => data_get($input, 'cash.currency'),
            'cost' => [
                'currency' => $estimate['currency'] ?? null,
                'base_fee' => $estimate['base_fee'] ?? null,
                'components' => $estimate['components'] ?? [],
                'total' => $estimate['total'] ?? null,
            ],
            'idempotency_key' => data_get($input, '_meta.idempotency_key'),
            'correlation_id' => data_get($input, '_meta.correlation_id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeDebit(mixed $debit): array
    {
        if (is_object($debit)) {
            return [
                'id' => $debit->id ?? null,
                'amount' => $debit->amount ?? null,
            ];
        }

        if (is_array($debit)) {
            return [
                'id' => $debit['id'] ?? null,
                'amount' => $debit['amount'] ?? null,
            ];
        }

        return [
            'id' => null,
            'amount' => null,
        ];
    }
}
