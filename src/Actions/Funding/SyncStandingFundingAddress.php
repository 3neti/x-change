<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Data\Funding\StandingFundingObservationRequestData;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Data\Funding\StandingFundingAddressBindingData;
use LBHurtado\XChange\Data\Funding\StandingFundingAddressSyncData;
use LBHurtado\XChange\Enums\AccountFundingReceiptStatus;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Exceptions\StandingFundingAddressBindingTimeUnavailable;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressBindingResolver;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressProviderRegistry;
use LBHurtado\XChange\Services\Funding\StandingFundingRecognitionPolicy;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;

final class SyncStandingFundingAddress
{
    public function __construct(
        private readonly StandingFundingAddressProviderRegistry $providers,
        private readonly RecordProviderFundingObservation $recordObservation,
        private readonly ClassifyStandingFundingObservation $classify,
        private readonly CorrectNetbankStandingFundingReceiptNormalization $correctNormalization,
        private readonly IgnorePreActivationFundingReceipts $ignorePreActivationReceipts,
        private readonly SynchronizeStandingFundingRecognitionMode $synchronizeRecognitionMode,
        private readonly StandingFundingRecognitionPolicy $recognitionPolicy,
        private readonly SettleAccountFundingReceipt $settle,
        private readonly OpenFundingSuspenseCase $openSuspense,
        private readonly AuditLoggerContract $audit,
        private readonly StandingFundingAddressBindingResolver $bindings,
    ) {}

    public function handle(
        StandingFundingAddress $address,
        string $trigger = 'operator',
        ?int $webhookReceiptId = null,
    ): StandingFundingAddressSyncData {
        $lock = Cache::lock(
            'x-change:standing-funding-address:'.$address->getKey(),
            max(1, (int) config('x-change.funding.standing_addresses.lock_seconds', 120)),
        );

        return $lock->block(
            max(1, (int) config('x-change.funding.standing_addresses.lock_wait_seconds', 5)),
            fn (): StandingFundingAddressSyncData => $this->sync($address->fresh(), $trigger, $webhookReceiptId),
        );
    }

    private function sync(
        StandingFundingAddress $address,
        string $trigger,
        ?int $webhookReceiptId,
    ): StandingFundingAddressSyncData {
        if ($address->status !== FundingAddressStatus::Active) {
            return new StandingFundingAddressSyncData(0, 0, 0, 0);
        }

        $address = $this->synchronizeRecognitionMode->handle($address);
        $quarantinedCount = $this->ignorePreActivationReceipts->handle($address);
        $currentBinding = $this->bindings->current($address);
        $destination = is_array($currentBinding->destinationSnapshot)
            ? FundingDestinationSnapshot::toData($currentBinding->destinationSnapshot)
            : null;
        $observations = $this->providers->for($address->provider_code)->observeStandingFundingAddress(
            new StandingFundingObservationRequestData(
                fundingAddress: $address->funding_address_ciphertext,
                accountReference: $currentBinding->accountReference,
                purpose: $address->purpose,
                currency: $address->currency,
                verificationSource: $trigger,
                destination: $destination,
                webhookReceiptId: $webhookReceiptId,
            ),
        );
        $counts = [
            'observed' => 0,
            'settled' => 0,
            'awaiting_approval' => 0,
            'suspense' => 0,
            'applied' => 0,
            'pre_activation_ignored' => 0,
        ];

        foreach ($observations as $data) {
            if ($data->occurredAt !== null
                && $address->activated_at !== null
                && $data->occurredAt < $address->activated_at) {
                $counts['pre_activation_ignored']++;

                continue;
            }

            $observation = $this->recordObservation->handle($data);
            $classified = $this->classify->handle($observation);

            if (! $classified instanceof StandingFundingAddress) {
                $this->openSuspense->handle(
                    provider: $observation->provider_code,
                    reasonCode: 'unknown_funding_address',
                    observation: $observation,
                    details: ['classification' => 'provider-and-exact-destination'],
                );
                $counts['suspense']++;

                continue;
            }

            if ($classified->purpose !== FundingAddressPurpose::AccountFunding) {
                $this->audit->log('funding.standing_address.observation_classified', [
                    'standing_funding_address_reference' => $classified->reference,
                    'provider_observation_id' => $observation->getKey(),
                    'provider' => $classified->provider_code,
                    'purpose' => $classified->purpose->value,
                    'balance_changed' => false,
                ]);
                $counts['observed']++;

                continue;
            }

            try {
                $binding = $this->bindings->at($classified, $observation->occurredAtInstant());
            } catch (StandingFundingAddressBindingTimeUnavailable) {
                $this->openSuspense->handle(
                    provider: $observation->provider_code,
                    reasonCode: 'binding_effective_time_unavailable',
                    observation: $observation,
                    details: ['standing_funding_address_reference' => $classified->reference],
                );
                $counts['suspense']++;

                continue;
            }

            $receipt = $this->correctNormalization->handle($classified, $observation)
                ?? $this->recordReceipt($classified, $observation, $binding);
            $wasSettled = $receipt->status === AccountFundingReceiptStatus::Settled;

            if ($receipt->status === AccountFundingReceiptStatus::Ready) {
                try {
                    $receipt = $this->settle->handle($receipt);
                } catch (FundingSettlementDenied $exception) {
                    $receipt = $this->suspendReceipt($receipt, 'settlement_guard_denied', $observation);
                }
            }

            if (! $wasSettled && $receipt->status === AccountFundingReceiptStatus::Settled) {
                $counts['applied']++;
            }

            match ($receipt->status) {
                AccountFundingReceiptStatus::Settled => $counts['settled']++,
                AccountFundingReceiptStatus::AwaitingApproval => $counts['awaiting_approval']++,
                AccountFundingReceiptStatus::Suspense => $counts['suspense']++,
                default => $counts['observed']++,
            };
        }

        $address->last_checked_at = now();
        $address->saveQuietly();

        $this->audit->log('funding.standing_address.synchronized', [
            'standing_funding_address_reference' => $address->reference,
            'provider' => $address->provider_code,
            'purpose' => $address->purpose->value,
            'trigger' => strtolower(trim($trigger)),
            'observed_count' => count($observations),
            'pre_activation_ignored_count' => $counts['pre_activation_ignored'],
            'pre_activation_quarantined_count' => $quarantinedCount,
            'settled_count' => $counts['settled'],
            'applied_count' => $counts['applied'],
            'suspense_count' => $counts['suspense'],
        ]);

        return new StandingFundingAddressSyncData(
            observed: $counts['observed'],
            settled: $counts['settled'],
            awaitingApproval: $counts['awaiting_approval'],
            suspense: $counts['suspense'],
            applied: $counts['applied'],
        );
    }

    private function recordReceipt(
        StandingFundingAddress $address,
        ProviderFundingObservation $observation,
        StandingFundingAddressBindingData $binding,
    ): AccountFundingReceipt {
        return DB::transaction(function () use ($address, $observation, $binding): AccountFundingReceipt {
            $transactionKey = hash('sha256', implode("\0", [
                $observation->provider_code,
                $observation->provider_transaction_id,
            ]));
            $receipt = AccountFundingReceipt::query()
                ->where('provider_transaction_key', $transactionKey)
                ->lockForUpdate()
                ->first();

            if ($receipt instanceof AccountFundingReceipt
                && $receipt->status === AccountFundingReceiptStatus::Settled) {
                if ($this->evidenceChanged($receipt, $observation)
                    || ! $this->postRecognitionStatusIsCompatible($receipt, $observation)) {
                    $this->openSuspense->handle(
                        provider: $observation->provider_code,
                        reasonCode: 'post_settlement_status_changed',
                        observation: $observation,
                        details: ['account_funding_receipt_reference' => $receipt->reference],
                    );

                    return $receipt;
                }

                if ($receipt->provider_funding_observation_id !== $observation->getKey()) {
                    $receipt->provider_funding_observation_id = $observation->getKey();
                    $receipt->metadata = array_merge($receipt->metadata ?? [], [
                        'latest_provider_status' => $observation->provider_status,
                    ]);
                    $receipt->saveQuietly();
                }

                return $receipt->refresh();
            }

            if ($receipt instanceof AccountFundingReceipt
                && in_array($receipt->status, [
                    AccountFundingReceiptStatus::AwaitingApproval,
                    AccountFundingReceiptStatus::Ready,
                ], true)
                && ! $this->evidenceChanged($receipt, $observation)) {
                return $receipt;
            }

            $receipt ??= AccountFundingReceipt::query()->create([
                'standing_funding_address_id' => $address->getKey(),
                'standing_funding_address_binding_revision_id' => $binding->revisionId,
                'provider_funding_observation_id' => $observation->getKey(),
                'provider_transaction_key' => $transactionKey,
                'provider_code' => $address->provider_code,
                'account_reference' => $binding->accountReference,
                'purpose' => $address->purpose,
                'recognition_mode_snapshot' => $address->recognition_mode,
                'status' => AccountFundingReceiptStatus::Observed,
                'gross_amount_minor' => $observation->gross_amount_minor,
                'fee_amount_minor' => $observation->fee_amount_minor,
                'net_amount_minor' => $observation->net_amount_minor,
                'currency' => $observation->currency,
                'observed_at' => $observation->occurred_at ?? now(),
                'metadata' => ['verification_source' => $observation->verification_source],
            ]);

            if ($this->evidenceChanged($receipt, $observation)) {
                return $this->suspendReceipt($receipt, 'provider_evidence_changed', $observation);
            }

            $receipt->provider_funding_observation_id = $observation->getKey();

            if (! $this->recognitionPolicy->accepts($observation)) {
                $receipt->status = AccountFundingReceiptStatus::Observed;
                $receipt->saveQuietly();

                return $receipt->refresh();
            }

            $reason = $this->suspenseReason($address, $observation);

            if ($reason !== null) {
                return $this->suspendReceipt($receipt, $reason, $observation);
            }

            $receipt->verified_at = now();
            $receipt->metadata = array_merge($receipt->metadata ?? [], [
                'provider_status_at_recognition' => $observation->provider_status,
                'provisional_recognition' => $this->recognitionPolicy->isProvisional($observation),
            ]);
            $receipt->status = match ($address->recognition_mode) {
                FundingRecognitionMode::ObserveOnly => AccountFundingReceiptStatus::Observed,
                FundingRecognitionMode::Supervised => AccountFundingReceiptStatus::AwaitingApproval,
                FundingRecognitionMode::Automatic => AccountFundingReceiptStatus::Ready,
            };
            $receipt->saveQuietly();

            return $receipt->refresh();
        }, attempts: 3);
    }

    private function postRecognitionStatusIsCompatible(
        AccountFundingReceipt $receipt,
        ProviderFundingObservation $observation,
    ): bool {
        if ($observation->provider_status === 'settled') {
            return true;
        }

        $recognizedStatus = data_get($receipt->metadata, 'provider_status_at_recognition');

        return is_string($recognizedStatus)
            && $recognizedStatus === $observation->provider_status
            && data_get($receipt->metadata, 'provisional_recognition') === true;
    }

    private function evidenceChanged(
        AccountFundingReceipt $receipt,
        ProviderFundingObservation $observation,
    ): bool {
        return $receipt->provider_code !== $observation->provider_code
            || $receipt->gross_amount_minor !== $observation->gross_amount_minor
            || $receipt->fee_amount_minor !== $observation->fee_amount_minor
            || $receipt->net_amount_minor !== $observation->net_amount_minor
            || $receipt->currency !== $observation->currency;
    }

    private function suspenseReason(
        StandingFundingAddress $address,
        ProviderFundingObservation $observation,
    ): ?string {
        return match (true) {
            $observation->funding_address !== 'sha256:'.$address->funding_address_hash => 'destination_mismatch',
            data_get($observation->metadata, 'destination_verified') !== true => 'destination_unverified',
            $observation->currency !== $address->currency => 'currency_mismatch',
            $observation->net_amount_minor <= 0 => 'non_positive_net_amount',
            $address->minimum_amount_minor !== null
                && $observation->gross_amount_minor < $address->minimum_amount_minor => 'below_minimum_amount',
            $address->maximum_amount_minor !== null
                && $observation->gross_amount_minor > $address->maximum_amount_minor => 'above_maximum_amount',
            default => null,
        };
    }

    private function suspendReceipt(
        AccountFundingReceipt $receipt,
        string $reason,
        ProviderFundingObservation $observation,
    ): AccountFundingReceipt {
        $receipt->provider_funding_observation_id = $observation->getKey();
        $receipt->status = AccountFundingReceiptStatus::Suspense;
        $receipt->suspense_reason = $reason;
        $receipt->suspense_at = now();
        $receipt->saveQuietly();

        $this->openSuspense->handle(
            provider: $observation->provider_code,
            reasonCode: $reason,
            observation: $observation,
            details: ['account_funding_receipt_reference' => $receipt->reference],
        );

        return $receipt->refresh();
    }
}
