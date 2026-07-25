<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Enums\AccountFundingCodeStatus;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\AccountFundingCode;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;

final readonly class ApproveFundingRequestAndIssueCode
{
    public function __construct(
        private SystemUserResolverContract $systemUsers,
        private FundingAccountCreditContract $accounts,
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryPositionOperationContract $operations,
    ) {}

    public function handle(
        FundingRequest $fundingRequest,
        string $approverType,
        string $approverId,
    ): AccountFundingCode {
        return DB::transaction(function () use (
            $fundingRequest,
            $approverType,
            $approverId,
        ): AccountFundingCode {
            $locked = FundingRequest::query()
                ->with('fundingCode')
                ->lockForUpdate()
                ->findOrFail($fundingRequest->getKey());

            if ($locked->fundingCode instanceof AccountFundingCode) {
                return $locked->fundingCode;
            }

            if ($locked->status !== FundingRequestStatus::AwaitingApproval) {
                throw new RuntimeException('This Funding Request is not awaiting approval.');
            }

            if (
                $locked->reviewed_by_type === $approverType
                && $locked->reviewed_by_id === $approverId
            ) {
                throw new RuntimeException('The backing reviewer cannot approve the same request.');
            }

            $amountMinor = (int) $locked->approved_value_minor;

            if ($amountMinor <= 0 || trim((string) $locked->evidence_reference) === '') {
                throw new RuntimeException('Recognized backing is required before approval.');
            }

            $connection = collect($this->connections->active([
                (string) $locked->connection_reference,
            ]))->sole();
            $system = $this->systemUsers->resolve();
            $account = $this->accounts->resolve($locked->account_reference);
            $recipient = data_get($account, 'holder');

            if (! $system instanceof Model || ! $recipient instanceof Model) {
                throw new RuntimeException('Funding Request principals could not be resolved.');
            }

            if (
                $recipient::class !== $locked->requester_type
                || (string) $recipient->getKey() !== $locked->requester_id
            ) {
                throw new RuntimeException('Funding Request recipient binding is invalid.');
            }

            $systemPortfolio = $this->portfolios->provision($system, [$connection->reference]);
            $recipientPortfolio = $this->portfolios->provision($recipient, [$connection->reference]);
            $source = $this->position(
                $systemPortfolio->positions,
                TreasuryPositionPurpose::ClientFunds,
            );
            $reserve = $this->position(
                $systemPortfolio->positions,
                TreasuryPositionPurpose::PayCodeReserve,
            );
            $destination = $this->position(
                $recipientPortfolio->positions,
                TreasuryPositionPurpose::ClientFunds,
            );
            $scope = hash('sha256', 'account-funding-code|'.$locked->reference);
            $reservationReference = 'account-funding-reservation:'.$scope;
            $claimReference = 'account-funding-claim:'.$scope;

            $this->operations->reserve(new TreasuryPositionReservationData(
                operationReference: $reservationReference,
                sourcePositionReference: $source->positionReference,
                destinationPositionReference: $reserve->positionReference,
                amountMinor: $amountMinor,
                currency: $locked->currency,
                idempotencyKey: 'account-funding-reservation-key:'.$scope,
                externalReference: (string) $locked->evidence_reference,
                metadata: [
                    'funding_request_reference' => $locked->reference,
                    'purpose' => 'account_funding_code',
                    'provider_calls' => false,
                ],
            ));

            $plainCode = Str::upper(Str::random(12));
            $code = AccountFundingCode::query()->create([
                'funding_request_id' => $locked->getKey(),
                'code_hash' => hash('sha256', $plainCode),
                'code_ciphertext' => $plainCode,
                'code_last_four' => substr($plainCode, -4),
                'recipient_type' => $recipient::class,
                'recipient_id' => (string) $recipient->getKey(),
                'account_reference' => $locked->account_reference,
                'amount_minor' => $amountMinor,
                'currency' => $locked->currency,
                'connection_reference' => $connection->reference,
                'source_position_reference' => $source->positionReference,
                'reserve_position_reference' => $reserve->positionReference,
                'destination_position_reference' => $destination->positionReference,
                'reservation_operation_reference' => $reservationReference,
                'claim_operation_reference' => $claimReference,
                'status' => AccountFundingCodeStatus::Issued,
                'version' => 1,
                'issued_at' => now(),
                'expires_at' => now()->addSeconds((int) config(
                    'x-change.funding.requests.code_ttl_seconds',
                    604800,
                )),
                'metadata' => [
                    'capability' => 'account_funding',
                    'provider_payout_enabled' => false,
                ],
            ]);
            $nextVersion = $locked->version + 1;
            $locked->forceFill([
                'status' => FundingRequestStatus::CodeIssued,
                'version' => $nextVersion,
                'approved_by_type' => $approverType,
                'approved_by_id' => $approverId,
                'approved_at' => now(),
            ])->saveQuietly();
            $locked->events()->create([
                'sequence' => $nextVersion,
                'event_type' => 'account_funding_code_issued',
                'from_status' => FundingRequestStatus::AwaitingApproval,
                'to_status' => FundingRequestStatus::CodeIssued,
                'actor_type' => $approverType,
                'actor_id' => $approverId,
                'evidence_reference' => $locked->evidence_reference,
                'metadata' => [
                    'account_funding_code_reference' => $code->reference,
                    'reservation_operation_reference' => $reservationReference,
                    'provider_calls' => false,
                ],
                'occurred_at' => now(),
            ]);
            $locked->notices()->create([
                'recipient_type' => $locked->requester_type,
                'recipient_id' => $locked->requester_id,
                'notice_type' => 'account_funding_code_ready',
                'title' => 'Account Funding Code ready',
                'message' => 'Verified value is reserved and ready for your one-time claim.',
                'action' => [
                    'type' => 'claim_account_funding_code',
                    'reference' => $code->reference,
                ],
            ]);

            return $code;
        }, 5);
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     */
    private function position(
        array $positions,
        TreasuryPositionPurpose $purpose,
    ): TreasuryPositionData {
        $position = collect($positions)->first(
            static fn (TreasuryPositionData $position): bool => $position->purpose === $purpose,
        );

        if (! $position instanceof TreasuryPositionData) {
            throw new RuntimeException("The required {$purpose->value} Position is unavailable.");
        }

        return $position;
    }
}
