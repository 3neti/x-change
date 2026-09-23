<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Exceptions\IncompatibleProviderFundingEvidence;
use LBHurtado\XChange\Models\CampaignPaymentEvidenceQuarantine;
use LBHurtado\XChange\Models\CampaignPaymentQrBinding;

final readonly class InspectCampaignPaymentEvidence
{
    private const CompatibleStatuses = ['pending', 'processing', 'settled'];

    private const AdverseStatuses = ['reversed', 'refunded', 'charged_back', 'returned'];

    public function __construct(
        private ReduceProviderFundingTransactionEvidence $reduce,
        private AuditLoggerContract $audit,
    ) {}

    public function handle(
        CampaignPaymentQrBinding $binding,
        ProviderFundingObservation $observation,
    ): ?CampaignPaymentEvidenceQuarantine {
        $address = $binding->standingFundingAddress;
        $providerCode = strtolower(trim((string) $observation->provider_code));
        $expectedFundingAddress = 'sha256:'.$address->funding_address_hash;

        if (! $observation->exists
            || $providerCode !== strtolower((string) $binding->provider_code)
            || ! hash_equals($expectedFundingAddress, (string) $observation->funding_address)) {
            throw new InvalidArgumentException(
                'Campaign payment evidence does not belong to the bound provider destination.',
            );
        }

        $evidence = ProviderFundingObservation::query()
            ->where('provider_code', $providerCode)
            ->where('provider_transaction_id', $observation->provider_transaction_id)
            ->orderBy('id')
            ->get();
        $transactionKey = hash('sha256', implode("\0", [
            $providerCode,
            (string) $observation->provider_transaction_id,
        ]));
        $reasonCode = null;
        $reasonDetail = null;

        try {
            $canonical = $this->reduce->handle($evidence);
            $status = strtolower(trim($canonical->providerStatus));

            if (in_array($status, self::AdverseStatuses, true)) {
                $reasonCode = 'adverse_status';
                $reasonDetail = $status;
            } elseif (! in_array($status, self::CompatibleStatuses, true)) {
                $reasonCode = 'unknown_status';
                $reasonDetail = $status;
            }
        } catch (IncompatibleProviderFundingEvidence $exception) {
            $reasonCode = 'incompatible_evidence';
            $reasonDetail = $exception->classification;
        } catch (InvalidArgumentException) {
            $reasonCode = 'invalid_evidence';
            $reasonDetail = 'canonical_evidence_rejected';
        }

        if ($reasonCode === null) {
            return null;
        }

        $evidenceIds = $evidence->modelKeys();
        $evidenceFingerprint = hash('sha256', implode('|', array_map('strval', $evidenceIds)));
        $quarantineKey = hash('sha256', implode('|', [
            (string) $binding->getKey(),
            $transactionKey,
        ]));

        $quarantine = DB::transaction(fn (): CampaignPaymentEvidenceQuarantine => CampaignPaymentEvidenceQuarantine::query()->firstOrCreate(
            ['quarantine_key' => $quarantineKey],
            [
                'campaign_payment_qr_binding_id' => $binding->getKey(),
                'provider_funding_observation_id' => $observation->getKey(),
                'provider_code' => $providerCode,
                'provider_transaction_key' => $transactionKey,
                'reason_code' => $reasonCode,
                'reason_detail' => $reasonDetail,
                'evidence_observation_ids' => $evidenceIds,
                'evidence_fingerprint' => $evidenceFingerprint,
                'opened_at' => now(),
            ],
        ), attempts: 3);

        if ($quarantine->wasRecentlyCreated) {
            $this->audit->log('campaign.payment.evidence_quarantined', [
                'campaign_reference' => $binding->campaign->reference,
                'campaign_revision_id' => $binding->campaign_revision_id,
                'binding_reference' => $binding->reference,
                'quarantine_reference' => $quarantine->reference,
                'provider' => $providerCode,
                'reason_code' => $reasonCode,
                'reason_detail' => $reasonDetail,
                'evidence_count' => count($evidenceIds),
                'financial_side_effects' => false,
            ]);
        }

        return $quarantine;
    }
}
