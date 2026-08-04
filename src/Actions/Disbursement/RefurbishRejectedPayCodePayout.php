<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Disbursement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Contracts\PayoutDestinationValidatorContract;
use LBHurtado\XChange\Events\DisbursementConfirmed;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\PayoutDestinationRevision;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Treasury\PayCodePayoutCorrectionJournal;
use RuntimeException;
use Throwable;

final readonly class RefurbishRejectedPayCodePayout
{
    public function __construct(
        private PayoutDestinationValidatorContract $destinations,
        private PayoutProvider $payouts,
        private DisbursementStatusResolverContract $statuses,
        private PayCodePayoutCorrectionJournal $journal,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        Voucher $voucher,
        Model $requestedBy,
        string $bankCode,
        string $accountNumber,
        ?string $mobile = null,
    ): array {
        $lock = Cache::lock(
            'x-change:payout-refurbishment:'.$voucher->getKey(),
            120,
        );

        if (! $lock->get()) {
            throw new RuntimeException('Another payout correction is already in progress.');
        }

        try {
            $voucher = $voucher->fresh();
            $this->assertAuthorized($voucher, $requestedBy);
            [$claim, $rejection] = $this->recoveryContext($voucher);
            $rail = (string) $rejection->settlement_rail;
            $validation = $this->destinations->validate(
                $bankCode,
                $accountNumber,
                $rail,
                $mobile,
            );

            if (! $validation->isValid()) {
                throw ValidationException::withMessages([
                    'account_number' => $validation->message,
                ]);
            }

            [$revision, $reconciliation, $request] = DB::transaction(
                function () use (
                    $claim,
                    $rejection,
                    $requestedBy,
                    $validation,
                    $voucher,
                ): array {
                    $version = ((int) PayoutDestinationRevision::query()
                        ->where('voucher_id', $voucher->getKey())
                        ->lockForUpdate()
                        ->max('version')) + 1;
                    $providerReference = (string) $voucher->code.'-R'.$version;
                    $revision = PayoutDestinationRevision::query()->create([
                        'voucher_id' => $voucher->getKey(),
                        'voucher_claim_id' => $claim->getKey(),
                        'rejected_reconciliation_id' => $rejection->getKey(),
                        'version' => $version,
                        'bank_code' => $validation->bankCode,
                        'account_number_ciphertext' => $validation->accountNumber,
                        'account_number_hash' => $this->sensitiveHash($validation->accountNumber),
                        'account_number_masked' => $this->mask($validation->accountNumber),
                        'mobile_ciphertext' => $validation->mobile,
                        'mobile_hash' => $validation->mobile !== null
                            ? $this->sensitiveHash($validation->mobile)
                            : null,
                        'validation_status' => $validation->status,
                        'validation_metadata' => [
                            'provider_verified' => $validation->providerVerified,
                            'message' => $validation->message,
                            'checks' => $validation->checks,
                        ],
                        'requested_by_type' => $requestedBy->getMorphClass(),
                        'requested_by_id' => $requestedBy->getKey(),
                        'recorded_at' => now(),
                    ]);
                    $request = $this->request(
                        $voucher,
                        $rejection,
                        $revision,
                        $providerReference,
                    );
                    $reconciliation = DisbursementReconciliation::query()->create([
                        'voucher_id' => $voucher->getKey(),
                        'voucher_code' => $voucher->code,
                        'claim_type' => 'payout_recovery',
                        'provider' => 'unknown',
                        'provider_reference' => $providerReference,
                        'status' => 'intent',
                        'internal_status' => 'intent',
                        'amount' => $request->amount,
                        'currency' => $request->currency,
                        'bank_code' => $request->bank_code,
                        'account_number_masked' => $revision->account_number_masked,
                        'settlement_rail' => $request->settlement_rail,
                        'attempt_count' => 1,
                        'attempted_at' => now(),
                        'needs_review' => false,
                        'raw_request' => [
                            'reference' => $request->reference,
                            'amount' => $request->amount,
                            'currency' => $request->currency,
                            'bank_code' => $request->bank_code,
                            'account_number_masked' => $revision->account_number_masked,
                            'settlement_rail' => $request->settlement_rail,
                            'destination_revision_reference' => $revision->reference,
                        ],
                        'meta' => [
                            'flow' => 'payout_recovery',
                            'destination_revision_reference' => $revision->reference,
                            'rejected_reconciliation_id' => $rejection->getKey(),
                        ],
                    ]);
                    $this->journal->record(
                        $voucher,
                        $revision,
                        $reconciliation,
                        $requestedBy,
                    );

                    return [$revision, $reconciliation, $request];
                },
                attempts: 5,
            );

            try {
                $result = $this->payouts->disburse($request);
                $status = $this->statuses->resolveFromGatewayResponse($result);
                $reconciliation->forceFill([
                    'provider' => $result->provider ?? 'unknown',
                    'provider_transaction_id' => $result->transaction_id,
                    'transaction_uuid' => $result->uuid,
                    'status' => $status,
                    'internal_status' => 'recorded',
                    'completed_at' => $status === 'succeeded' ? now() : null,
                    'needs_review' => $status === 'failed',
                    'review_reason' => $status === 'failed'
                        ? 'Immediate provider failure requires authoritative status verification.'
                        : null,
                    'raw_response' => $result->toArray(),
                ])->save();
            } catch (Throwable $exception) {
                $status = 'unknown';
                $reconciliation->forceFill([
                    'status' => 'unknown',
                    'internal_status' => 'recorded',
                    'needs_review' => true,
                    'review_reason' => 'Provider outcome is unknown after payout correction.',
                    'error_message' => 'The provider outcome could not be confirmed.',
                    'raw_response' => [
                        'exception' => $exception::class,
                        'message' => 'Provider outcome could not be confirmed.',
                    ],
                ])->save();
            }

            $this->markRetrySubmitted($voucher, $claim, $reconciliation, $revision);

            if ($status === 'succeeded') {
                Event::dispatch(new DisbursementConfirmed($reconciliation->fresh()));
            }

            return [
                'schema' => 'x-change.pay-code-payout-refurbishment.v1',
                'success' => $status !== 'failed',
                'pay_code' => (string) $voucher->code,
                'claim_preserved' => true,
                'destination_revision' => (string) $revision->reference,
                'destination_version' => (int) $revision->version,
                'validation_status' => (string) $revision->validation_status,
                'validation_message' => data_get($revision->validation_metadata, 'message'),
                'provider_reference' => (string) $reconciliation->provider_reference,
                'provider_transaction_id' => $reconciliation->provider_transaction_id,
                'status' => (string) $reconciliation->status,
            ];
        } finally {
            $lock->release();
        }
    }

    private function assertAuthorized(Voucher $voucher, Model $requestedBy): void
    {
        if (
            $voucher->owner_type !== $requestedBy->getMorphClass()
            || (string) $voucher->owner_id !== (string) $requestedBy->getKey()
        ) {
            throw new RuntimeException('Only the Pay Code owner may correct this payout destination.');
        }

        if (data_get($voucher->metadata, 'treasury.pay_code_reservation.status') !== 'recovery_pending') {
            throw new RuntimeException('The Pay Code does not have a recoverable rejected payout.');
        }
    }

    /** @return array{VoucherClaim, DisbursementReconciliation} */
    private function recoveryContext(Voucher $voucher): array
    {
        $claim = VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->latest('id')
            ->firstOrFail();
        $rejection = DisbursementReconciliation::query()
            ->where('voucher_id', $voucher->getKey())
            ->where('status', 'failed')
            ->whereNotNull('provider_transaction_id')
            ->latest('id')
            ->firstOrFail();

        if ($claim->status !== 'payout_rejected' || $rejection->needs_review) {
            throw new RuntimeException('Authoritative provider rejection evidence is required.');
        }

        return [$claim, $rejection];
    }

    private function request(
        Voucher $voucher,
        DisbursementReconciliation $rejection,
        PayoutDestinationRevision $revision,
        string $providerReference,
    ): PayoutRequestData {
        return new PayoutRequestData(
            reference: $providerReference,
            amount: (float) $rejection->amount,
            account_number: (string) $revision->account_number_ciphertext,
            bank_code: (string) $revision->bank_code,
            settlement_rail: (string) $rejection->settlement_rail,
            currency: (string) $rejection->currency,
            external_id: (string) $voucher->getKey(),
            external_code: (string) $voucher->code,
            user_id: is_numeric($voucher->owner_id) ? (int) $voucher->owner_id : null,
            mobile: $revision->mobile_ciphertext,
            metadata: [
                'recovery' => true,
                'destination_revision_reference' => $revision->reference,
                'rejected_reconciliation_id' => $rejection->getKey(),
            ],
        );
    }

    private function markRetrySubmitted(
        Voucher $voucher,
        VoucherClaim $claim,
        DisbursementReconciliation $reconciliation,
        PayoutDestinationRevision $revision,
    ): void {
        DB::transaction(function () use ($claim, $reconciliation, $revision, $voucher): void {
            $metadata = (array) $voucher->refresh()->metadata;
            data_set($metadata, 'disbursement.status', $reconciliation->status);
            data_set($metadata, 'disbursement.gateway', $reconciliation->provider);
            data_set($metadata, 'disbursement.transaction_id', $reconciliation->provider_transaction_id);
            data_set($metadata, 'disbursement.requires_reconciliation', $reconciliation->status !== 'succeeded');
            data_set($metadata, 'disbursement.requires_recovery', $reconciliation->status !== 'succeeded');
            data_set($metadata, 'disbursement.destination_revision_reference', $revision->reference);
            data_set($metadata, 'disbursement.destination_version', $revision->version);
            data_forget($metadata, 'disbursement.rejection_reason');
            $voucher->forceFill(['metadata' => $metadata])->saveQuietly();

            if ($reconciliation->status !== 'succeeded') {
                $claimMeta = (array) $claim->meta;
                data_set($claimMeta, 'disbursement.status', $reconciliation->status);
                data_set($claimMeta, 'disbursement.destination_revision_reference', $revision->reference);
                $claim->forceFill([
                    'status' => 'payout_retry_pending',
                    'failure_message' => null,
                    'meta' => $claimMeta,
                ])->save();
            }
        }, attempts: 5);
    }

    private function sensitiveHash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function mask(string $value): string
    {
        return str_repeat('*', max(0, strlen($value) - 4)).substr($value, -4);
    }
}
