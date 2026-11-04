<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Contracts\StandingFundingCorrectionPolicy;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Enums\AccountFundingReceiptStatus;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\FundingSuspenseCase;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressProviderRegistry;

class CorrectStandingFundingReceiptNormalization
{
    public function __construct(
        private readonly AuditLoggerContract $audit,
        private readonly StandingFundingAddressProviderRegistry $providers,
    ) {}

    public function handle(
        StandingFundingAddress $address,
        ProviderFundingObservation $correctedObservation,
    ): ?AccountFundingReceipt {
        $wasCorrected = false;
        $correctionVersion = null;

        $receipt = DB::transaction(function () use (
            $address,
            $correctedObservation,
            &$wasCorrected,
            &$correctionVersion,
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
                $version = data_get($receipt->metadata, 'normalization_correction.version');
                if (is_string($version) && $version !== '') {
                    $this->resolveSuspenseCases(
                        $receipt,
                        $correctedObservation,
                        data_get($receipt->metadata, 'normalization_correction.original_observation_id'),
                        $version,
                    );
                }

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

            $provider = $this->providers->for($lockedAddress->provider_code);
            $correctionVersion = $originalObservation instanceof ProviderFundingObservation && $provider instanceof StandingFundingCorrectionPolicy
                ? $provider->correctionVersion($originalObservation, $correctedObservation)
                : null;

            if (! is_string($correctionVersion) || $correctionVersion === ''
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
                'version' => $correctionVersion,
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
                $correctionVersion,
            );

            $wasCorrected = true;

            return $receipt->refresh();
        }, attempts: 3);

        if ($wasCorrected && $receipt instanceof AccountFundingReceipt) {
            $this->audit->log('funding.standing_address.normalization_corrected', [
                'account_funding_receipt_reference' => $receipt->reference,
                'standing_funding_address_reference' => $address->reference,
                'provider' => $receipt->provider_code,
                'normalization_version' => $correctionVersion,
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
        return $address->provider_code === $corrected->provider_code
            && $original->provider_code === $corrected->provider_code
            && $original->provider_transaction_id === $corrected->provider_transaction_id
            && $original->provider_status === 'settled'
            && $corrected->provider_status === 'settled'
            && $original->gross_amount_minor === $corrected->gross_amount_minor
            && $original->currency === $corrected->currency
            && $original->funding_address === $corrected->funding_address
            && $original->provider_account_reference === $corrected->provider_account_reference
            && $original->occurredAtInstant()?->equalTo($corrected->occurredAtInstant()) === true
            && $corrected->gross_amount_minor > 0
            && $receipt->currency === $corrected->currency
            && $corrected->currency === $address->currency
            && $corrected->provider_status === 'settled'
            && $corrected->settledAtInstant() !== null
            && $corrected->occurredAtInstant() !== null
            && $address->activated_at !== null
            && $corrected->occurredAtInstant()->greaterThanOrEqualTo($address->activated_at)
            && $corrected->funding_address === 'sha256:'.$address->funding_address_hash
            && data_get($corrected->metadata, 'destination_verified') === true;
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

        $provider = $this->providers->for($corrected->provider_code);
        if (! $provider instanceof StandingFundingCorrectionPolicy) {
            return null;
        }

        $matches = ProviderFundingObservation::query()
            ->where('provider_code', $corrected->provider_code)
            ->where('provider_transaction_id', $corrected->provider_transaction_id)
            ->whereKeyNot($corrected->getKey())
            ->where('provider_status', 'settled')
            ->lockForUpdate()
            ->get()
            ->filter(fn (ProviderFundingObservation $original): bool => $provider->correctionVersion($original, $corrected) !== null);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function resolveSuspenseCases(
        AccountFundingReceipt $receipt,
        ProviderFundingObservation $corrected,
        mixed $originalObservationId,
        string $correctionVersion,
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
                $correctionVersion,
            ): void {
                $case->forceFill([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'resolved_by_type' => 'system',
                    'resolved_by_id' => $correctionVersion,
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
