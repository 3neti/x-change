<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Enums\AccountFundingReceiptStatus;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\FundingSuspenseCase;
use LBHurtado\XChange\Models\StandingFundingAddress;

final class CorrectNetbankStandingFundingReceiptNormalization
{
    private const NormalizationVersion = 'netbank-standing-credit-v2';

    public function __construct(
        private readonly AuditLoggerContract $audit,
    ) {}

    public function handle(
        StandingFundingAddress $address,
        ProviderFundingObservation $correctedObservation,
    ): ?AccountFundingReceipt {
        $wasCorrected = false;

        $receipt = DB::transaction(function () use (
            $address,
            $correctedObservation,
            &$wasCorrected,
        ): ?AccountFundingReceipt {
            $lockedAddress = StandingFundingAddress::query()
                ->lockForUpdate()
                ->findOrFail($address->getKey());
            $transactionKey = hash('sha256', implode("\0", [
                $correctedObservation->provider_code,
                $correctedObservation->provider_transaction_id,
            ]));
            $receipt = AccountFundingReceipt::query()
                ->where('provider_transaction_key', $transactionKey)
                ->lockForUpdate()
                ->first();

            if (! $receipt instanceof AccountFundingReceipt) {
                return null;
            }

            if ($receipt->provider_funding_observation_id === $correctedObservation->getKey()
                && in_array($receipt->status, [
                    AccountFundingReceiptStatus::AwaitingApproval,
                    AccountFundingReceiptStatus::Ready,
                    AccountFundingReceiptStatus::Settled,
                ], true)) {
                $this->resolveSuspenseCases(
                    $receipt,
                    $correctedObservation,
                    data_get(
                        $receipt->metadata,
                        'normalization_correction.original_observation_id',
                    ),
                );

                return $receipt;
            }

            if ($receipt->status !== AccountFundingReceiptStatus::Suspense
                || ! in_array($receipt->suspense_reason, [
                    'non_positive_net_amount',
                    'provider_evidence_changed',
                ], true)
                || $receipt->wallet_transaction_id !== null
                || $receipt->settled_at !== null) {
                return null;
            }

            $originalObservation = $this->originalObservation(
                $receipt,
                $correctedObservation,
            );

            if (! $originalObservation instanceof ProviderFundingObservation
                || ! $this->isAuthorizedCorrection(
                    $lockedAddress,
                    $receipt,
                    $originalObservation,
                    $correctedObservation,
                )) {
                return null;
            }

            $metadata = $receipt->metadata ?? [];
            $metadata['normalization_correction'] = [
                'version' => self::NormalizationVersion,
                'original_observation_id' => $originalObservation->getKey(),
                'corrected_observation_id' => $correctedObservation->getKey(),
                'original_fee_amount_minor' => $receipt->fee_amount_minor,
                'original_net_amount_minor' => $receipt->net_amount_minor,
                'corrected_at' => now()->toRfc3339String(),
                'approval_policy' => 'owner_supervised',
            ];

            $receipt->forceFill([
                'provider_funding_observation_id' => $correctedObservation->getKey(),
                'gross_amount_minor' => $correctedObservation->gross_amount_minor,
                'fee_amount_minor' => $correctedObservation->fee_amount_minor,
                'net_amount_minor' => $correctedObservation->net_amount_minor,
                'status' => AccountFundingReceiptStatus::AwaitingApproval,
                'suspense_reason' => null,
                'verified_at' => now(),
                'metadata' => $metadata,
            ])->saveQuietly();

            $this->resolveSuspenseCases(
                $receipt,
                $correctedObservation,
                $originalObservation->getKey(),
            );

            $wasCorrected = true;

            return $receipt->refresh();
        }, attempts: 3);

        if ($wasCorrected && $receipt instanceof AccountFundingReceipt) {
            $this->audit->log('funding.standing_address.normalization_corrected', [
                'account_funding_receipt_reference' => $receipt->reference,
                'standing_funding_address_reference' => $address->reference,
                'provider' => $receipt->provider_code,
                'normalization_version' => self::NormalizationVersion,
                'balance_changed' => false,
            ]);
        }

        return $receipt;
    }

    private function isAuthorizedCorrection(
        StandingFundingAddress $address,
        AccountFundingReceipt $receipt,
        ProviderFundingObservation $original,
        ProviderFundingObservation $corrected,
    ): bool {
        return $address->provider_code === 'netbank'
            && $corrected->provider_code === 'netbank'
            && $original->provider_code === $corrected->provider_code
            && $original->provider_transaction_id === $corrected->provider_transaction_id
            && $original->provider_status === 'settled'
            && $corrected->provider_status === 'settled'
            && $original->gross_amount_minor === $corrected->gross_amount_minor
            && $original->currency === $corrected->currency
            && $original->funding_address === $corrected->funding_address
            && $original->provider_account_reference === $corrected->provider_account_reference
            && $original->occurredAtInstant()?->equalTo($corrected->occurredAtInstant()) === true
            && $original->fee_amount_minor === $original->gross_amount_minor
            && $original->net_amount_minor === 0
            && $corrected->fee_amount_minor === 0
            && $corrected->net_amount_minor === $corrected->gross_amount_minor
            && $corrected->gross_amount_minor > 0
            && $receipt->currency === $corrected->currency
            && $corrected->currency === $address->currency
            && $corrected->provider_status === 'settled'
            && $corrected->settledAtInstant() !== null
            && $corrected->occurredAtInstant() !== null
            && $address->activated_at !== null
            && $corrected->occurredAtInstant()->greaterThanOrEqualTo($address->activated_at)
            && $corrected->funding_address === 'sha256:'.$address->funding_address_hash
            && data_get($corrected->metadata, 'destination_verified') === true
            && data_get($corrected->metadata, 'normalization_version') === self::NormalizationVersion
            && data_get($corrected->metadata, 'incoming_credit_amount_is_net_received') === true;
    }

    private function originalObservation(
        AccountFundingReceipt $receipt,
        ProviderFundingObservation $corrected,
    ): ?ProviderFundingObservation {
        if ($receipt->suspense_reason === 'non_positive_net_amount') {
            return ProviderFundingObservation::query()
                ->lockForUpdate()
                ->find($receipt->provider_funding_observation_id);
        }

        return ProviderFundingObservation::query()
            ->where('provider_code', $corrected->provider_code)
            ->where('provider_transaction_id', $corrected->provider_transaction_id)
            ->where('provider_status', 'settled')
            ->whereColumn('fee_amount_minor', 'gross_amount_minor')
            ->where('net_amount_minor', 0)
            ->lockForUpdate()
            ->first();
    }

    private function resolveSuspenseCases(
        AccountFundingReceipt $receipt,
        ProviderFundingObservation $corrected,
        mixed $originalObservationId,
    ): void {
        $observationIds = array_values(array_filter([
            is_int($originalObservationId) ? $originalObservationId : null,
            $corrected->getKey(),
        ]));

        FundingSuspenseCase::query()
            ->whereIn('provider_funding_observation_id', $observationIds)
            ->whereIn('reason_code', [
                'non_positive_net_amount',
                'provider_evidence_changed',
            ])
            ->where('status', 'open')
            ->lockForUpdate()
            ->get()
            ->each(function (FundingSuspenseCase $case) use (
                $receipt,
                $corrected,
            ): void {
                $case->forceFill([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'resolved_by_type' => 'system',
                    'resolved_by_id' => self::NormalizationVersion,
                    'resolution_code' => 'normalization_corrected',
                    'resolution' => [
                        'account_funding_receipt_reference' => $receipt->reference,
                        'corrected_observation_id' => $corrected->getKey(),
                        'next_step' => $receipt->status === AccountFundingReceiptStatus::Settled
                            ? 'complete'
                            : 'owner_approval',
                    ],
                ])->saveQuietly();
            });
    }
}
