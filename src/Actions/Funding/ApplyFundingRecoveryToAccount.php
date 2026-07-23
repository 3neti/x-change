<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\FundingAccountRecoveryContract;
use LBHurtado\XChange\Models\FundingAccountHold;
use LBHurtado\XChange\Models\FundingRecovery;
use LBHurtado\XChange\Models\FundingRecoveryPayment;
use LBHurtado\XChange\Models\FundingSettlement;

class ApplyFundingRecoveryToAccount
{
    public function __construct(
        private readonly FundingAccountRecoveryContract $accounts,
    ) {}

    public function handle(
        string $accountReference,
        object $account,
        FundingSettlement $settlement,
    ): int {
        return DB::transaction(function () use ($accountReference, $account, $settlement): int {
            $remainingAmountMinor = $settlement->net_amount_minor;
            $appliedAmountMinor = 0;
            $holds = FundingAccountHold::query()
                ->where('account_reference', $accountReference)
                ->where('status', 'active')
                ->oldest('id')
                ->lockForUpdate()
                ->get();

            foreach ($holds as $hold) {
                if ($remainingAmountMinor <= 0) {
                    break;
                }

                $recovery = FundingRecovery::query()
                    ->whereKey($hold->funding_recovery_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $amountMinor = min($remainingAmountMinor, $recovery->outstanding_amount_minor);
                $accountRecovery = $this->accounts->recover($account, $amountMinor, [
                    'source' => 'funding_recovery_payment',
                    'funding_recovery_reference' => $recovery->reference,
                    'funding_settlement_id' => $settlement->getKey(),
                ]);

                if ($accountRecovery->recoveredAmountMinor !== $amountMinor
                    || $accountRecovery->outstandingAmountMinor !== 0
                    || $accountRecovery->walletTransactionId === null
                    || $accountRecovery->walletTransactionUuid === null) {
                    throw new \LogicException('The Account could not apply its funded recovery amount atomically.');
                }

                FundingRecoveryPayment::query()->create([
                    'funding_recovery_id' => $recovery->getKey(),
                    'funding_settlement_id' => $settlement->getKey(),
                    'amount_minor' => $amountMinor,
                    'currency' => $recovery->currency,
                    'wallet_transaction_id' => $accountRecovery->walletTransactionId,
                    'wallet_transaction_uuid' => $accountRecovery->walletTransactionUuid,
                    'paid_at' => now(),
                    'metadata' => [
                        'source' => 'verified_provider_funding',
                    ],
                ]);

                $outstandingAmountMinor = $recovery->outstanding_amount_minor - $amountMinor;
                $recovery->forceFill([
                    'recovered_amount_minor' => $recovery->recovered_amount_minor + $amountMinor,
                    'outstanding_amount_minor' => $outstandingAmountMinor,
                    'status' => $outstandingAmountMinor === 0 ? 'recovered' : 'open',
                    'recovered_at' => $outstandingAmountMinor === 0 ? now() : null,
                ])->saveQuietly();
                $hold->forceFill([
                    'outstanding_amount_minor' => $outstandingAmountMinor,
                    'status' => $outstandingAmountMinor === 0 ? 'released' : 'active',
                    'released_at' => $outstandingAmountMinor === 0 ? now() : null,
                    'released_by_type' => $outstandingAmountMinor === 0 ? 'funding_recovery_runtime' : null,
                    'released_by_id' => $outstandingAmountMinor === 0 ? 'automatic' : null,
                ])->saveQuietly();

                $remainingAmountMinor -= $amountMinor;
                $appliedAmountMinor += $amountMinor;
            }

            return $appliedAmountMinor;
        }, attempts: 3);
    }
}
