<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Services\DriverService;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\SettlementEnvelope\Services\PayloadValidator;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Data\Settlement\ProvisionalCoverageBoundData;
use LBHurtado\XChange\Data\Settlement\ProvisionalCoverageEnvelopeData;
use LBHurtado\XChange\Data\Settlement\ProvisionalCoverageTermsData;
use LBHurtado\XChange\Enums\ProvisionalCoverageStatus;
use LBHurtado\XChange\Events\ProvisionalCoverageBound;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;
use LBHurtado\XChange\Models\ProvisionalCoverage;

final readonly class BindProvisionalCoverage
{
    public function __construct(
        private EnvelopeService $envelopes,
        private DriverService $drivers,
        private PayloadValidator $payloadValidator,
        private AuditLoggerContract $audit,
    ) {}

    public function handle(
        CampaignPaymentRecognition $recognition,
        ProvisionalCoverageTermsData $terms,
    ): ProvisionalCoverageEnvelopeData {
        $this->assertValid($recognition, $terms);

        $result = DB::transaction(function () use ($recognition, $terms): array {
            $locked = CampaignPaymentRecognition::query()
                ->with(['binding.campaign', 'binding.standingFundingAddress'])
                ->lockForUpdate()
                ->findOrFail($recognition->getKey());
            $snapshots = $this->snapshots($locked, $terms);
            $coverageKey = $this->hash([
                'recognition_reference' => $locked->reference,
                'driver_id' => $terms->driverId,
                'driver_version' => $terms->driverVersion,
                'snapshot_hash' => $snapshots['snapshot_hash'],
            ]);
            $existing = ProvisionalCoverage::query()
                ->with('envelope')
                ->where('campaign_payment_recognition_id', $locked->getKey())
                ->first();

            if ($existing instanceof ProvisionalCoverage) {
                if (! hash_equals($existing->coverage_key, $coverageKey)
                    || ! hash_equals($existing->snapshot_hash, $snapshots['snapshot_hash'])) {
                    throw new InvalidArgumentException(
                        'Existing provisional coverage does not match the requested binding.',
                    );
                }

                return ['coverage' => $existing, 'envelope' => $existing->envelope, 'created' => false];
            }

            $coverageReference = (string) Str::ulid();
            $payload = $this->envelopePayload($locked, $terms, $snapshots, $coverageReference);
            $driver = $this->drivers->load($terms->driverId, $terms->driverVersion);
            $schema = $this->drivers->getSchema($driver);

            if ($schema !== null) {
                $this->payloadValidator->validate($payload, $driver, $schema);
            }

            $envelope = $this->envelopes->create(
                referenceCode: 'campaign-coverage:'.$coverageReference,
                driverId: $terms->driverId,
                driverVersion: $terms->driverVersion,
                reference: $locked,
                initialPayload: $payload,
                context: [
                    'schema' => 'x-change.campaign-provisional-coverage-context.v1',
                    'coverage_reference' => $coverageReference,
                    'recognition_reference' => $locked->reference,
                    'campaign_reference' => $locked->binding->campaign->reference,
                ],
            );
            $boundAt = now();
            $coverage = ProvisionalCoverage::query()->create([
                'reference' => $coverageReference,
                'coverage_key' => $coverageKey,
                'campaign_payment_recognition_id' => $locked->getKey(),
                'envelope_id' => $envelope->getKey(),
                'endpoint_campaign_id' => $locked->binding->endpoint_campaign_id,
                'campaign_payment_qr_binding_id' => $locked->binding->getKey(),
                'campaign_revision_id' => $locked->campaign_revision_id,
                'driver_id' => $terms->driverId,
                'driver_version' => $terms->driverVersion,
                'status' => ProvisionalCoverageStatus::Provisional,
                'coverage_type' => $terms->coverageType,
                'coverage_amount_minor' => $terms->coverageAmountMinor,
                'currency' => strtoupper($terms->currency),
                'effective_at' => $terms->effectiveAt,
                'expires_at' => $terms->expiresAt,
                'payment_snapshot' => $snapshots['payment'],
                'terms_snapshot' => $snapshots['terms'],
                'authorization_snapshot' => $snapshots['authorization'],
                'payment_snapshot_hash' => $snapshots['payment_hash'],
                'terms_snapshot_hash' => $snapshots['terms_hash'],
                'authorization_snapshot_hash' => $snapshots['authorization_hash'],
                'snapshot_hash' => $snapshots['snapshot_hash'],
                'bound_at' => $boundAt,
            ]);

            return ['coverage' => $coverage, 'envelope' => $envelope, 'created' => true];
        }, attempts: 5);

        /** @var ProvisionalCoverage $coverage */
        $coverage = $result['coverage'];
        /** @var Envelope $envelope */
        $envelope = $result['envelope'];

        if ($result['created'] === true) {
            $locked = $coverage->recognition->loadMissing([
                'binding.campaign',
                'binding.standingFundingAddress',
            ]);
            $binding = $locked->binding;
            $data = new ProvisionalCoverageBoundData(
                coverageReference: $coverage->reference,
                envelopeReference: $envelope->reference_code,
                recognitionReference: $locked->reference,
                campaignReference: $binding->campaign->reference,
                campaignRevisionId: $coverage->campaign_revision_id,
                bindingReference: $binding->reference,
                driverId: $coverage->driver_id,
                driverVersion: $coverage->driver_version,
                coverageType: $coverage->coverage_type,
                coverageAmountMinor: $coverage->coverage_amount_minor,
                currency: $coverage->currency,
                effectiveAt: $coverage->effective_at->toIso8601String(),
                expiresAt: $coverage->expires_at?->toIso8601String(),
            );
            $this->audit->log('campaign.provisional_coverage.bound', [
                ...$data->toBroadcastArray(),
                'financial_side_effects' => false,
            ]);
            ProvisionalCoverageBound::dispatch(
                ownerType: (string) $binding->standingFundingAddress->owner_type,
                ownerId: (string) $binding->standingFundingAddress->owner_id,
                coverage: $data,
            );
        }

        return new ProvisionalCoverageEnvelopeData($coverage, $envelope, $result['created']);
    }

    private function assertValid(
        CampaignPaymentRecognition $recognition,
        ProvisionalCoverageTermsData $terms,
    ): void {
        if (! $recognition->exists) {
            throw new InvalidArgumentException('A persisted campaign payment recognition is required.');
        }

        if (trim($terms->driverId) === ''
            || trim($terms->driverVersion) === ''
            || trim($terms->coverageType) === '') {
            throw new InvalidArgumentException('Driver identity, version, and coverage type are required.');
        }

        if (strtoupper($terms->currency) !== strtoupper((string) $recognition->currency)) {
            throw new InvalidArgumentException('Coverage currency must match the recognized payment.');
        }

        if ($recognition->settled_at === null
            || ! $terms->effectiveAt->equalTo($recognition->settled_at)) {
            throw new InvalidArgumentException('Coverage effective time must equal payment settlement time.');
        }

        if ($terms->expiresAt !== null && ! $terms->expiresAt->greaterThan($terms->effectiveAt)) {
            throw new InvalidArgumentException('Coverage expiry must be after its effective time.');
        }

        if ($terms->coverageAmountMinor !== null && $terms->coverageAmountMinor < 1) {
            throw new InvalidArgumentException('Coverage amount must be positive when supplied.');
        }

        if (trim((string) ($terms->authorization['authority'] ?? '')) === ''
            || trim((string) ($terms->authorization['authority_reference'] ?? '')) === '') {
            throw new InvalidArgumentException('Coverage authorization provenance is required.');
        }
    }

    /** @return array<string, array<string, mixed>|string> */
    private function snapshots(
        CampaignPaymentRecognition $recognition,
        ProvisionalCoverageTermsData $terms,
    ): array {
        $payment = $this->canonicalize([
            'schema' => 'x-change.campaign-payment-snapshot.v1',
            'recognition_reference' => $recognition->reference,
            'provider' => $recognition->provider_code,
            'gross_amount_minor' => $recognition->gross_amount_minor,
            'fee_amount_minor' => $recognition->fee_amount_minor,
            'net_amount_minor' => $recognition->net_amount_minor,
            'currency' => $recognition->currency,
            'settlement_rail' => $recognition->settlement_rail,
            'occurred_at' => $recognition->occurred_at?->toIso8601String(),
            'settled_at' => $recognition->settled_at?->toIso8601String(),
        ]);
        $termSnapshot = $this->canonicalize([
            'schema' => 'x-change.provisional-coverage-terms.v1',
            'driver_id' => $terms->driverId,
            'driver_version' => $terms->driverVersion,
            'coverage_type' => $terms->coverageType,
            'coverage_amount_minor' => $terms->coverageAmountMinor,
            'currency' => strtoupper($terms->currency),
            'effective_at' => $terms->effectiveAt->toIso8601String(),
            'expires_at' => $terms->expiresAt?->toIso8601String(),
            'terms' => $terms->terms,
        ]);
        $authorization = $this->canonicalize([
            'schema' => 'x-change.provisional-coverage-authorization.v1',
            ...$terms->authorization,
        ]);
        $paymentHash = $this->hash($payment);
        $termsHash = $this->hash($termSnapshot);
        $authorizationHash = $this->hash($authorization);

        return [
            'payment' => $payment,
            'terms' => $termSnapshot,
            'authorization' => $authorization,
            'payment_hash' => $paymentHash,
            'terms_hash' => $termsHash,
            'authorization_hash' => $authorizationHash,
            'snapshot_hash' => $this->hash([$paymentHash, $termsHash, $authorizationHash]),
        ];
    }

    /** @param array<string, array<string, mixed>|string> $snapshots */
    private function envelopePayload(
        CampaignPaymentRecognition $recognition,
        ProvisionalCoverageTermsData $terms,
        array $snapshots,
        string $coverageReference,
    ): array {
        return $this->canonicalize([
            'schema' => 'x-change.campaign-provisional-coverage-envelope.v1',
            'campaign' => [
                'campaign_reference' => $recognition->binding->campaign->reference,
                'campaign_revision_id' => $recognition->campaign_revision_id,
                'binding_reference' => $recognition->binding->reference,
            ],
            'payment' => $snapshots['payment'],
            'coverage' => [
                'coverage_reference' => $coverageReference,
                ...$snapshots['terms'],
            ],
            'authorization' => $snapshots['authorization'],
        ]);
    }

    /** @return array<mixed> */
    private function canonicalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private function hash(array $value): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
    }
}
