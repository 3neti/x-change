<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\DispatchVoucherClaimOutcome;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Models\VoucherClaim;
use RuntimeException;

final readonly class ClaimReviewedFundingPayCode
{
    public function __construct(
        private DispatchVoucherClaimOutcome $outcomes,
    ) {}

    public function handle(
        FundingRequest $fundingRequest,
        Authenticatable&Model $claimant,
    ): VoucherClaim {
        return DB::transaction(function () use (
            $fundingRequest,
            $claimant,
        ): VoucherClaim {
            $locked = FundingRequest::query()
                ->with('voucher')
                ->lockForUpdate()
                ->findOrFail($fundingRequest->getKey());

            if (
                $locked->requester_type !== $claimant::class
                || $locked->requester_id !== (string) $claimant->getKey()
            ) {
                throw new RuntimeException(
                    'This Reviewed Funding Pay Code belongs to another Account.',
                );
            }

            if ($locked->status === FundingRequestStatus::Completed) {
                return $this->existingClaim($locked);
            }

            if (
                $locked->status !== FundingRequestStatus::PayCodeIssued
                || ! $locked->voucher instanceof Voucher
            ) {
                throw new RuntimeException(
                    'This Funding Request does not have a claimable Pay Code.',
                );
            }

            $claim = $this->outcomes->handle(
                voucher: $locked->voucher,
                requestedOutcome: 'account_funding',
                payload: [],
                claimant: $claimant,
            );

            if (! $claim instanceof VoucherClaim) {
                throw new RuntimeException(
                    'The Reviewed Funding Pay Code returned an unexpected result.',
                );
            }

            $nextVersion = $locked->version + 1;
            $locked->forceFill([
                'status' => FundingRequestStatus::Completed,
                'version' => $nextVersion,
                'completed_at' => now(),
            ])->saveQuietly();
            $locked->events()->create([
                'sequence' => $nextVersion,
                'event_type' => 'reviewed_funding_pay_code_claimed',
                'from_status' => FundingRequestStatus::PayCodeIssued,
                'to_status' => FundingRequestStatus::Completed,
                'actor_type' => $claimant::class,
                'actor_id' => (string) $claimant->getKey(),
                'metadata' => [
                    'pay_code_id' => (int) $locked->voucher->getKey(),
                    'voucher_claim_id' => (int) $claim->getKey(),
                    'treasury_operation_reference' => $claim->treasury_operation_reference,
                    'provider_calls' => false,
                ],
                'occurred_at' => now(),
            ]);

            return $claim;
        }, 5);
    }

    private function existingClaim(FundingRequest $fundingRequest): VoucherClaim
    {
        $claim = VoucherClaim::query()
            ->where('voucher_id', $fundingRequest->voucher_id)
            ->where('settlement_mode', 'account_funding')
            ->first();

        if (! $claim instanceof VoucherClaim) {
            throw new RuntimeException(
                'The completed Funding Request is missing its Voucher claim.',
            );
        }

        return $claim;
    }
}
