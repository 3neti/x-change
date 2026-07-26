<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Data\Funding\IssueSystemAccountFundingPayCodeData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\FundingRequest;
use RuntimeException;

final readonly class ApproveFundingRequestAndIssueCode
{
    public function __construct(
        private FundingAccountCreditContract $accounts,
        private IssueSystemAccountFundingPayCode $issuance,
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

            $account = $this->accounts->resolve($locked->account_reference);
            $recipient = data_get($account, 'holder');

            if (
                ! $recipient instanceof Model
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

            $expiresAt = now()->addSeconds((int) config(
                'x-change.funding.requests.code_ttl_seconds',
                604800,
            ));
            $issuance = $this->issuance->handle(
                new IssueSystemAccountFundingPayCodeData(
                    amountMinor: $amountMinor,
                    connectionReference: (string) $locked->connection_reference,
                    idempotencyReference: 'reviewed-funding-pay-code|'.$locked->reference,
                    expiresAt: $expiresAt,
                    recipient: $recipient,
                    evidenceReference: (string) $locked->evidence_reference,
                    authorizationReference: implode(':', [
                        'funding-request-approval',
                        (string) $locked->reference,
                        $approverType,
                        $approverId,
                    ]),
                    source: 'reviewed_funding',
                    metadata: [
                        'custom' => [
                            'reviewed_funding' => [
                                'request_reference' => $locked->reference,
                            ],
                        ],
                    ],
                ),
            );
            $voucher = $issuance->voucher;

            if (! $voucher instanceof Voucher) {
                throw new RuntimeException(
                    'Reviewed Funding Pay Code issuance did not return a Voucher.',
                );
            }

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
                    'reservation_operation_reference' => $issuance->reservation_operation_reference,
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
}
