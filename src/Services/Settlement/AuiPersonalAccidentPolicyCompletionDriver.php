<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use InvalidArgumentException;
use LBHurtado\XChange\Contracts\CampaignPolicyCompletionDriverContract;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;
use LBHurtado\XChange\Enums\ProvisionalCoverageStatus;
use LBHurtado\XChange\Models\CompletionClaimEvidenceProjection;
use LBHurtado\XChange\Models\VoucherClaimEvidence;

final class AuiPersonalAccidentPolicyCompletionDriver implements CampaignPolicyCompletionDriverContract
{
    public const DRIVER_ID = 'aui.personal-accident.provisional-cover';

    public const DRIVER_VERSION = '1.0.0';

    public function __construct(private CampaignWorkflowPublicationResolver $publications) {}

    public function driverId(): string
    {
        return self::DRIVER_ID;
    }

    public function driverVersion(): string
    {
        return self::DRIVER_VERSION;
    }

    public function prepare(
        CompletionClaimEvidenceProjection $projection,
    ): PolicyCompletionPreparationData {
        if (! $projection->exists) {
            throw new InvalidArgumentException(
                'A persisted completion claim evidence projection is required.',
            );
        }

        $projection->loadMissing([
            'claim.evidence',
            'envelope',
            'payloadVersion',
            'issuance.coverage.recognition',
            'issuance.voucher',
        ]);

        $issuance = $projection->issuance;
        $coverage = $issuance->coverage;
        $recognition = $coverage->recognition;
        $this->publications->forCampaignRevision($recognition->campaignRecord(), (string) $recognition->campaign_revision_id);
        $envelope = $projection->envelope;
        $payloadVersion = $projection->payloadVersion;
        $claim = $projection->claim;

        if ($issuance->driver_id !== self::DRIVER_ID
            || $issuance->driver_version !== self::DRIVER_VERSION
            || $coverage->driver_id !== self::DRIVER_ID
            || $coverage->driver_version !== self::DRIVER_VERSION
            || $envelope->driver_id !== self::DRIVER_ID
            || $envelope->driver_version !== self::DRIVER_VERSION) {
            throw new InvalidArgumentException(
                'AUI policy completion requires the reserved driver identity.',
            );
        }

        if ($projection->envelope_id !== $issuance->envelope_id
            || $projection->envelope_id !== $coverage->envelope_id
            || $projection->envelope_id !== $payloadVersion->envelope_id
            || $payloadVersion->version !== $envelope->payload_version
            || $projection->voucher_claim_id !== $claim->getKey()
            || $issuance->voucher_id !== $claim->voucher_id
            || $coverage->campaign_payment_recognition_id !== $recognition->getKey()) {
            throw new InvalidArgumentException(
                'AUI policy completion facts do not belong to one immutable settlement chain.',
            );
        }

        if ($coverage->status !== ProvisionalCoverageStatus::Provisional
            || $recognition->provider_status !== 'settled'
            || ! $recognition->destination_verified
            || $recognition->settled_at === null
            || $claim->status !== 'redeemed'
            || $claim->completed_at === null) {
            throw new InvalidArgumentException(
                'AUI policy completion facts are not ready for preparation.',
            );
        }

        $manifest = data_get($payloadVersion->payload, 'applicant.evidence');

        if (! is_array($manifest)
            || ! hash_equals($projection->manifest_hash, $this->hash($manifest))) {
            throw new InvalidArgumentException(
                'AUI policy completion evidence manifest does not match its projection.',
            );
        }

        $applicantEvidence = $claim->evidence
            ->whereIn('id', (array) data_get($projection->source_snapshot, 'evidence_record_ids', []))
            ->whereIn('requirement_key', array_column($manifest['items'] ?? [], 'key'))
            ->sortBy('requirement_key')
            ->mapWithKeys(static fn (VoucherClaimEvidence $evidence): array => [
                $evidence->requirement_key => data_get($evidence->payload, 'value'),
            ])
            ->all();
        $fingerprint = $this->hash([
            'driver_id' => self::DRIVER_ID,
            'driver_version' => self::DRIVER_VERSION,
            'projection_reference' => $projection->reference,
            'projection_hash' => $projection->projection_hash,
            'envelope_reference' => $envelope->reference_code,
            'envelope_payload_version' => $envelope->payload_version,
            'coverage_reference' => $coverage->reference,
            'payment_recognition_reference' => $recognition->reference,
        ]);

        return new PolicyCompletionPreparationData(
            driverId: self::DRIVER_ID,
            driverVersion: self::DRIVER_VERSION,
            idempotencyKey: 'aui-policy-completion:'.$projection->reference,
            fingerprint: $fingerprint,
            projectionReference: $projection->reference,
            envelopeReference: $envelope->reference_code,
            envelopePayloadVersion: $envelope->payload_version,
            coverageReference: $coverage->reference,
            coverageType: $coverage->coverage_type,
            coverageAmountMinor: $coverage->coverage_amount_minor,
            currency: $coverage->currency,
            coverageEffectiveAt: $coverage->effective_at,
            coverageExpiresAt: $coverage->expires_at,
            paymentRecognitionReference: $recognition->reference,
            paymentAmountMinor: $recognition->gross_amount_minor,
            paymentSettledAt: $recognition->settled_at,
            completionPayCodeReference: $issuance->reference,
            claimNumber: $claim->claim_number,
            applicantEvidence: $applicantEvidence,
        );
    }

    /** @param array<string, mixed> $value */
    private function hash(array $value): string
    {
        return hash('sha256', json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
    }
}
