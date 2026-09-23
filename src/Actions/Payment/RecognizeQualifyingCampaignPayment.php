<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Data\Payment\CampaignPaymentRecognitionData;
use LBHurtado\XChange\Data\Payment\CampaignPaymentRecognitionResultData;
use LBHurtado\XChange\Data\Payment\CanonicalProviderFundingTransactionData;
use LBHurtado\XChange\Enums\CampaignPaymentAmountMode;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Events\CampaignPaymentRecognized;
use LBHurtado\XChange\Models\CampaignPaymentEvidenceQuarantine;
use LBHurtado\XChange\Models\CampaignPaymentQrBinding;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;

final readonly class RecognizeQualifyingCampaignPayment
{
    /** @var list<string> */
    private const SupportedRuleKeys = [
        'allowed_rails',
        'maximum_amount_minor',
        'maximum_payments',
        'minimum_amount_minor',
    ];

    public function __construct(
        private ReduceProviderFundingTransactionEvidence $reduce,
        private QuarantineCampaignPaymentEvidence $quarantine,
        private AuditLoggerContract $audit,
    ) {}

    public function handle(
        CampaignPaymentQrBinding $binding,
        ProviderFundingObservation $observation,
    ): CampaignPaymentRecognitionResultData {
        $this->assertBoundObservation($binding, $observation);

        $outcome = DB::transaction(function () use ($binding, $observation): array {
            $lockedBinding = CampaignPaymentQrBinding::query()
                ->lockForUpdate()
                ->findOrFail($binding->getKey());
            $evidence = ProviderFundingObservation::query()
                ->where('provider_code', strtolower((string) $observation->provider_code))
                ->where('provider_transaction_id', $observation->provider_transaction_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $canonical = $this->reduce->handle($evidence);

            if ($canonical->providerStatus !== 'settled') {
                return ['state' => 'pending'];
            }

            $existing = CampaignPaymentRecognition::query()
                ->where('campaign_payment_qr_binding_id', $lockedBinding->getKey())
                ->where('provider_transaction_key', $canonical->providerTransactionKey)
                ->first();

            if ($existing instanceof CampaignPaymentRecognition) {
                $this->assertReplayMatches($existing, $lockedBinding, $canonical);

                return ['state' => 'recognized', 'recognition' => $existing, 'created' => false];
            }

            $quarantine = CampaignPaymentEvidenceQuarantine::query()
                ->where('campaign_payment_qr_binding_id', $lockedBinding->getKey())
                ->where('provider_transaction_key', $canonical->providerTransactionKey)
                ->first();

            if ($quarantine instanceof CampaignPaymentEvidenceQuarantine) {
                return ['state' => 'quarantined', 'quarantine' => $quarantine];
            }

            $rejection = $this->qualificationRejection($lockedBinding, $canonical);

            if ($rejection !== null) {
                return [
                    'state' => 'rejected',
                    'reason' => $rejection,
                    'canonical' => $canonical,
                    'evidence_ids' => $evidence->modelKeys(),
                ];
            }

            $recognizedAt = now();
            $recognition = CampaignPaymentRecognition::query()->create([
                'recognition_key' => $this->recognitionKey($lockedBinding, $canonical),
                'campaign_payment_qr_binding_id' => $lockedBinding->getKey(),
                'canonical_provider_funding_observation_id' => $canonical->canonicalObservationId,
                'campaign_revision_id' => $lockedBinding->campaign_revision_id,
                'provider_code' => $canonical->providerCode,
                'provider_transaction_key' => $canonical->providerTransactionKey,
                'provider_operation_key' => $canonical->providerOperationKey,
                'request_key' => $canonical->requestKey,
                'gross_amount_minor' => $canonical->grossAmountMinor,
                'fee_amount_minor' => $canonical->feeAmountMinor,
                'net_amount_minor' => $canonical->netAmountMinor,
                'currency' => $canonical->currency,
                'provider_status' => $canonical->providerStatus,
                'settlement_rail' => $canonical->settlementRail,
                'destination_verified' => $canonical->destinationVerified,
                'occurred_at' => $canonical->occurredAt,
                'settled_at' => $canonical->settledAt,
                'evidence_observation_ids' => $canonical->evidenceObservationIds,
                'evidence_fingerprint' => hash('sha256', implode('|', $canonical->evidenceObservationIds)),
                'binding_configuration_hash' => $lockedBinding->configuration_hash,
                'rule_snapshot' => $lockedBinding->permitted_payment_rules ?? [],
                'recognized_at' => $recognizedAt,
            ]);

            return ['state' => 'recognized', 'recognition' => $recognition, 'created' => true];
        }, attempts: 5);

        if ($outcome['state'] === 'pending') {
            return new CampaignPaymentRecognitionResultData;
        }

        if ($outcome['state'] === 'quarantined') {
            return new CampaignPaymentRecognitionResultData(quarantine: $outcome['quarantine']);
        }

        if ($outcome['state'] === 'rejected') {
            $canonical = $outcome['canonical'];
            $quarantine = $this->quarantine->handle(
                binding: $binding,
                trigger: $observation,
                providerTransactionKey: $canonical->providerTransactionKey,
                reasonCode: 'qualification_rejected',
                reasonDetail: $outcome['reason'],
                evidenceObservationIds: $outcome['evidence_ids'],
            );

            return new CampaignPaymentRecognitionResultData(quarantine: $quarantine);
        }

        /** @var CampaignPaymentRecognition $recognition */
        $recognition = $outcome['recognition'];

        if ($outcome['created'] === true) {
            $this->audit->log('campaign.payment.recognized', [
                'campaign_reference' => $binding->campaign->reference,
                'campaign_revision_id' => $binding->campaign_revision_id,
                'binding_reference' => $binding->reference,
                'recognition_reference' => $recognition->reference,
                'provider' => $recognition->provider_code,
                'gross_amount_minor' => $recognition->gross_amount_minor,
                'net_amount_minor' => $recognition->net_amount_minor,
                'currency' => $recognition->currency,
                'evidence_count' => count($recognition->evidence_observation_ids),
                'financial_side_effects' => false,
            ]);
            CampaignPaymentRecognized::dispatch(
                ownerType: (string) $binding->standingFundingAddress->owner_type,
                ownerId: (string) $binding->standingFundingAddress->owner_id,
                recognition: $this->eventData($binding, $recognition),
            );
        }

        return new CampaignPaymentRecognitionResultData(recognition: $recognition);
    }

    private function assertBoundObservation(
        CampaignPaymentQrBinding $binding,
        ProviderFundingObservation $observation,
    ): void {
        $address = $binding->standingFundingAddress;

        if (! $observation->exists
            || strtolower((string) $observation->provider_code) !== strtolower((string) $binding->provider_code)
            || ! hash_equals('sha256:'.$address->funding_address_hash, (string) $observation->funding_address)) {
            throw new InvalidArgumentException(
                'Campaign payment evidence does not belong to the bound provider destination.',
            );
        }
    }

    private function qualificationRejection(
        CampaignPaymentQrBinding $binding,
        CanonicalProviderFundingTransactionData $payment,
    ): ?string {
        $address = $binding->standingFundingAddress;
        $rules = $binding->permitted_payment_rules ?? [];
        $unsupported = array_diff(array_keys($rules), self::SupportedRuleKeys);

        if ($unsupported !== []) {
            return 'unsupported_rule';
        }

        if ($address->status !== FundingAddressStatus::Active
            || $payment->providerCode !== strtolower((string) $binding->provider_code)
            || $payment->currency !== strtoupper((string) $binding->currency)
            || ! $payment->destinationVerified
            || $payment->occurredAt === null
            || $payment->settledAt === null
            || $payment->grossAmountMinor < 1
            || $payment->netAmountMinor < 1
            || $address->activated_at === null
            || $payment->occurredAt->lessThan($address->activated_at)) {
            return 'binding_or_settlement_mismatch';
        }

        if ($binding->available_from !== null
            && $payment->occurredAt->lessThan($binding->available_from)) {
            return 'outside_availability';
        }

        if ($binding->available_until !== null
            && $payment->occurredAt->greaterThan($binding->available_until)) {
            return 'outside_availability';
        }

        if ($binding->amount_mode === CampaignPaymentAmountMode::Fixed
            && $payment->grossAmountMinor !== (int) $binding->fixed_amount_minor) {
            return 'fixed_amount_mismatch';
        }

        if (($address->minimum_amount_minor !== null
                && $payment->grossAmountMinor < (int) $address->minimum_amount_minor)
            || ($address->maximum_amount_minor !== null
                && $payment->grossAmountMinor > (int) $address->maximum_amount_minor)) {
            return 'amount_outside_address_limits';
        }

        if ((isset($rules['minimum_amount_minor'])
                && $payment->grossAmountMinor < (int) $rules['minimum_amount_minor'])
            || (isset($rules['maximum_amount_minor'])
                && $payment->grossAmountMinor > (int) $rules['maximum_amount_minor'])) {
            return 'amount_outside_rules';
        }

        $allowedRails = collect($rules['allowed_rails'] ?? [])
            ->map(fn (mixed $rail): string => strtoupper(trim((string) $rail)))
            ->filter()
            ->values()
            ->all();

        if ($allowedRails !== []
            && ($payment->settlementRail === null
                || ! in_array(strtoupper($payment->settlementRail), $allowedRails, true))) {
            return 'settlement_rail_not_allowed';
        }

        $maximumPayments = isset($rules['maximum_payments'])
            ? (int) $rules['maximum_payments']
            : null;

        if ($maximumPayments !== null
            && ($maximumPayments < 1
                || CampaignPaymentRecognition::query()
                    ->where('campaign_payment_qr_binding_id', $binding->getKey())
                    ->count() >= $maximumPayments)) {
            return 'maximum_payments_reached';
        }

        return null;
    }

    private function recognitionKey(
        CampaignPaymentQrBinding $binding,
        CanonicalProviderFundingTransactionData $payment,
    ): string {
        return hash('sha256', implode('|', [
            (string) $binding->getKey(),
            $binding->configuration_hash,
            $payment->providerTransactionKey,
        ]));
    }

    private function assertReplayMatches(
        CampaignPaymentRecognition $recognition,
        CampaignPaymentQrBinding $binding,
        CanonicalProviderFundingTransactionData $payment,
    ): void {
        $matches = hash_equals($recognition->recognition_key, $this->recognitionKey($binding, $payment))
            && hash_equals($recognition->binding_configuration_hash, $binding->configuration_hash)
            && $recognition->campaign_revision_id === $binding->campaign_revision_id
            && $recognition->gross_amount_minor === $payment->grossAmountMinor
            && $recognition->fee_amount_minor === $payment->feeAmountMinor
            && $recognition->net_amount_minor === $payment->netAmountMinor
            && $recognition->currency === $payment->currency;

        if (! $matches) {
            throw new InvalidArgumentException(
                'Existing campaign payment recognition does not match canonical evidence.',
            );
        }
    }

    private function eventData(
        CampaignPaymentQrBinding $binding,
        CampaignPaymentRecognition $recognition,
    ): CampaignPaymentRecognitionData {
        return new CampaignPaymentRecognitionData(
            recognitionReference: $recognition->reference,
            campaignReference: $binding->campaign->reference,
            campaignRevisionId: $recognition->campaign_revision_id,
            bindingReference: $binding->reference,
            provider: $recognition->provider_code,
            providerTransactionKey: $recognition->provider_transaction_key,
            grossAmountMinor: $recognition->gross_amount_minor,
            netAmountMinor: $recognition->net_amount_minor,
            currency: $recognition->currency,
            settlementRail: $recognition->settlement_rail,
            evidenceCount: count($recognition->evidence_observation_ids),
            occurredAt: $recognition->occurred_at->toIso8601String(),
            settledAt: $recognition->settled_at->toIso8601String(),
            recognizedAt: $recognition->recognized_at->toIso8601String(),
        );
    }
}
