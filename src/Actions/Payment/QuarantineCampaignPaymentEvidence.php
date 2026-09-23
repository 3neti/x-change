<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Models\CampaignPaymentEvidenceQuarantine;
use LBHurtado\XChange\Models\CampaignPaymentQrBinding;

final readonly class QuarantineCampaignPaymentEvidence
{
    public function __construct(private AuditLoggerContract $audit) {}

    /**
     * @param  list<int>  $evidenceObservationIds
     */
    public function handle(
        CampaignPaymentQrBinding $binding,
        ProviderFundingObservation $trigger,
        string $providerTransactionKey,
        string $reasonCode,
        ?string $reasonDetail,
        array $evidenceObservationIds,
    ): CampaignPaymentEvidenceQuarantine {
        $evidenceFingerprint = hash(
            'sha256',
            implode('|', array_map('strval', $evidenceObservationIds)),
        );
        $quarantineKey = hash('sha256', implode('|', [
            (string) $binding->getKey(),
            $providerTransactionKey,
        ]));

        $quarantine = DB::transaction(fn (): CampaignPaymentEvidenceQuarantine => CampaignPaymentEvidenceQuarantine::query()->firstOrCreate(
            ['quarantine_key' => $quarantineKey],
            [
                'campaign_payment_qr_binding_id' => $binding->getKey(),
                'provider_funding_observation_id' => $trigger->getKey(),
                'provider_code' => strtolower((string) $binding->provider_code),
                'provider_transaction_key' => $providerTransactionKey,
                'reason_code' => $reasonCode,
                'reason_detail' => $reasonDetail,
                'evidence_observation_ids' => $evidenceObservationIds,
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
                'provider' => $binding->provider_code,
                'reason_code' => $reasonCode,
                'reason_detail' => $reasonDetail,
                'evidence_count' => count($evidenceObservationIds),
                'financial_side_effects' => false,
            ]);
        }

        return $quarantine;
    }
}
