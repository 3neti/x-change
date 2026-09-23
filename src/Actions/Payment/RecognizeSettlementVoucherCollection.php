<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Data\Payment\CampaignPaymentRecognitionData;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Events\CampaignPaymentRecognized;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;
use LBHurtado\XChange\Models\CampaignPaymentSource;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;

final readonly class RecognizeSettlementVoucherCollection
{
    public function __construct(
        private ReduceProviderFundingTransactionEvidence $reduce,
        private AuditLoggerContract $audit,
    ) {}

    public function handle(VoucherCollection $collection): CampaignPaymentRecognition
    {
        $outcome = DB::transaction(function () use ($collection): array {
            $lockedCollection = VoucherCollection::query()->lockForUpdate()->findOrFail($collection->getKey());
            $voucher = Voucher::query()->lockForUpdate()->findOrFail($lockedCollection->voucher_id);
            $attempt = PaymentAttempt::query()
                ->where('voucher_collection_id', $lockedCollection->getKey())
                ->lockForUpdate()
                ->sole();
            $observation = ProviderFundingObservation::query()
                ->whereKey($attempt->matched_observation_id)
                ->lockForUpdate()
                ->sole();
            $campaignReference = trim((string) data_get(
                $voucher->metadata,
                'instructions.metadata.custom.lead_campaign.campaign_reference',
            ));
            $campaign = LeadCampaign::query()->where('reference', $campaignReference)->lockForUpdate()->sole();

            $this->assertValid($lockedCollection, $voucher, $attempt, $observation, $campaign);
            $evidence = ProviderFundingObservation::query()
                ->where('provider_code', strtolower((string) $observation->provider_code))
                ->where('provider_transaction_id', $observation->provider_transaction_id)
                ->orderBy('id')->lockForUpdate()->get();
            $canonical = $this->reduce->handle($evidence);
            $configurationHash = $this->hash([
                'source_kind' => 'settlement_voucher_collection',
                'campaign_reference' => $campaign->reference,
                'campaign_revision_id' => $campaign->active_template_version_id,
                'voucher_collection_id' => $lockedCollection->getKey(),
                'payment_attempt_reference' => $attempt->reference,
            ]);
            $source = CampaignPaymentSource::query()->where('voucher_collection_id', $lockedCollection->getKey())->first();

            if (! $source instanceof CampaignPaymentSource) {
                $source = CampaignPaymentSource::query()->create([
                    'endpoint_campaign_id' => $campaign->getKey(),
                    'voucher_collection_id' => $lockedCollection->getKey(),
                    'payment_attempt_id' => $attempt->getKey(),
                    'source_kind' => 'settlement_voucher_collection',
                    'campaign_revision_id' => $campaign->active_template_version_id,
                    'provider_code' => strtolower((string) $attempt->provider_code),
                    'currency' => strtoupper((string) $attempt->currency),
                    'configuration_hash' => $configurationHash,
                ]);
            } elseif (! hash_equals($source->configuration_hash, $configurationHash)) {
                throw new InvalidArgumentException('Existing campaign payment source does not match this settlement collection.');
            }

            $recognitionKey = $this->hash([
                'source' => $source->reference,
                'configuration' => $source->configuration_hash,
                'provider_transaction' => $canonical->providerTransactionKey,
            ]);
            $recognition = CampaignPaymentRecognition::query()
                ->where('campaign_payment_source_id', $source->getKey())
                ->where('provider_transaction_key', $canonical->providerTransactionKey)
                ->first();

            if ($recognition instanceof CampaignPaymentRecognition) {
                if (! hash_equals($recognition->recognition_key, $recognitionKey)
                    || $recognition->gross_amount_minor !== $canonical->grossAmountMinor
                    || $recognition->currency !== $canonical->currency) {
                    throw new InvalidArgumentException('Existing campaign payment recognition does not match canonical settlement evidence.');
                }

                return ['recognition' => $recognition, 'created' => false, 'campaign' => $campaign, 'source' => $source];
            }

            $recognition = CampaignPaymentRecognition::query()->create([
                'recognition_key' => $recognitionKey,
                'campaign_payment_source_id' => $source->getKey(),
                'canonical_provider_funding_observation_id' => $canonical->canonicalObservationId,
                'campaign_revision_id' => $campaign->active_template_version_id,
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
                'evidence_fingerprint' => $this->hash($canonical->evidenceObservationIds),
                'binding_configuration_hash' => $source->configuration_hash,
                'rule_snapshot' => ['source_kind' => $source->source_kind],
                'recognized_at' => now(),
            ]);

            return ['recognition' => $recognition, 'created' => true, 'campaign' => $campaign, 'source' => $source];
        }, attempts: 5);

        $recognition = $outcome['recognition'];
        $campaign = $outcome['campaign'];
        $source = $outcome['source'];

        if ($outcome['created']) {
            $this->audit->log('campaign.payment.recognized', [
                'campaign_reference' => $campaign->reference,
                'campaign_revision_id' => $recognition->campaign_revision_id,
                'payment_source_reference' => $source->reference,
                'recognition_reference' => $recognition->reference,
                'provider' => $recognition->provider_code,
                'gross_amount_minor' => $recognition->gross_amount_minor,
                'net_amount_minor' => $recognition->net_amount_minor,
                'currency' => $recognition->currency,
                'financial_side_effects' => false,
            ]);
            CampaignPaymentRecognized::dispatch(
                ownerType: (string) $campaign->owner_type,
                ownerId: (string) $campaign->owner_id,
                recognition: new CampaignPaymentRecognitionData(
                    recognitionReference: $recognition->reference,
                    campaignReference: $campaign->reference,
                    campaignRevisionId: $recognition->campaign_revision_id,
                    bindingReference: $source->reference,
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
                ),
            );
        }

        return $recognition;
    }

    private function assertValid(VoucherCollection $collection, Voucher $voucher, PaymentAttempt $attempt, ProviderFundingObservation $observation, LeadCampaign $campaign): void
    {
        $flow = data_get($voucher->metadata, 'instructions.metadata.flow_type');
        $ownerMatches = in_array((string) $campaign->owner_type, [$voucher->owner_type, $voucher->owner?->getMorphClass()], true)
            && (string) $campaign->owner_id === (string) $voucher->owner_id;
        $matches = $collection->isSucceeded()
            && $flow === 'settlement'
            && $attempt->status === PaymentAttemptStatus::Settled
            && (int) $attempt->voucher_id === (int) $voucher->getKey()
            && (int) $attempt->voucher_collection_id === (int) $collection->getKey()
            && (int) $attempt->matched_observation_id === (int) $observation->getKey()
            && $observation->provider_status === 'settled'
            && $observation->provider_code === $attempt->provider_code
            && $observation->provider_transaction_id === $collection->provider_transaction_id
            && $observation->gross_amount_minor === $attempt->expected_amount_minor
            && $observation->gross_amount_minor === $collection->collected_amount_minor
            && $observation->currency === $attempt->currency
            && $observation->currency === $collection->currency
            && data_get($observation->metadata, 'destination_verified') === true
            && $ownerMatches;

        if (! $matches) {
            throw new InvalidArgumentException('Settlement collection does not match its campaign, attempt, and canonical provider evidence.');
        }
    }

    /** @param array<mixed> $value */
    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
