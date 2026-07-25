<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Services\Claim\VoucherClaimantReference;
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
        private IssueTreasuryBackedPayCode $issuance,
        private VoucherClaimantReference $claimantReferences,
    ) {}

    public function handle(
        FundingRequest $fundingRequest,
        string $approverType,
        string $approverId,
    ): Voucher {
        return DB::transaction(function () use (
            $fundingRequest,
            $approverType,
            $approverId,
        ): Voucher {
            $locked = FundingRequest::query()
                ->with('voucher')
                ->lockForUpdate()
                ->findOrFail($fundingRequest->getKey());

            if ($locked->voucher instanceof Voucher) {
                return $locked->voucher;
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

            if (
                ! $system instanceof Model
                || ! $system instanceof Authenticatable
                || ! $recipient instanceof Model
                || ! $recipient instanceof Authenticatable
            ) {
                throw new RuntimeException('Funding Request principals could not be resolved.');
            }

            if (
                $recipient::class !== $locked->requester_type
                || (string) $recipient->getKey() !== $locked->requester_id
            ) {
                throw new RuntimeException('Funding Request recipient binding is invalid.');
            }

            $systemPortfolio = $this->portfolios->provision(
                $system,
                [$connection->reference],
            );
            $this->portfolios->provision($recipient, [$connection->reference]);
            $source = $this->position(
                $systemPortfolio->positions,
                TreasuryPositionPurpose::ClientFunds,
            );
            $reserve = $this->position(
                $systemPortfolio->positions,
                TreasuryPositionPurpose::PayCodeReserve,
            );
            $scope = hash('sha256', 'reviewed-funding-pay-code|'.$locked->reference);
            $reservationReference = 'reviewed-funding-reservation:'.$scope;
            $claimantReference = $this->claimantReferences->for($recipient);
            $expiresAt = now()->addSeconds((int) config(
                'x-change.funding.requests.code_ttl_seconds',
                604800,
            ));
            $voucher = $this->issuance->handle($system, [
                'cash' => [
                    'amount' => $amountMinor / 100,
                    'currency' => $locked->currency,
                    'validation' => ['country' => 'PH'],
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
                'count' => 1,
                'prefix' => 'FUND',
                'mask' => '****',
                'expires_at' => $expiresAt,
                'voucher_type' => 'redeemable',
                'claim' => [
                    'outcomes' => [[
                        'key' => 'account_funding',
                        'pricing_profile' => 'account-funding-v1',
                    ]],
                    'selection' => 'server',
                    'consumption' => 'one_of',
                    'default_outcome' => 'account_funding',
                    'onboarding' => ['mode' => 'if_required'],
                    'claimant' => [
                        'mode' => 'recipient',
                        'reference' => $claimantReference,
                    ],
                ],
                'metadata' => [
                    'issuer_id' => (string) $system->getAuthIdentifier(),
                    'source' => 'manual',
                    'custom' => [
                        'settlement' => [
                            'destinations' => ['account_funding'],
                            'account_funding' => [
                                'pricing_profile' => 'account-funding-v1',
                            ],
                        ],
                        'reviewed_funding' => [
                            'request_reference' => $locked->reference,
                        ],
                    ],
                ],
            ], $expiresAt);

            $this->operations->reserve(new TreasuryPositionReservationData(
                operationReference: $reservationReference,
                sourcePositionReference: $source->positionReference,
                destinationPositionReference: $reserve->positionReference,
                amountMinor: $amountMinor,
                currency: $locked->currency,
                idempotencyKey: 'reviewed-funding-reservation-key:'.$scope,
                externalReference: (string) $locked->evidence_reference,
                metadata: [
                    'funding_request_reference' => $locked->reference,
                    'pay_code_id' => (int) $voucher->getKey(),
                    'purpose' => 'account_funding',
                    'provider_calls' => false,
                ],
            ));

            $metadata = is_array($voucher->metadata) ? $voucher->metadata : [];
            data_set($metadata, 'treasury.account_funding', [
                'status' => 'ready',
                'destinations' => ['account_funding'],
                'pricing_profile' => 'account-funding-v1',
                'provider_cost_minor' => 0,
                'provider_calls' => false,
                'funding_request_reference' => $locked->reference,
            ]);
            data_set($metadata, 'treasury.pay_code_reservation', [
                'status' => 'reserved',
                'provider' => $connection->provider,
                'connection_reference' => $connection->reference,
                'operation_reference' => $reservationReference,
                'amount_minor' => $amountMinor,
                'currency' => $locked->currency,
            ]);
            $voucher->forceFill(['metadata' => $metadata])->saveQuietly();

            $nextVersion = $locked->version + 1;
            $locked->forceFill([
                'voucher_id' => $voucher->getKey(),
                'status' => FundingRequestStatus::PayCodeIssued,
                'version' => $nextVersion,
                'approved_by_type' => $approverType,
                'approved_by_id' => $approverId,
                'approved_at' => now(),
            ])->saveQuietly();
            $locked->events()->create([
                'sequence' => $nextVersion,
                'event_type' => 'reviewed_funding_pay_code_issued',
                'from_status' => FundingRequestStatus::AwaitingApproval,
                'to_status' => FundingRequestStatus::PayCodeIssued,
                'actor_type' => $approverType,
                'actor_id' => $approverId,
                'evidence_reference' => $locked->evidence_reference,
                'metadata' => [
                    'pay_code_id' => (int) $voucher->getKey(),
                    'reservation_operation_reference' => $reservationReference,
                    'provider_calls' => false,
                ],
                'occurred_at' => now(),
            ]);
            $locked->notices()->create([
                'recipient_type' => $locked->requester_type,
                'recipient_id' => $locked->requester_id,
                'notice_type' => 'reviewed_funding_pay_code_ready',
                'title' => 'Reviewed Funding Pay Code ready',
                'message' => 'Verified value is reserved and ready for your one-time claim.',
                'action' => [
                    'type' => 'claim_reviewed_funding_pay_code',
                    'funding_request_reference' => $locked->reference,
                ],
            ]);

            return $voucher->refresh();
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
