<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReleaseData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Events\FundingProjectionChanged;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Funding\PayCodeFundingEligibility;
use RuntimeException;

final readonly class TransferPayCodeIntoAccount
{
    public function __construct(
        private PayCodeFundingEligibility $eligibility,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryPositionOperationContract $operations,
    ) {}

    public function handle(
        Voucher $voucher,
        Authenticatable&Model $claimant,
    ): VoucherClaim {
        return DB::transaction(function () use (
            $claimant,
            $voucher,
        ): VoucherClaim {
            $locked = Voucher::query()
                ->with('owner')
                ->lockForUpdate()
                ->findOrFail($voucher->getKey());
            $scope = hash('sha256', 'pay-code-account-funding|'.$locked->getKey());
            $existing = VoucherClaim::query()
                ->where('account_funding_scope', $scope)
                ->first();

            if ($existing instanceof VoucherClaim) {
                return $existing;
            }

            $decision = $this->eligibility->evaluate($locked);

            if (
                ! $decision->eligible
                || $decision->amountMinor === null
                || $decision->currency === null
                || $decision->connectionReference === null
                || $decision->reservationOperationReference === null
            ) {
                throw new RuntimeException($decision->message);
            }

            $issuer = $locked->owner;

            if (! $issuer instanceof Model) {
                throw new RuntimeException('The Pay Code issuer is unavailable.');
            }

            $issuerPortfolio = $this->portfolios->provision(
                $issuer,
                [$decision->connectionReference],
            );
            $claimantPortfolio = $this->portfolios->provision(
                $claimant,
                [$decision->connectionReference],
            );
            $source = $this->position(
                $issuerPortfolio->positions,
                TreasuryPositionPurpose::PayCodeReserve,
            );
            $destination = $this->position(
                $claimantPortfolio->positions,
                TreasuryPositionPurpose::ClientFunds,
            );
            $operationReference = 'pay-code-account-funding:'.$scope;
            $release = $this->operations->release(
                new TreasuryPositionReleaseData(
                    operationReference: $operationReference,
                    sourcePositionReference: $source->positionReference,
                    destinationPositionReference: $destination->positionReference,
                    amountMinor: $decision->amountMinor,
                    currency: $decision->currency,
                    idempotencyKey: 'pay-code-account-funding-key:'.$scope,
                    externalReference: $decision->reservationOperationReference,
                    metadata: [
                        'source' => 'x_change_pay_code_account_funding',
                        'pay_code_id' => (int) $locked->getKey(),
                        'purpose' => 'account_funding',
                        'provider_calls' => false,
                        'provider_inventory_changed' => false,
                    ],
                ),
            );
            $claim = VoucherClaim::query()->create([
                'voucher_id' => $locked->getKey(),
                'claim_number' => (int) $locked->claims()->max('claim_number') + 1,
                'claim_type' => 'account_funding',
                'settlement_mode' => 'account_funding',
                'status' => 'succeeded',
                'requested_amount_minor' => $decision->amountMinor,
                'disbursed_amount_minor' => $decision->amountMinor,
                'remaining_balance_minor' => 0,
                'currency' => $decision->currency,
                'idempotency_key' => 'pay-code-account-funding-key:'.$scope,
                'reference' => $operationReference,
                'claimant_type' => $claimant::class,
                'claimant_id' => (string) $claimant->getKey(),
                'account_funding_scope' => $scope,
                'treasury_operation_reference' => $release->operationReference,
                'attempted_at' => now(),
                'completed_at' => now(),
                'meta' => [
                    'settlement_destination' => 'account_funding',
                    'provider_calls' => false,
                    'provider_inventory_changed' => false,
                    'reservation_operation_reference' => $decision->reservationOperationReference,
                ],
            ]);
            $locked->forceFill([
                'redeemed_at' => now(),
            ])->saveQuietly();

            DB::afterCommit(static fn () => FundingProjectionChanged::dispatch(
                $claimant::class,
                (string) $claimant->getKey(),
                $operationReference,
                now()->toIso8601String(),
            ));

            return $claim;
        }, attempts: 5);
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     */
    private function position(
        array $positions,
        TreasuryPositionPurpose $purpose,
    ): TreasuryPositionData {
        $matches = array_values(array_filter(
            $positions,
            static fn (TreasuryPositionData $position): bool => $position->purpose === $purpose
                && $position->status === 'active',
        ));

        if (count($matches) !== 1) {
            throw new RuntimeException(
                "Account Funding requires one active {$purpose->value} Treasury Position.",
            );
        }

        return $matches[0];
    }
}
