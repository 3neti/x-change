<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Commercial;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\CommercialPartnerRevisionStatus;
use LBHurtado\XChange\Enums\PartnerCommissionPayoutBatchStatus;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialPartnerDestinationRevision;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;
use LBHurtado\XChange\Services\Commercial\CommercialAccountingJournal;

final readonly class PreparePartnerCommissionPayoutRetry
{
    public function __construct(
        private CommercialOperatorAuthorityContract $authority,
        private SystemUserResolverContract $systemPrincipal,
        private CommercialAccountingJournal $journal,
    ) {}

    public function execute(
        Model $operator,
        PartnerCommissionPayoutBatch $batch,
        CommercialPartnerDestinationRevision $destinationRevision,
    ): PartnerCommissionPayoutBatch {
        $this->authorize($operator);

        $prepared = DB::transaction(function () use ($batch, $destinationRevision): PartnerCommissionPayoutBatch {
            $locked = PartnerCommissionPayoutBatch::query()->lockForUpdate()->findOrFail($batch->getKey());
            $destination = CommercialPartnerDestinationRevision::query()
                ->lockForUpdate()
                ->findOrFail($destinationRevision->getKey());

            if ($locked->status !== PartnerCommissionPayoutBatchStatus::Rejected) {
                throw new CommercialSaleConflict('Only a rejected commission payout may be prepared for retry.');
            }

            if ((string) $locked->commercial_partner_id !== (string) $destination->commercial_partner_id
                || $destination->status !== CommercialPartnerRevisionStatus::Approved
                || $destination->provider !== $locked->provider
                || $destination->connection_reference !== $locked->connection_reference
                || $destination->currency !== $locked->currency) {
                throw new CommercialSaleConflict('Retry requires an approved destination for the same Partner and Treasury connection.');
            }

            $payload = $destination->destination;
            $locked->forceFill([
                'status' => PartnerCommissionPayoutBatchStatus::Approved,
                'commercial_partner_destination_revision_id' => $destination->getKey(),
                'destination' => [
                    ...$payload,
                    'settlement_rail' => $locked->amount_minor >= 5_000_000 ? 'PESONET' : 'INSTAPAY',
                ],
                'destination_hash' => $destination->destination_hash,
                'destination_summary' => $destination->destination_summary,
                'submission_idempotency_key' => null,
                'provider_transaction_id' => null,
                'provider_transaction_uuid' => null,
                'rejected_at' => null,
                'submitted_at' => null,
                'metadata' => [
                    ...$locked->metadata,
                    'retry_prepared' => [
                        'next_attempt_number' => $locked->attempts()->count() + 1,
                        'destination_revision_id' => $destination->getKey(),
                        'recorded_at' => now()->toIso8601String(),
                    ],
                ],
            ])->save();

            return $locked->refresh();
        }, attempts: 5);

        $this->journal->recordPartnerPayoutRetryPrepared($prepared, $operator);

        return $prepared;
    }

    private function authorize(Model $operator): void
    {
        if ($operator->is($this->systemPrincipal->resolve())
            || ! $this->authority->allows($operator, CommercialOperatorCapability::ExecuteCommissionPayouts)) {
            throw new AuthorizationException('Operator lacks commission payout execution authority.');
        }
    }
}
