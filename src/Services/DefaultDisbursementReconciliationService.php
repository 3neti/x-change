<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\DisbursementStatusFetcherContract;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;

class DefaultDisbursementReconciliationService implements DisbursementReconciliationContract
{
    public function __construct(
        protected DisbursementReconciliationStoreContract $store,
        protected DisbursementStatusFetcherContract $fetcher,
        protected DisbursementStatusResolverContract $resolver,
    ) {}

    public function reconcile(DisbursementReconciliationData $reconciliation): DisbursementReconciliationData
    {
        try {
            $providerPayload = $this->fetcher->fetch($reconciliation);

            $normalized = $this->resolver->resolveFromGatewayResponse((object) $providerPayload);

            return $this->store->record([
                'voucher_id' => $reconciliation->voucher_id,
                'voucher_code' => $reconciliation->voucher_code,
                'claim_type' => $reconciliation->claim_type,
                'provider' => $reconciliation->provider,
                'provider_reference' => $reconciliation->provider_reference,
                'provider_transaction_id' => $providerPayload['transaction_id']
                    ?? $reconciliation->provider_transaction_id,
                'transaction_uuid' => $providerPayload['uuid']
                    ?? $reconciliation->transaction_uuid,
                'status' => $normalized,
                'internal_status' => $this->resolveInternalStatus($normalized),
                'amount' => $reconciliation->amount,
                'currency' => $reconciliation->currency,
                'bank_code' => $reconciliation->bank_code,
                'account_number_masked' => $reconciliation->account_number_masked,
                'settlement_rail' => $reconciliation->settlement_rail,
                'attempt_count' => max(1, $reconciliation->attempt_count),
                'attempted_at' => $reconciliation->attempted_at,
                'completed_at' => $normalized === 'succeeded' ? now() : $reconciliation->completed_at,
                'last_checked_at' => now(),
                'needs_review' => $normalized === 'unknown',
                'review_reason' => $normalized === 'unknown' ? 'Provider returned unknown status' : null,
                'error_message' => $normalized === 'failed'
                    ? ($providerPayload['message'] ?? $reconciliation->error_message)
                    : null,
                'raw_request' => $reconciliation->raw_request,
                'raw_response' => $providerPayload,
                'meta' => array_merge($reconciliation->meta ?? [], [
                    'reconciled_at' => now()->toIso8601String(),
                ]),
            ]);
        } catch (\Throwable $e) {
            return $this->store->record([
                'voucher_id' => $reconciliation->voucher_id,
                'voucher_code' => $reconciliation->voucher_code,
                'claim_type' => $reconciliation->claim_type,
                'provider' => $reconciliation->provider,
                'provider_reference' => $reconciliation->provider_reference,
                'provider_transaction_id' => $reconciliation->provider_transaction_id,
                'transaction_uuid' => $reconciliation->transaction_uuid,
                'status' => 'unknown',
                'internal_status' => 'manual_review',
                'amount' => $reconciliation->amount,
                'currency' => $reconciliation->currency,
                'bank_code' => $reconciliation->bank_code,
                'account_number_masked' => $reconciliation->account_number_masked,
                'settlement_rail' => $reconciliation->settlement_rail,
                'attempt_count' => max(1, $reconciliation->attempt_count),
                'attempted_at' => $reconciliation->attempted_at,
                'completed_at' => $reconciliation->completed_at,
                'last_checked_at' => now(),
                'needs_review' => true,
                'review_reason' => 'Reconciliation fetch failed',
                'error_message' => $e->getMessage(),
                'raw_request' => $reconciliation->raw_request,
                'raw_response' => [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ],
                'meta' => array_merge($reconciliation->meta ?? [], [
                    'reconciliation_error_at' => now()->toIso8601String(),
                ]),
            ]);
        }
    }

    protected function resolveInternalStatus(string $normalized): string
    {
        return match ($normalized) {
            'succeeded' => 'matched',
            'failed' => 'finalized',
            'pending' => 'recorded',
            default => 'manual_review',
        };
    }
}
