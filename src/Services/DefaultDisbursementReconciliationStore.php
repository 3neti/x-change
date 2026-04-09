<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;
use LBHurtado\XChange\Models\DisbursementReconciliation;

class DefaultDisbursementReconciliationStore implements DisbursementReconciliationStoreContract
{
    public function record(array $attributes): DisbursementReconciliationData
    {
        $model = DisbursementReconciliation::query()->updateOrCreate(
            [
                'voucher_code' => (string) $attributes['voucher_code'],
                'provider_reference' => $attributes['provider_reference'] ?? null,
                'claim_type' => $attributes['claim_type'] ?? null,
            ],
            $attributes
        );

        return $this->toData($model);
    }

    public function findByVoucherReferenceAndClaim(
        string $voucherCode,
        ?string $providerReference,
        ?string $claimType,
    ): ?DisbursementReconciliationData {
        $model = DisbursementReconciliation::query()
            ->where('voucher_code', $voucherCode)
            ->where('provider_reference', $providerReference)
            ->where('claim_type', $claimType)
            ->first();

        return $model ? $this->toData($model) : null;
    }

    protected function toData(DisbursementReconciliation $model): DisbursementReconciliationData
    {
        return new DisbursementReconciliationData(
            id: $model->id,
            voucher_id: $model->voucher_id,
            voucher_code: $model->voucher_code,
            claim_type: $model->claim_type,
            provider: $model->provider,
            provider_reference: $model->provider_reference,
            provider_transaction_id: $model->provider_transaction_id,
            transaction_uuid: $model->transaction_uuid,
            status: $model->status,
            internal_status: $model->internal_status,
            amount: $model->amount !== null ? (float) $model->amount : null,
            currency: $model->currency,
            bank_code: $model->bank_code,
            account_number_masked: $model->account_number_masked,
            settlement_rail: $model->settlement_rail,
            attempt_count: (int) $model->attempt_count,
            attempted_at: $model->attempted_at?->toIso8601String(),
            completed_at: $model->completed_at?->toIso8601String(),
            last_checked_at: $model->last_checked_at?->toIso8601String(),
            next_retry_at: $model->next_retry_at?->toIso8601String(),
            needs_review: (bool) $model->needs_review,
            review_reason: $model->review_reason,
            error_message: $model->error_message,
            raw_request: $model->raw_request,
            raw_response: $model->raw_response,
            meta: $model->meta,
        );
    }
}
