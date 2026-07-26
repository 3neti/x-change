<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\SettlementEnvelope\Enums\EnvelopeStatus;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Services\Funding\FundingRequestWorkflowPublisher;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;
use RuntimeException;

final readonly class ApproveFundingRequestAndIssueCode
{
    public function __construct(
        private SystemUserResolverContract $systemUsers,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryPayCodeAccountingService $accounting,
        private EnvelopeService $envelopes,
        private FundingRequestWorkflowPublisher $workflows,
    ) {}

    public function handle(
        FundingRequest $fundingRequest,
        string $approverType,
        string $approverId,
    ): Voucher {
        $voucher = DB::transaction(function () use (
            $fundingRequest,
            $approverType,
            $approverId,
        ): Voucher {
            $locked = FundingRequest::query()
                ->with('voucher.envelope')
                ->lockForUpdate()
                ->findOrFail($fundingRequest->getKey());

            if (
                in_array($locked->status, [
                    FundingRequestStatus::PayCodeIssued,
                    FundingRequestStatus::Completed,
                ], true)
                && $locked->voucher instanceof Voucher
            ) {
                return $locked->voucher;
            }

            if ($locked->status !== FundingRequestStatus::AwaitingApproval) {
                throw new RuntimeException(
                    'This Funding Request is not awaiting approval.',
                );
            }

            if (
                $locked->reviewed_by_type === $approverType
                && $locked->reviewed_by_id === $approverId
            ) {
                throw new RuntimeException(
                    'The backing reviewer cannot approve the same request.',
                );
            }

            if (
                ! $locked->voucher instanceof Voucher
                || $locked->voucher->envelope === null
            ) {
                throw new RuntimeException(
                    'Reviewed Account Funding requires its PAYABLE Pay Code and Settlement Envelope.',
                );
            }

            $amountMinor = (int) $locked->approved_value_minor;

            if (
                $amountMinor <= 0
                || trim((string) $locked->evidence_reference) === ''
                || $amountMinor !== (int) $locked->requested_value_minor
            ) {
                throw new RuntimeException(
                    'Recognized backing must exactly match the requested Pay Code target.',
                );
            }

            $system = $this->systemUsers->resolve();
            $approver = $this->actor($approverType, $approverId);

            if (! $system instanceof Model) {
                throw new RuntimeException(
                    'The configured system Treasury principal is unavailable.',
                );
            }

            $this->envelopes->setSignal(
                $locked->voucher->envelope,
                'checker_approved',
                true,
                $approver,
            );
            $envelope = $locked->voucher->envelope->refresh();

            if (
                $envelope->status !== EnvelopeStatus::READY_TO_SETTLE
                || ! $envelope->isSettleable()
            ) {
                throw new RuntimeException(
                    'The Settlement Envelope is not ready for Treasury payment.',
                );
            }

            $envelope = $this->envelopes->lock($envelope, $approver);
            $this->portfolios->provision(
                $system,
                [(string) $locked->connection_reference],
            );
            $reservation = $this->accounting->reserveAccountFunding(
                systemOwner: $system,
                voucher: $locked->voucher,
                connectionReference: (string) $locked->connection_reference,
                providerPrincipalMinor: $amountMinor,
                currency: $locked->currency,
            );
            $voucherMetadata = is_array($locked->voucher->metadata)
                ? $locked->voucher->metadata
                : [];
            data_set($voucherMetadata, 'treasury.account_funding', [
                'status' => 'ready_for_system_payment',
                'provider_calls' => false,
                'provider_inventory_changed' => false,
                'funding_request_reference' => $locked->reference,
                'reservation_operation_reference' => $reservation->operationReference,
                'settlement_envelope_id' => $envelope->getKey(),
            ]);
            $locked->voucher->forceFill([
                'metadata' => $voucherMetadata,
                'state' => VoucherState::ACTIVE,
            ])->saveQuietly();

            $nextVersion = $locked->version + 1;
            $locked->forceFill([
                'status' => FundingRequestStatus::PayCodeIssued,
                'version' => $nextVersion,
                'approved_by_type' => $approverType,
                'approved_by_id' => $approverId,
                'approved_at' => now(),
            ])->saveQuietly();
            $locked->events()->create([
                'sequence' => $nextVersion,
                'event_type' => 'reviewed_funding_pay_code_approved',
                'from_status' => FundingRequestStatus::AwaitingApproval,
                'to_status' => FundingRequestStatus::PayCodeIssued,
                'actor_type' => $approverType,
                'actor_id' => $approverId,
                'evidence_reference' => $locked->evidence_reference,
                'metadata' => [
                    'pay_code_id' => (int) $locked->voucher->getKey(),
                    'reservation_operation_reference' => $reservation->operationReference,
                    'settlement_envelope_id' => $envelope->getKey(),
                    'provider_calls' => false,
                ],
                'occurred_at' => now(),
            ]);
            $locked->notices()->create([
                'recipient_type' => $locked->requester_type,
                'recipient_id' => $locked->requester_id,
                'notice_type' => 'reviewed_funding_pay_code_approved',
                'title' => 'Account Funding approved',
                'message' => 'Verified value is reserved for one system Treasury payment.',
                'action' => [
                    'type' => 'view_reviewed_funding_pay_code',
                    'funding_request_reference' => $locked->reference,
                ],
            ]);

            return $locked->voucher->refresh();
        }, 5);

        $this->workflows->publish($fundingRequest->refresh());

        return $voucher;
    }

    private function actor(string $type, string $id): Model
    {
        if (! is_subclass_of($type, Model::class)) {
            throw new RuntimeException('Funding Request approver type is invalid.');
        }

        $actor = $type::query()->find($id);

        if (! $actor instanceof Model) {
            throw new RuntimeException('Funding Request approver was not found.');
        }

        return $actor;
    }
}
