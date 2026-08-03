<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Disbursement;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Events\DisbursementConfirmed;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use RuntimeException;

final readonly class RetryFailedPayCodeDisbursement
{
    public function __construct(
        private PayoutProvider $payouts,
        private DisbursementStatusResolverContract $statuses,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(string $code, bool $confirmedNoProviderTransfer): array
    {
        if (! $confirmedNoProviderTransfer) {
            throw new RuntimeException(
                'Explicit confirmation that no provider transfer exists is required.',
            );
        }

        $normalizedCode = mb_strtoupper(trim($code));
        $lock = Cache::lock('x-change:disbursement-retry:'.$normalizedCode, 120);

        if (! $lock->get()) {
            throw new RuntimeException('Another disbursement recovery is already running.');
        }

        try {
            $voucher = Voucher::query()->where('code', $normalizedCode)->firstOrFail();
            $reconciliation = DisbursementReconciliation::query()
                ->where('voucher_id', $voucher->getKey())
                ->where('status', 'failed')
                ->latest('id')
                ->firstOrFail();

            $this->assertRetryable($voucher, $reconciliation);
            $request = PayoutRequestData::from((array) $reconciliation->raw_request);
            $result = $this->payouts->disburse($request);
            $status = $this->statuses->resolveFromGatewayResponse($result);

            $reconciliation = DB::transaction(function () use (
                $reconciliation,
                $result,
                $status,
                $voucher,
            ): DisbursementReconciliation {
                $reconciliation->forceFill([
                    'provider' => $result->provider ?? 'unknown',
                    'provider_transaction_id' => $result->transaction_id,
                    'transaction_uuid' => $result->uuid,
                    'status' => $status,
                    'internal_status' => 'recorded',
                    'attempt_count' => ((int) $reconciliation->attempt_count) + 1,
                    'attempted_at' => now(),
                    'completed_at' => $status === 'succeeded' ? now() : null,
                    'needs_review' => $status === 'failed',
                    'review_reason' => $status === 'failed'
                        ? 'Provider rejected guarded disbursement recovery'
                        : null,
                    'error_message' => $status === 'failed'
                        ? 'Provider rejected guarded disbursement recovery.'
                        : null,
                    'raw_response' => $result->toArray(),
                    'meta' => [
                        ...(array) $reconciliation->meta,
                        'recovery' => [
                            'mode' => 'confirmed_no_prior_provider_transfer',
                            'attempted_at' => now()->toIso8601String(),
                        ],
                    ],
                ])->save();

                $metadata = (array) $voucher->metadata;
                data_set($metadata, 'disbursement.gateway', $result->provider);
                data_set($metadata, 'disbursement.transaction_id', $result->transaction_id);
                data_set($metadata, 'disbursement.status', $result->status->value);
                data_set(
                    $metadata,
                    'disbursement.requires_reconciliation',
                    $result->status !== PayoutStatus::COMPLETED,
                );
                data_forget($metadata, 'disbursement.error');
                $voucher->forceFill(['metadata' => $metadata])->saveQuietly();

                return $reconciliation->fresh();
            }, attempts: 5);

            if ($status === 'succeeded') {
                Event::dispatch(new DisbursementConfirmed($reconciliation));
            }

            return [
                'schema' => 'x-change.disbursement-recovery.v1',
                'success' => $status !== 'failed',
                'pay_code' => $normalizedCode,
                'provider_reference' => $reconciliation->provider_reference,
                'provider_transaction_id' => $reconciliation->provider_transaction_id,
                'provider' => $reconciliation->provider,
                'status' => $reconciliation->status,
                'attempt_count' => $reconciliation->attempt_count,
            ];
        } finally {
            $lock->release();
        }
    }

    private function assertRetryable(
        Voucher $voucher,
        DisbursementReconciliation $reconciliation,
    ): void {
        $retryable = $voucher->redeemed_at !== null
            && data_get($voucher->metadata, 'disbursement.requires_reconciliation') === true
            && data_get($voucher->metadata, 'treasury.pay_code_reservation.status') === 'reserved'
            && $reconciliation->provider_transaction_id === null
            && in_array($reconciliation->provider, [null, '', 'unknown'], true)
            && filled($reconciliation->provider_reference)
            && is_array($reconciliation->raw_request)
            && data_get($reconciliation->raw_request, 'reference') === $reconciliation->provider_reference;

        if (! $retryable) {
            throw new RuntimeException(
                'The Pay Code is not eligible for guarded disbursement recovery.',
            );
        }
    }
}
