<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Commercial;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\PartnerCommissionPayoutBatchStatus;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;
use LBHurtado\XChange\Services\Commercial\CommercialAccountingJournal;
use Throwable;

final readonly class SubmitPartnerCommissionPayoutBatch
{
    public function __construct(
        private CommercialOperatorAuthorityContract $authority,
        private SystemUserResolverContract $systemPrincipal,
        private PayoutProvider $payouts,
        private CommercialAccountingJournal $journal,
    ) {}

    public function execute(
        Model $operator,
        PartnerCommissionPayoutBatch $batch,
        string $idempotencyKey,
    ): PartnerCommissionPayoutBatch {
        $this->authorize($operator);

        if (! (bool) config('x-change.commercial.operations.live_provider_calls_enabled', false)) {
            throw new CommercialSaleConflict('Live commercial provider calls are disabled.');
        }

        $idempotencyKey = trim($idempotencyKey);

        if ($idempotencyKey === '') {
            throw new CommercialSaleConflict('Commission payout submission key is required.');
        }

        $prepared = DB::transaction(function () use ($batch, $idempotencyKey): PartnerCommissionPayoutBatch {
            $locked = PartnerCommissionPayoutBatch::query()->lockForUpdate()->findOrFail($batch->getKey());

            if ($locked->submission_idempotency_key !== null) {
                if ($locked->submission_idempotency_key !== $idempotencyKey) {
                    throw new CommercialSaleConflict('Commission payout submission was replayed with a different key.');
                }

                return $locked;
            }

            if ($locked->status !== PartnerCommissionPayoutBatchStatus::Approved) {
                throw new CommercialSaleConflict('Commission payout requires independent approval before submission.');
            }

            $locked->forceFill([
                'status' => PartnerCommissionPayoutBatchStatus::Submitted,
                'submission_idempotency_key' => $idempotencyKey,
                'submitted_at' => now(),
            ])->save();

            return $locked->refresh();
        }, attempts: 5);

        if ($prepared->provider_transaction_id !== null
            || $prepared->status !== PartnerCommissionPayoutBatchStatus::Submitted
            || $prepared->submitted_at?->lt(now()->subMinute())) {
            return $prepared;
        }

        try {
            $destination = $prepared->destination;
            $result = $this->payouts->disburse(new PayoutRequestData(
                reference: $prepared->reference,
                amount: $prepared->amount_minor / 100,
                account_number: (string) data_get($destination, 'account_number'),
                bank_code: (string) data_get($destination, 'bank_code'),
                settlement_rail: (string) data_get($destination, 'settlement_rail'),
                currency: $prepared->currency,
                external_id: (string) $prepared->getKey(),
                external_code: $prepared->reference,
                mobile: (string) data_get($destination, 'mobile'),
                metadata: [
                    'flow' => 'commercial_partner_commission',
                    'partner_reference' => $prepared->partner_reference,
                    'recipient_name' => (string) data_get($destination, 'recipient_name'),
                ],
            ));
        } catch (Throwable $exception) {
            $prepared->forceFill([
                'status' => PartnerCommissionPayoutBatchStatus::Suspense,
                'metadata' => [
                    ...$prepared->metadata,
                    'submission_failure' => [
                        'exception' => $exception::class,
                        'recorded_at' => now()->toIso8601String(),
                    ],
                ],
            ])->save();

            $prepared = $prepared->refresh();
            $this->journal->recordPartnerPayoutBatch(
                $prepared,
                $operator->getMorphClass().':'.$operator->getKey(),
                'commercial_payout_executor',
            );

            return $prepared;
        }

        $prepared->forceFill([
            'status' => $result->status === PayoutStatus::FAILED
                ? PartnerCommissionPayoutBatchStatus::Rejected
                : PartnerCommissionPayoutBatchStatus::Pending,
            'provider_transaction_id' => $result->transaction_id,
            'provider_transaction_uuid' => $result->uuid,
            'rejected_at' => $result->status === PayoutStatus::FAILED ? now() : null,
            'metadata' => [
                ...$prepared->metadata,
                'provider_submission' => [
                    'provider' => $result->provider,
                    'status' => $result->status->value,
                    'recorded_at' => now()->toIso8601String(),
                ],
            ],
        ])->save();

        $prepared = $prepared->refresh();
        $this->journal->recordPartnerPayoutBatch(
            $prepared,
            $operator->getMorphClass().':'.$operator->getKey(),
            'commercial_payout_executor',
        );

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
