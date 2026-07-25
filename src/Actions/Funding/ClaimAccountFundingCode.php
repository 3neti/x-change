<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReleaseData;
use LBHurtado\XChange\Enums\AccountFundingCodeStatus;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\AccountFundingCode;
use LBHurtado\XChange\Models\FundingRequest;
use RuntimeException;

final readonly class ClaimAccountFundingCode
{
    public function __construct(
        private TreasuryPositionOperationContract $operations,
    ) {}

    public function handle(
        AccountFundingCode $fundingCode,
        string $claimantType,
        string $claimantId,
    ): AccountFundingCode {
        return DB::transaction(function () use (
            $fundingCode,
            $claimantType,
            $claimantId,
        ): AccountFundingCode {
            $locked = AccountFundingCode::query()
                ->lockForUpdate()
                ->findOrFail($fundingCode->getKey());

            if ($locked->status === AccountFundingCodeStatus::Claimed) {
                return $locked;
            }

            if (
                $locked->recipient_type !== $claimantType
                || $locked->recipient_id !== $claimantId
            ) {
                throw new RuntimeException('This Account Funding Code belongs to another Account.');
            }

            if (
                $locked->status !== AccountFundingCodeStatus::Issued
                || $locked->expires_at?->isPast() === true
            ) {
                throw new RuntimeException('This Account Funding Code is no longer claimable.');
            }

            $this->operations->release(new TreasuryPositionReleaseData(
                operationReference: $locked->claim_operation_reference,
                sourcePositionReference: $locked->reserve_position_reference,
                destinationPositionReference: $locked->destination_position_reference,
                amountMinor: $locked->amount_minor,
                currency: $locked->currency,
                idempotencyKey: 'account-funding-claim-key:'.hash(
                    'sha256',
                    $locked->claim_operation_reference,
                ),
                externalReference: $locked->reservation_operation_reference,
                metadata: [
                    'account_funding_code_reference' => $locked->reference,
                    'purpose' => 'account_funding',
                    'provider_calls' => false,
                ],
            ));
            $locked->forceFill([
                'status' => AccountFundingCodeStatus::Claimed,
                'version' => $locked->version + 1,
                'claimed_at' => now(),
            ])->saveQuietly();

            $request = FundingRequest::query()
                ->lockForUpdate()
                ->findOrFail($locked->funding_request_id);
            $nextVersion = $request->version + 1;
            $request->forceFill([
                'status' => FundingRequestStatus::Completed,
                'version' => $nextVersion,
                'completed_at' => now(),
            ])->saveQuietly();
            $request->events()->create([
                'sequence' => $nextVersion,
                'event_type' => 'account_funding_code_claimed',
                'from_status' => FundingRequestStatus::CodeIssued,
                'to_status' => FundingRequestStatus::Completed,
                'actor_type' => $claimantType,
                'actor_id' => $claimantId,
                'metadata' => [
                    'account_funding_code_reference' => $locked->reference,
                    'claim_operation_reference' => $locked->claim_operation_reference,
                    'provider_calls' => false,
                ],
                'occurred_at' => now(),
            ]);

            return $locked->refresh();
        }, 5);
    }
}
