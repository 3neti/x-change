<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PayCode;

use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\UserResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Exceptions\PayCodeIssuerNotResolved;
use LBHurtado\XChange\Services\InstructionRevenueAllocatorService;
use RuntimeException;

class GeneratePayCode
{
    public function __construct(
        protected UserResolverContract $users,
        protected WalletAccessContract $wallets,
        protected EstimatePayCodeCost $estimatePayCodeCost,
        protected PayCodeIssuanceContract $issuance,
        protected InstructionRevenueAllocatorService $allocator,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): GeneratePayCodeResultData
    {
        $issuer = $this->users->resolve($input);

        if (! $issuer) {
            throw new PayCodeIssuerNotResolved('Unable to resolve Pay Code issuer.');
        }

        $wallet = $this->wallets->resolveForUser($issuer);
        $estimate = $this->estimatePayCodeCost->handle($input);

        return DB::transaction(function () use ($issuer, $wallet, $input, $estimate): GeneratePayCodeResultData {
            $balanceBefore = $this->wallets->getBalance($wallet);

            $this->wallets->assertCanAfford($wallet, $estimate->total);

            $allocation = $this->allocator->allocate(
                issuer: $this->assertWalletableIssuer($issuer),
                estimate: $estimate,
                context: $this->buildAllocationContext($input, $estimate),
            );

            $issued = $this->issuance->issue($issuer, $input);

            $balanceAfter = $this->wallets->getBalance($wallet);

            return new GeneratePayCodeResultData(
                voucher_id: $issued['voucher_id'],
                code: (string) $issued['code'],
                amount: $issued['amount'],
                currency: (string) $issued['currency'],
                issuer: new IssuerData(
                    id: is_object($issuer) ? ($issuer->id ?? null) : data_get($issuer, 'id'),
                ),
                cost: $estimate,
                wallet: [
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                ],
                debit: $this->normalizeDebit($allocation['debit'] ?? null),
                links: new PayCodeLinksData(
                    redeem: (string) data_get($issued, 'links.redeem'),
                    redeem_path: (string) data_get($issued, 'links.redeem_path'),
                ),
                allocations: $allocation['allocations'] ?? [],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function buildAllocationContext(array $input, PricingEstimateData $estimate): array
    {
        return [
            'requested_amount' => data_get($input, 'cash.amount'),
            'requested_currency' => data_get($input, 'cash.currency'),
            'idempotency_key' => data_get($input, '_meta.idempotency_key'),
            'correlation_id' => data_get($input, '_meta.correlation_id'),
            'cost' => [
                'currency' => $estimate->currency,
                'base_fee' => $estimate->base_fee,
                'components' => $estimate->components,
                'total' => $estimate->total,
                'charges' => $estimate->charges,
            ],
        ];
    }

    /**
     * @return object&Wallet
     */
    protected function assertWalletableIssuer(mixed $issuer): object
    {
        if (! is_object($issuer) || ! $issuer instanceof Wallet) {
            throw new RuntimeException('Resolved issuer is not wallet-enabled.');
        }

        return $issuer;
    }

    protected function normalizeDebit(mixed $debit): DebitData
    {
        if (is_object($debit)) {
            return new DebitData(
                id: $debit->id ?? null,
                amount: $debit->amount ?? null,
            );
        }

        if (is_array($debit)) {
            return new DebitData(
                id: $debit['id'] ?? null,
                amount: $debit['amount'] ?? null,
            );
        }

        return new DebitData;
    }
}
